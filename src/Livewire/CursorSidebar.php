<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Services\LlmPlanner;
use Platform\Core\Services\CommandDispatcher;
use Platform\Core\Services\CommandGateway;
use Platform\Core\Services\IntentMatcher;
use Platform\Core\Services\CommandPolicy;
use Platform\Core\Services\CommandResolver;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Models\CoreChatEvent;
use Platform\Core\Services\ModelNameResolver;

class CursorSidebar extends Component
{
    public bool $open = false;
    public string $input = '';
    public bool $forceExecute = false;
    public array $feed = [];
    public string $lastUserText = '';
    public ?int $chatId = null;
    public int $totalTokensIn = 0;
    public int $totalTokensOut = 0;
    public int $activeChatsCount = 0;
    public array $recentChats = [];
    public bool $isWorking = false;
    public ?string $pendingNavigate = null;

    public function mount(): void
    {
        // Aktiven Chat aus Session wiederherstellen
        $sid = session('core_chat_id');
        if ($sid) {
            $this->chatId = (int) $sid;
            $this->loadFeedFromChat();
        }
    }

    #[On('cursor-sidebar-toggle')]
    public function toggle(): void
    {
        $this->open = !$this->open;
        if ($this->open) {
            $this->ensureChat();
        }
    }

    public function send(): void
    {
        $text = trim($this->input);
        if ($text === '') return;
        $this->debugLog(['phase' => 'input', 'text' => $text, 'force' => $this->forceExecute, 'mode' => config('agent.mode'), 'include' => config('agent.include'), 'exclude' => config('agent.exclude')]);
        $this->feed[] = ['role' => 'user', 'text' => $text];
        $this->lastUserText = $text;
        $this->ensureChat();
        $this->saveMessage('user', $text, ['forceExecute' => $this->forceExecute]);
        $this->isWorking = true;
        $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => 'Arbeite …']];

        // Iterative Tool-Loop: LLM darf mehrere Tools nacheinander wählen
        $planner = new LlmPlanner();
        
        // DEBUG: CRM Debug-Info in Chat ausgeben
        $crmDebugInfo = session('crm_debug_info');
        if ($crmDebugInfo) {
            $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => [
                'text' => "🔍 **CRM Debug-Info:**\n" . implode("\n", $crmDebugInfo)
            ]];
            session()->forget('crm_debug_info'); // Einmalig anzeigen
        }
        
        // Debug: verfügbare Tools nach Policy anzeigen
        if (config('agent.debug_log')) {
            try {
                $schemasAll = \Platform\Core\Registry\CommandRegistry::exportFunctionSchemas();
                $filtered = (new \Platform\Core\Services\ToolFilterPolicy())->filter($schemasAll, $text);
                $toolNamesAll = array_map(fn($s) => $s['name'] ?? '', $schemasAll);
                $toolNamesFiltered = array_map(fn($s) => $s['name'] ?? '', $filtered);
                $this->debugLog([
                    'phase' => 'tools.built',
                    'all' => count($schemasAll),
                    'filtered' => count($filtered),
                    'names_all' => $toolNamesAll,
                    'names_filtered' => $toolNamesFiltered,
                ]);
            } catch (\Throwable $e) {
                $this->debugLog(['phase' => 'tools.build_error', 'err' => $e->getMessage()]);
            }
        }
        // Chat-Verlauf (nur user/assistant-Plaintext) als Kontext einspeisen – letzte Nachrichten bevorzugen
        // Token-Budget grob: ~4 Zeichen pro Token
        $approxToken = function(string $s) { return (int) ceil(mb_strlen($s) / 4); };
        $maxTokens = 3000; // ein großzügiges Kontextbudget
        $used = 0;
        $history = [];
        for ($i = count($this->feed) - 1; $i >= 0; $i--) {
            $b = $this->feed[$i];
            if (($b['role'] ?? '') === 'user' && !empty($b['text'] ?? '')) {
                $t = $b['text'];
                $need = $approxToken($t);
                if ($used + $need > $maxTokens) break;
                $used += $need;
                $history[] = ['role' => 'user', 'content' => $t];
            }
            if (($b['role'] ?? '') === 'assistant' && ($b['type'] ?? '') === 'message') {
                $t = $b['data']['text'] ?? '';
                if ($t === '') continue;
                $need = $approxToken($t);
                if ($used + $need > $maxTokens) break;
                $used += $need;
                $history[] = ['role' => 'assistant', 'content' => $t];
            }
        }
        // Umkehren, damit die älteren zuerst im Verlauf stehen
        $history = array_reverse($history);
        $messages = $planner->initialMessages($text, $history);
        // Optionales kurzes Reasoning/Plan-Preview als ToDo-Liste
        try {
            if (config('agent.reason_first', true)) {
                $plan = [
                    ['step' => 'Analysieren', 'done' => true],
                    ['step' => 'Fehlende Infos klären (needResolve)', 'done' => false],
                    ['step' => 'Befehl ausführen', 'done' => false],
                    ['step' => 'Ergebnis bestätigen / navigieren', 'done' => false],
                ];
                $this->feed[] = ['role' => 'assistant', 'type' => 'plan', 'data' => $plan];
                $this->saveMessage('assistant', json_encode(['plan' => $plan], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ['kind' => 'plan']);
                $this->saveEvent('plan', ['text' => $text, 'plan' => $plan]);
            }
        } catch (\Throwable $e) {
            // Plan-Anzeige ist optional – Fehler hier ignorieren
        }
        // Kontext-Modul holen (für Modell-Normalisierung)
        $contextModule = null;
        try {
            $ctx = $this->executeIntent('core.context_get', [], true);
            $contextModule = $ctx['data']['module'] ?? null;
        } catch (\Throwable $e) {
            $contextModule = null;
        }
        $maxSteps = 12;
        for ($i = 0; $i < $maxSteps; $i++) {
            $step = $planner->step($messages, $text);
            $this->debugLog(['phase' => 'llm.step', 'index' => $i, 'type' => $step['type'] ?? 'unknown']);
            // Fehler oder reine Assistant-Antwort → anzeigen und abbrechen
            if (!($step['ok'] ?? false)) {
                $msg = ($step['message'] ?? 'LLM Fehler');
                $detail = is_string($step['detail'] ?? null) ? $step['detail'] : json_encode($step['detail'] ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $textErr = $detail ? ($msg.': '.$detail) : $msg;
                $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => $textErr]];
                $this->saveMessage('assistant', $textErr, ['kind' => 'error']);
                break;
            }
            if (($step['type'] ?? '') === 'assistant') {
                $textOut = trim($step['text'] ?? '');
                if ($textOut !== '') {
                    $this->debugLog(['phase' => 'llm.assistant_only', 'len' => mb_strlen($textOut)]);
                    // Falls die Assistant-Antwort eine Navigation als JSON enthält, direkt übernehmen
                    $maybe = json_decode($textOut, true);
                    if (is_array($maybe) && !empty($maybe['navigate'] ?? null) && is_string($maybe['navigate'])) {
                        $this->pendingNavigate = $maybe['navigate'];
                    } else {
                        $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => $textOut]];
                        $this->saveMessage('assistant', $textOut, ['kind' => 'message']);
                    }
                }
                break;
            }
            if (($step['type'] ?? '') === 'tools') {
                // ganze Assistant-Nachricht in den Verlauf
                if (!empty($step['raw'])) {
                    $messages[] = $step['raw'];
                }
                // Tool-Calls priorisieren (sichere Read-Intents zuerst) und bis zu 2 davon spekulativ ausführen
                $calls = $step['calls'];
                $this->debugLog(['phase' => 'llm.tools_received', 'count' => count($calls), 'intents' => array_map(fn($c) => $c['intent'] ?? '', $calls)]);
                usort($calls, function($a, $b){
                    $wa = $this->intentWeight((string)($a['intent'] ?? ''));
                    $wb = $this->intentWeight((string)($b['intent'] ?? ''));
                    return $wb <=> $wa;
                });
                $safeExecuted = 0;
                $didQuery = false;
                $didOpen  = false;
                foreach ($calls as $call) {
                    $intent = $call['intent'] ?? '';
                    $slots  = $call['slots'] ?? [];
                    if (($slots['model'] ?? '') === '' && str_ends_with($intent, '.query') || str_ends_with($intent, '.open') || $intent === 'core.model.query') {
                        $guessed = (new ModelNameResolver())->resolveFromText($this->lastUserText);
                        if ($guessed) { $slots['model'] = $guessed; }
                    }
                    // Modell normalisieren (ohne Modulpräfix) mit Kontext
                    if (!empty($slots['model'] ?? null) && !str_contains((string) $slots['model'], '.')) {
                        $norm = (new ModelNameResolver())->resolveFromText((string) $slots['model']);
                        if (!$norm && is_string($contextModule) && $contextModule !== '') {
                            $norm = $contextModule . '.' . (string) $slots['model'];
                        }
                        if ($norm) { $slots['model'] = $norm; }
                    }
                    // Auto-Vorbelegung für Create-Intents: model & title aus Nutzertext
                    if ($intent === 'core.model.create' || str_ends_with($intent, '.create')) {
                        if (($slots['model'] ?? '') === '') {
                            $slots['model'] = (new ModelNameResolver())->resolveFromText($this->lastUserText);
                        }
                        $data = (array)($slots['data'] ?? []);
                        if (($data['title'] ?? '') === '') {
                            $data['title'] = $this->extractTitleFromText($this->lastUserText);
                        }
                        $slots['data'] = $data;
                    }
                    $autoAllowed = (new CommandPolicy())->isAutoAllowed($intent);
                    if (!$this->forceExecute && !$autoAllowed) {
                        $this->debugLog(['phase' => 'confirm_required', 'intent' => $intent]);
                        $this->feed[] = ['role' => 'assistant', 'type' => 'confirm', 'data' => [
                            'intent' => $intent,
                            'impact' => 'medium',
                            'slots' => $slots,
                            'confidence' => 0.0,
                        ]];
                        $this->saveMessage('assistant', json_encode(['confirm' => ['intent' => $intent, 'slots' => $slots]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ['kind' => 'confirm']);
                        break 2; // verlasse Loop
                    }
                    $override = !$this->isWriteIntent($intent) || !(!empty($slots['confirmed']) ? false : true);
                    // override = true für Reads; für Writes nur wenn confirmed=true
                    if ($this->isWriteIntent($intent) && empty($slots['confirmed'])) {
                        $override = false;
                    }
                    $result = $this->executeIntent($intent, $slots, $override);
                    $this->debugLog(['phase' => 'tool_call', 'intent' => $intent, 'slots' => $slots, 'ok' => $result['ok'] ?? null, 'navigate' => $result['navigate'] ?? null]);
                    $this->debugLog(['intent' => $intent, 'slots' => $slots, 'result_ok' => $result['ok'] ?? null, 'navigate' => $result['navigate'] ?? null]);
                    if (($result['ok'] ?? false) && !empty($result['navigate'] ?? null)) {
                        return; // direkte Navigation
                    }
                    // Erfolgreichen Write loggen, aber Chain nicht abbrechen (weitere Schritte erlaubt)
                    if ($this->isWriteIntent($intent) && ($result['ok'] ?? false)) {
                        $this->saveEvent('write_ok', [
                            'intent' => $intent,
                            'slots' => $slots,
                            'result' => ['id' => $result['data']['id'] ?? null],
                        ]);
                    }
                    // Fallback für fehlgeschlagene *.show Routen: query → open
                    if (!($result['ok'] ?? true) && str_ends_with(mb_strtolower($intent), '.show')) {
                        $modelFromIntent = $this->inferModelFromShowIntent($intent);
                        if ($modelFromIntent) {
                            $queryTerm = $this->extractBestQueryFromSlots($slots) ?: $this->lastUserText;
                            $qr = $this->executeIntent('core.model.query', ['model' => $modelFromIntent, 'q' => $queryTerm, 'limit' => 5], true);
                            $items = (array)($qr['data']['items'] ?? []);
                            if (count($items) === 1) {
                                $this->executeIntent('core.model.open', ['model' => $modelFromIntent, 'id' => $items[0]['id'] ?? null], true);
                                return;
                            }
                            if (count($items) > 1) {
                                $choices = array_slice($items, 0, 6);
                                $this->feed[] = ['role' => 'assistant', 'type' => 'choices', 'data' => [
                                    'title' => 'Mehrere Treffer – bitte wählen:',
                                    'items' => array_map(fn($it) => ['id' => $it['id'] ?? null, 'name' => ($it['name'] ?? ($it['title'] ?? (string)($it['id'] ?? '')))], $choices),
                                ]];
                                break 2;
                            }
                        }
                    }
                    // Generischer Resolver für *.open mit name/title → *.query → open
                    if ((!$result['ok'])
                        && str_ends_with($intent, '.open')
                        && (!empty($slots['name'] ?? null) || !empty($slots['title'] ?? null))
                        && !empty($slots['model'] ?? null)) {
                        $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
                        $queryIntent = str_replace('.open', '.query', $intent);
                        $resolved = (new CommandResolver())->resolveOpenByName(
                            $gateway,
                            $queryIntent,
                            $intent,
                            [
                                'model' => $slots['model'],
                                'q' => $slots['name'] ?? ($slots['title'] ?? ''),
                                'limit' => 5,
                            ]
                        );
                        $this->debugLog(['phase' => 'resolver', 'intent' => $intent, 'resolved_ok' => $resolved['ok'] ?? null, 'choices' => count($resolved['choices'] ?? [])]);
                        if (($resolved['ok'] ?? false) && !empty($resolved['navigate'] ?? null)) {
                            return;
                        }
                        if (!($resolved['ok'] ?? true) && !empty($resolved['needResolve'] ?? null) && !empty($resolved['choices'] ?? [])) {
                            // DB-Event: needResolve mit Auswahl
                            $this->saveEvent('need_resolve', [
                                'intent' => $intent,
                                'slots' => $slots,
                                'message' => $resolved['message'] ?? 'Bitte wählen',
                                'choices' => array_slice($resolved['choices'], 0, 6),
                            ]);
                            $this->feed[] = ['role' => 'assistant', 'type' => 'choices', 'data' => [
                                'title' => 'Bitte wählen:',
                                'items' => array_map(fn($c) => ['id' => $c['id'], 'name' => $c['label']], array_slice($resolved['choices'], 0, 6)),
                            ]];
                            break 2;
                        }
                    }
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                        'tool_call_id' => $call['tool_call_id'] ?? null,
                    ];
                    // Ergebnisse vollständig im Chat listen, falls vorhanden
                    if (($result['ok'] ?? false) && is_array($result['data']['items'] ?? null)) {
                        $items = $result['data']['items'];
                        $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => [
                            'text' => $this->formatItemsFull($items)
                        ]];
                    }
                    // Spekulative Ausführung: sicherstellen, dass mindestens eine Query UND eine Open ausgeführt wurde
                    if ($autoAllowed) {
                        if ($this->isQueryIntent($intent)) { $didQuery = true; }
                        if ($this->isOpenIntent($intent))  { $didOpen  = true; }
                        $safeExecuted++;
                        if ($didQuery && $didOpen) {
                            $this->debugLog(['phase' => 'speculative_done', 'query' => $didQuery, 'open' => $didOpen, 'count' => $safeExecuted]);
                            break 2;
                        }
                    }
                }
                // nach Antworten auf alle tool_calls plant das LLM den nächsten Schritt
                $this->debugLog(['phase' => 'llm.next_after_tools']);
                continue;
            }
            // Tool-Aufruf
            $intent = $step['intent'] ?? '';
            $slots  = $step['slots'] ?? [];
            $autoAllowed = (new CommandPolicy())->isAutoAllowed($intent);
            if (!$this->forceExecute && !$autoAllowed) {
                // Bestätigungs-Bubble und abbrechen
                $this->feed[] = ['role' => 'assistant', 'type' => 'confirm', 'data' => [
                    'intent' => $intent,
                    'impact' => 'medium',
                    'slots' => $slots,
                    'confidence' => 0.0,
                ]];
                $this->saveMessage('assistant', json_encode(['confirm' => ['intent' => $intent, 'slots' => $slots]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ['kind' => 'confirm']);
                break;
            }
            // Assistant-Nachricht (mit tool_calls) muss in den Verlauf, bevor das Tool-Ergebnis folgt
            if (!empty($step['raw'])) {
                $messages[] = $step['raw'];
            }
            // Modell normalisieren (single tool call)
            if (!empty($slots['model'] ?? null) && !str_contains((string) $slots['model'], '.')) {
                $norm = (new ModelNameResolver())->resolveFromText((string) $slots['model']);
                if (!$norm && is_string($contextModule) && $contextModule !== '') {
                    $norm = $contextModule . '.' . (string) $slots['model'];
                }
                if ($norm) { $slots['model'] = $norm; }
            }
            $override = !$this->isWriteIntent($intent) || !(!empty($slots['confirmed']) ? false : true);
            if ($this->isWriteIntent($intent) && empty($slots['confirmed'])) {
                $override = false;
            }
            $result = $this->executeIntent($intent, $slots, $override);
            $this->debugLog(['phase' => 'tool_call_single', 'intent' => $intent, 'slots' => $slots, 'ok' => $result['ok'] ?? null, 'navigate' => $result['navigate'] ?? null]);
            $this->debugLog(['intent' => $intent, 'slots' => $slots, 'result_ok' => $result['ok'] ?? null, 'navigate' => $result['navigate'] ?? null]);
            if (($result['ok'] ?? false) && !empty($result['navigate'] ?? null)) {
                // Sofortige Navigation, aber vorher Eingabe leeren und Feed/Message persistieren
                $this->input = '';
                $this->pendingNavigate = $result['navigate'];
                // Sofort navigieren erst nach Abschluss des Loops
                break;
            }
            // Erfolgreichen Write loggen, aber Chain nicht abbrechen (weitere Schritte erlaubt)
            if ($this->isWriteIntent($intent) && ($result['ok'] ?? false)) {
                $this->saveEvent('write_ok', [
                    'intent' => $intent,
                    'slots' => $slots,
                    'result' => ['id' => $result['data']['id'] ?? null],
                ]);
            }
            // Fallback für fehlgeschlagene *.show Routen: query → open
            if (!($result['ok'] ?? true) && str_ends_with(mb_strtolower($intent), '.show')) {
                $modelFromIntent = $this->inferModelFromShowIntent($intent);
                if ($modelFromIntent) {
                    $queryTerm = $this->extractBestQueryFromSlots($slots) ?: $this->lastUserText;
                    $qr = $this->executeIntent('core.model.query', ['model' => $modelFromIntent, 'q' => $queryTerm, 'limit' => 5], true);
                    $items = (array)($qr['data']['items'] ?? []);
                    if (count($items) === 1) {
                        $this->executeIntent('core.model.open', ['model' => $modelFromIntent, 'id' => $items[0]['id'] ?? null], true);
                            $this->isWorking = false;
                            return;
                    }
                    if (count($items) > 1) {
                        $choices = array_slice($items, 0, 6);
                        $this->feed[] = ['role' => 'assistant', 'type' => 'choices', 'data' => [
                            'title' => 'Mehrere Treffer – bitte wählen:',
                            'items' => array_map(fn($it) => ['id' => $it['id'] ?? null, 'name' => ($it['name'] ?? ($it['title'] ?? (string)($it['id'] ?? '')))], $choices),
                        ]];
                        return;
                    }
                }
            }
            if (($result['ok'] ?? false) && is_array($result['data']['items'] ?? null)) {
                $items = $result['data']['items'];
                $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => [
                    'text' => $this->formatItemsFull($items)
                ]];
            }
            // Intelligente Nachfrage: Sprachmodell kann selbst entscheiden
            if (!empty($result['needResolve'] ?? null) && !empty($result['message'] ?? null) && !$result['ok']) {
                // DB-Event: needResolve ohne Auswahl
                $this->saveEvent('need_resolve', [
                    'intent' => $intent,
                    'slots' => $slots,
                    'message' => $result['message'],
                    'missing' => $result['missing'] ?? null,
                ]);
                $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => [
                    'text' => $result['message']
                ]];
                break;
            }
            
            // Generischer Resolver für *.open mit name/title + model
            if ((!$result['ok'])
                && str_ends_with($intent, '.open')
                && (!empty($slots['name'] ?? null) || !empty($slots['title'] ?? null))
                && !empty($slots['model'] ?? null)) {
                $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
                $queryIntent = str_replace('.open', '.query', $intent);
                $resolved = (new CommandResolver())->resolveOpenByName(
                    $gateway,
                    $queryIntent,
                    $intent,
                    [
                        'model' => $slots['model'],
                        'q' => $slots['name'] ?? ($slots['title'] ?? ''),
                        'limit' => 5,
                    ]
                );
                if (($resolved['ok'] ?? false) && !empty($resolved['navigate'] ?? null)) {
                    // Direkte Navigation
                $this->input = '';
                $this->pendingNavigate = $resolved['navigate'];
                break;
                }
                if (!($resolved['ok'] ?? true) && !empty($resolved['needResolve'] ?? null) && !empty($resolved['choices'] ?? [])) {
                    // DB-Event: needResolve mit Auswahl (single-call Zweig)
                    $this->saveEvent('need_resolve', [
                        'intent' => $intent,
                        'slots' => $slots,
                        'message' => $resolved['message'] ?? 'Bitte wählen',
                        'choices' => array_slice($resolved['choices'], 0, 6),
                    ]);
                    $this->feed[] = ['role' => 'assistant', 'type' => 'choices', 'data' => [
                        'title' => 'Bitte wählen:',
                        'items' => array_map(fn($c) => ['id' => $c['id'], 'name' => $c['label']], array_slice($resolved['choices'], 0, 6)),
                    ]];
                    break;
                }
            }
            // Tool-Resultat in den LLM-Kontext rückführen
            $messages[] = [
                'role' => 'tool',
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                // Muss die tool_call_id der vorherigen Assistant-Nachricht referenzieren
                'tool_call_id' => $step['tool_call_id'] ?? null,
            ];
        }

        $this->input = '';
        $this->isWorking = false;
        // Konsumiere pendingNavigate nach Abschluss des Loops
        if (is_string($this->pendingNavigate) && $this->pendingNavigate !== '') {
            $url = $this->pendingNavigate;
            $this->pendingNavigate = null;
            $this->redirect($url, navigate: true);
        }
    }

    public function confirmAndRun(string $intent, array $slots = []): void
    {
        $slots['confirmed'] = true;
        $this->executeIntent($intent, $slots, true);
        if ($this->lastUserText !== '') {
            $this->assistantFollowUp($this->lastUserText);
        }
    }

    protected function executeIntent(string $intent, array $slots = [], bool $override = false): array
    {
        $gateway = new CommandGateway(new IntentMatcher(), new CommandDispatcher());
        $matched = [ 'command' => $this->findCommandByKey($intent), 'slots' => $slots ];
        $result = $gateway->executeMatched($matched, auth()->user(), $override || $this->forceExecute);
        $this->feed[] = ['role' => 'tool', 'type' => 'result', 'data' => $result];
        $this->saveEvent('tool_call_end', ['intent' => $intent, 'slots' => $slots, 'result' => $result]);
        $this->saveMessage('tool', json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ['kind' => 'result']);
        if (($result['ok'] ?? false) && !empty($result['navigate'])) {
            $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => 'Navigiere …']];
            $this->saveMessage('assistant', json_encode(['navigate' => $result['navigate']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ['kind' => 'navigate']);
            $this->redirect($result['navigate'], navigate: true);
        }
        return $result;
    }

    protected function assistantFollowUp(string $userText): void
    {
        $context = [];
        foreach (array_reverse($this->feed, true) as $b) {
            if (($b['type'] ?? '') === 'result' && !empty($b['data']['data'])) {
                $context[] = $b['data']['data'];
            }
            if (count($context) >= 3) break;
        }
        $planner = new LlmPlanner();
        $resp = $planner->assistantRespond($userText, $context);
        if (($resp['ok'] ?? false) && !empty($resp['text'])) {
            $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => $resp['text']]];
            $this->saveMessage('assistant', $resp['text'], ['kind' => 'message']);
        } elseif (!($resp['ok'] ?? true)) {
            $msg = ($resp['message'] ?? 'LLM Fehler');
            $detail = is_string($resp['detail'] ?? null) ? $resp['detail'] : json_encode($resp['detail'] ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $text = $detail ? ($msg.': '.$detail) : $msg;
            $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => $text]];
            $this->saveMessage('assistant', $text, ['kind' => 'error']);
        }
    }

    protected function isAutoAllowed(string $intent): bool
    {
        $i = mb_strtolower($intent);
        foreach (['list', 'show', 'get', 'open', 'query'] as $kw) {
            if (str_contains($i, $kw)) return true;
        }
        if (str_ends_with($i, '.list_my_tasks')) return true;
        if (str_ends_with($i, '.query')) return true;
        return false;
    }

    protected function intentWeight(string $intent): float
    {
        $i = mb_strtolower($intent);
        // höhere Werte = früher ausführen
        if (str_contains($i, 'list') || str_contains($i, 'get') || str_contains($i, 'query')) return 2.1; // Query minimal bevorzugen
        if (str_contains($i, 'open') || str_contains($i, 'show')) return 2.0;
        return 1.0;
    }

    protected function debugLog(array $data): void
    {
        if (!config('agent.debug_log')) return;
        $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => [
            'text' => '[debug] '.json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        ]];
    }

    protected function inferModelFromShowIntent(string $intent): ?string
    {
        // z. B. planner.projects.show -> planner.projects
        $i = mb_strtolower($intent);
        if (!str_ends_with($i, '.show')) return null;
        $base = mb_substr($i, 0, -5);
        // base sollte module.entity sein
        return $base;
    }

    protected function extractBestQueryFromSlots(array $slots): ?string
    {
        foreach (['name','title','q'] as $k) {
            $v = (string)($slots[$k] ?? '');
            if ($v !== '') return $v;
        }
        // Falls Route-Parameter als String mitgegeben wurde (z. B. plannerProject => 'webviduell')
        foreach ($slots as $v) {
            if (is_string($v) && $v !== '') return $v;
        }
        return null;
    }

    protected function extractTitleFromText(string $text): ?string
    {
        $t = trim($text);
        if ($t === '') return null;
        // Heuristik: alles nach Komma/“Task/aufgabe/erstelle/neue” als Titel nehmen
        foreach (['aufgabe', 'task', 'erstelle', 'neue', 'neu'] as $kw) {
            $pos = mb_stripos($t, $kw);
            if ($pos !== false) {
                $rest = trim(mb_substr($t, $pos + mb_strlen($kw)));
                $rest = ltrim($rest, ':,.- ');
                if ($rest !== '') return $rest;
            }
        }
        // Fallback: nach Komma teilen
        if (str_contains($t, ',')) {
            $parts = array_map('trim', explode(',', $t));
            $last = end($parts);
            if ($last !== '') return $last;
        }
        return $t;
    }

    protected function isQueryIntent(string $intent): bool
    {
        $i = mb_strtolower($intent);
        return str_contains($i, 'list') || str_contains($i, 'get') || str_contains($i, 'query');
    }

    protected function isOpenIntent(string $intent): bool
    {
        $i = mb_strtolower($intent);
        return str_contains($i, 'open') || str_contains($i, 'show');
    }

    protected function isWriteIntent(string $intent): bool
    {
        $i = mb_strtolower($intent);
        return str_contains($i, 'create') || str_contains($i, 'update') || str_contains($i, 'delete');
    }

    protected function formatItemsFull(array $items): string
    {
        $lines = [];
        foreach ($items as $it) {
            $label = $it['title'] ?? ($it['name'] ?? (isset($it['id']) ? ('#'.$it['id']) : ''));
            $lines[] = '- '.$label;
        }
        return implode("\n", $lines);
    }

    protected function multiChainOpenProjectByName(string $name): void
    {
        $list = $this->executeIntent('planner.query', ['model' => 'planner.projects'], true);
        $projects = $list['data']['items'] ?? [];
        if (empty($projects)) {
            $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => 'Keine Projekte gefunden.']];
            return;
        }
        $needle = mb_strtolower($name);
        $matches = [];
        foreach ($projects as $p) {
            $pname = mb_strtolower($p['name'] ?? '');
            if ($pname === $needle || str_contains($pname, $needle)) {
                $matches[] = $p;
            }
        }
        if (count($matches) === 1) {
            $this->executeIntent('planner.open', ['model' => 'planner.projects', 'id' => $matches[0]['id']], true);
            return;
        }
        if (count($matches) > 1) {
            $choices = array_slice($matches, 0, 6);
            $this->feed[] = ['role' => 'assistant', 'type' => 'choices', 'data' => [
                'title' => 'Mehrere Projekte gefunden – bitte wählen:',
                'items' => array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $choices),
            ]];
            return;
        }
        $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => 'Kein passendes Projekt gefunden.']];
    }

    public function openProjectById(int $id): void
    {
        $this->executeIntent('planner.open', ['model' => 'planner.projects', 'id' => $id], true);
    }

    public function render()
    {
        if ($this->chatId) {
            $chat = CoreChat::find($this->chatId);
            if ($chat) {
                $this->totalTokensIn = (int) $chat->total_tokens_in;
                $this->totalTokensOut = (int) $chat->total_tokens_out;
            }
        }
        // simple count of active chats for user
        if (auth()->check()) {
            $this->activeChatsCount = CoreChat::where('user_id', auth()->id())
                ->where('status', 'active')
                ->count();
            $this->recentChats = CoreChat::where('user_id', auth()->id())
                ->where('status', 'active')
                ->latest('updated_at')
                ->limit(5)
                ->get(['id','title'])
                ->map(fn($c) => ['id' => $c->id, 'title' => $c->title ?: ('Chat #'.$c->id)])
                ->toArray();
        }
        return view('platform::livewire.cursor-sidebar');
    }

    protected function ensureChat(): void
    {
        if ($this->chatId) return;
        $user = auth()->user();
        $chat = CoreChat::create([
            'user_id' => $user?->id,
            'team_id' => $user?->currentTeam?->id,
            'title' => null,
            'total_tokens_in' => 0,
            'total_tokens_out' => 0,
            'status' => 'active',
        ]);
        $this->chatId = $chat->id;
        session(['core_chat_id' => $this->chatId]);
    }

    protected function saveMessage(string $role, string $content, array $meta = []): void
    {
        if (!$this->chatId) return;
        CoreChatMessage::create([
            'core_chat_id' => $this->chatId,
            'role' => $role,
            'content' => $content,
            'meta' => $meta,
            'tokens_in' => 0,
            'tokens_out' => 0,
        ]);
        CoreChat::where('id', $this->chatId)->update(['updated_at' => now()]);
    }

    protected function saveEvent(string $type, array $payload = []): void
    {
        if (!$this->chatId) return;
        CoreChatEvent::create([
            'core_chat_id' => $this->chatId,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    protected function findCommandByKey(string $key): array
    {
        foreach (\Platform\Core\Registry\CommandRegistry::all() as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }

    public function switchChat(int $id): void
    {
        $chat = CoreChat::find($id);
        if (!$chat) return;
        $this->chatId = $chat->id;
        session(['core_chat_id' => $this->chatId]);
        $this->loadFeedFromChat();
    }

    public function newChat(): void
    {
        $this->chatId = null;
        $this->ensureChat();
        $this->feed = [];
        $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => 'Neuer Chat gestartet.']];
    }

    protected function loadFeedFromChat(): void
    {
        $this->feed = [];
        if (!$this->chatId) return;
        // gesamten Verlauf laden (bei Bedarf später paginieren)
        $messages = CoreChatMessage::where('core_chat_id', $this->chatId)
            ->orderBy('id')
            ->get();
        foreach ($messages as $m) {
            $meta = (array) ($m->meta ?? []);
            if ($m->role === 'user') {
                $this->feed[] = ['role' => 'user', 'text' => $m->content];
                continue;
            }
            if ($m->role === 'assistant') {
                if (($meta['kind'] ?? '') === 'plan') {
                    $data = json_decode($m->content, true);
                    $plan = $data['plan'] ?? [];
                    $this->feed[] = ['role' => 'assistant', 'type' => 'plan', 'data' => $plan];
                } else {
                    $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => $m->content]];
                }
                continue;
            }
            if ($m->role === 'tool') {
                $res = json_decode($m->content, true) ?: [];
                $this->feed[] = ['role' => 'tool', 'type' => 'result', 'data' => $res];
                continue;
            }
        }
    }
}


