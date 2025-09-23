<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Platform\Core\Registry\CommandRegistry;

class LlmPlanner
{
    public function initialMessages(string $userText, array $history = []): array
    {
        $now = now();
        $system = "Du bist ein Assistent in einer Business-Plattform. Heute ist "
            . $now->translatedFormat('l, d.m.Y H:i') . " " . (config('app.timezone') ?: 'UTC') . ". "
            . "Nutze bereitgestellte Tools, um Nutzerbefehle auszuführen. Du kannst mehrere Tools in Folge verwenden, bis die Aufgabe vollständig erledigt ist. "
            . "Bei 'mehrere X anlegen' mache so viele separate create-Calls wie nötig. "
            . "Du kannst intelligent nachfragen, wenn Informationen fehlen oder unklar sind. "
            . "Antworte nicht frei, sondern benutze function-calling. Nach erfolgreichen Aktionen nur kurz bestätigen.";
        // Hinweis: Verfügbare Modelle (aus Registry), damit das LLM 'model' korrekt setzt
        try {
            $plannerModels = \Platform\Core\Schema\ModelSchemaRegistry::keysByPrefix('planner.');
            $crmModels = \Platform\Core\Schema\ModelSchemaRegistry::keysByPrefix('crm.');
            if (!empty($plannerModels)) {
                $system .= " Verfügbare Modelle (Planner): ".implode(', ', $plannerModels).".";
            }
            if (!empty($crmModels)) {
                $system .= " Verfügbare Modelle (CRM): ".implode(', ', $crmModels).".";
            }
            
            // Modell-Strukturen für besseres Verständnis
            $allModels = \Platform\Core\Schema\ModelSchemaRegistry::keys();
            foreach ($allModels as $modelKey) {
                $schema = \Platform\Core\Schema\ModelSchemaRegistry::get($modelKey);
                if (!empty($schema['fields'])) {
                    $system .= " {$modelKey} hat Felder: ".implode(', ', array_slice($schema['fields'], 0, 10)).".";
                }
                if (!empty($schema['relations'])) {
                    $relations = array_keys($schema['relations']);
                    $system .= " {$modelKey} hat Relations: ".implode(', ', $relations).".";
                }
            }
        } catch (\Throwable $e) {
            // Registry evtl. nicht geladen – ignorieren
        }
        $messages = [ ['role' => 'system', 'content' => $system] ];
        // Erwartet: $history ist eine Liste aus ['role' => 'user'|'assistant', 'content' => string]
        foreach ($history as $h) {
            $r = $h['role'] ?? '';
            $c = $h['content'] ?? '';
            if (($r === 'user' || $r === 'assistant') && $c !== '') {
                $messages[] = ['role' => $r, 'content' => $c];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userText];
        return $messages;
    }

    public function step(array $messages, string $userText = ''): array
    {
        $apiKey = env('OPENAI_API_KEY');
        $base   = rtrim(env('OPENAI_BASE', 'https://api.openai.com/v1'), '/');
        $model  = env('OPENAI_MODEL', 'gpt-4o');
        if (!$apiKey) {
            return ['ok' => false, 'message' => 'OPENAI_API_KEY fehlt'];
        }

        $tools = $this->buildToolsFromRegistry($userText);

        try {
            $resp = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->post($base . '/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                    'temperature' => 0.1,
                    'presence_penalty' => 0,
                    'frequency_penalty' => 0,
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'LLM Anfrage fehlgeschlagen', 'detail' => $e->getMessage()];
        }
        if ($resp->failed()) {
            $json = $resp->json();
            return ['ok' => false, 'message' => 'LLM Fehler', 'detail' => $json ?: $resp->body()];
        }
        $data = $resp->json();
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $toolCalls = $message['tool_calls'] ?? [];
        if (is_array($toolCalls) && count($toolCalls) > 0) {
            $calls = [];
            foreach ($toolCalls as $tc) {
                $toolName = $tc['function']['name'] ?? null;
                $intent = $toolName ? CommandRegistry::resolveKeyFromToolName($toolName) : null;
                $argsJson = $tc['function']['arguments'] ?? '{}';
                $slots = json_decode($argsJson, true) ?: [];
                $calls[] = [
                    'intent' => $intent,
                    'slots' => $slots,
                    'tool_call_id' => $tc['id'] ?? null,
                ];
            }
            return [
                'ok' => true,
                'type' => 'tools',
                'calls' => $calls,
                'raw' => $message,
            ];
        }
        $assistant = $message['content'] ?? '';
        return [
            'ok' => true,
            'type' => 'assistant',
            'text' => $assistant,
            'raw' => $message,
        ];
    }

    public function plan(string $userText): array
    {
        $apiKey = env('OPENAI_API_KEY');
        $base   = rtrim(env('OPENAI_BASE', 'https://api.openai.com/v1'), '/');
        $model  = env('OPENAI_MODEL', 'gpt-4o');
        if (!$apiKey) {
            return ['ok' => false, 'message' => 'OPENAI_API_KEY fehlt'];
        }

        $tools = $this->buildToolsFromRegistry($userText);

        $system = "Du bist ein Assistent in einer Business-Plattform. Nutze bereitgestellte Tools, um Nutzerbefehle auszuführen. Du kannst mehrere Tools in Folge verwenden, bis die Aufgabe vollständig erledigt ist. Bei 'mehrere X anlegen' mache so viele separate create-Calls wie nötig. Antworte nicht frei, sondern benutze function-calling. Nach erfolgreichen Aktionen nur kurz bestätigen.";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userText],
            ],
            'tools' => $tools,
            'tool_choice' => 'auto',
        ];

        try {
            $resp = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->post($base . '/chat/completions', array_merge($payload, [
                    'temperature' => 0.1,
                    'presence_penalty' => 0,
                    'frequency_penalty' => 0,
                ]));
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'LLM Anfrage fehlgeschlagen', 'detail' => $e->getMessage()];
        }

        if ($resp->failed()) {
            return ['ok' => false, 'message' => 'LLM Fehler', 'detail' => $resp->json() ?: $resp->body()];
        }
        $data = $resp->json();
        $choice = $data['choices'][0] ?? [];
        $toolCall = $choice['message']['tool_calls'][0] ?? null;
        if (!$toolCall) {
            return ['ok' => false, 'message' => 'Kein Tool-Vorschlag'];
        }
        $toolName = $toolCall['function']['name'] ?? null;
        // Tool-Name zurück auf originalen Command-Key mappen
        $intent = $toolName ? CommandRegistry::resolveKeyFromToolName($toolName) : null;
        $argsJson = $toolCall['function']['arguments'] ?? '{}';
        $slots = json_decode($argsJson, true) ?: [];

        // Impact & confirm aus Registry lookup
        $meta = $this->findCommandMeta($intent);
        $impact = $meta['impact'] ?? 'low';
        $confirmRequired = $meta['confirmRequired'] ?? false;

        $confidence = $this->estimateConfidence($userText, (string) $intent, $slots);
        return [
            'ok' => true,
            'intent' => $intent,
            'slots' => $slots,
            'impact' => $impact,
            'confirmRequired' => $confirmRequired,
            'confidence' => $confidence,
            'rationale' => null,
        ];
    }

    public function assistantRespond(string $userText, array $context): array
    {
        $apiKey = env('OPENAI_API_KEY');
        $base   = rtrim(env('OPENAI_BASE', 'https://api.openai.com/v1'), '/');
        $model  = env('OPENAI_MODEL', 'gpt-4o');
        if (!$apiKey) {
            return ['ok' => false, 'message' => 'OPENAI_API_KEY fehlt'];
        }

        $system = "Du bist ein hilfreicher Assistent in einer Business-Plattform. Antworte kurz und präzise auf Deutsch. Nutze relevante Kontextdaten (JSON) für deine Antwort. Keine allgemeinen Ratgebertexte nach erfolgreichen Aktionen – bestätige kurz (z. B. 'Angelegt', 'Gefunden', 'Geöffnet'). Wenn Informationen fehlen, frage gezielt nach (needResolve).";

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userText],
            ['role' => 'system', 'content' => "Kontext:\n" . $contextJson],
        ];

        try {
            $resp = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(15)
                ->post($base . '/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.6,
                    'presence_penalty' => 0.2,
                    'frequency_penalty' => 0.2,
                ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'LLM Anfrage fehlgeschlagen', 'detail' => $e->getMessage()];
        }

        if ($resp->failed()) {
            return ['ok' => false, 'message' => 'LLM Fehler', 'detail' => $resp->json() ?: $resp->body()];
        }
        $data = $resp->json();
        $choice = $data['choices'][0] ?? [];
        $assistant = $choice['message']['content'] ?? '';
        return ['ok' => true, 'text' => $assistant];
    }

    protected function buildToolsFromRegistry(string $userText = ''): array
    {
        $schemas = CommandRegistry::exportFunctionSchemas();
        // Konfigurierbare Filter-/Ranking-Policy anwenden
        $policy = new ToolFilterPolicy();
        $filtered = $policy->filter($schemas, $userText);
        $tools = [];
        foreach ($filtered as $s) {
            $tools[] = [
                'type' => 'function',
                'function' => $s,
            ];
        }
        return $tools;
    }

    protected function findCommandMeta(?string $key): array
    {
        if (!$key) return [];
        foreach (CommandRegistry::all() as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }

    protected function estimateConfidence(string $userText, string $intent, array $slots): float
    {
        $user = mb_strtolower($userText);
        $intentLower = mb_strtolower($intent);
        $score = 0.5;
        $moduleKey = strstr($intentLower, '.', true) ?: $intentLower;
        if ($moduleKey && str_contains($user, $moduleKey)) {
            $score += 0.3;
        }
        foreach (['öffne', 'open', 'dashboard', 'projekt', 'ticket', 'okr'] as $kw) {
            if (str_contains($user, $kw)) { $score += 0.1; break; }
        }
        foreach (array_keys($slots) as $slotName) {
            if (str_contains($user, mb_strtolower($slotName))) { $score += 0.05; }
        }
        if ($score < 0) $score = 0.0;
        if ($score > 1) $score = 1.0;
        return $score;
    }
}


