<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Services\LlmPlanner;
use Platform\Core\Services\CommandDispatcher;
use Platform\Core\Services\CommandGateway;
use Platform\Core\Services\IntentMatcher;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Models\CoreChatEvent;

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
        $this->feed[] = ['role' => 'user', 'text' => $text];
        $this->lastUserText = $text;
        $this->ensureChat();
        $this->saveMessage('user', $text, ['forceExecute' => $this->forceExecute]);

        // Iterative Tool-Loop: LLM darf mehrere Tools nacheinander wählen
        $planner = new LlmPlanner();
        $messages = $planner->initialMessages($text);
        $maxSteps = 4;
        for ($i = 0; $i < $maxSteps; $i++) {
            $step = $planner->step($messages, $text);
            // Fehler oder reine Assistant-Antwort → anzeigen und abbrechen
            if (!($step['ok'] ?? false)) {
                $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => ($step['message'] ?? 'Fehler')]];
                $this->saveMessage('assistant', ($step['message'] ?? 'Fehler'), ['kind' => 'message']);
                break;
            }
            if (($step['type'] ?? '') === 'assistant') {
                $textOut = trim($step['text'] ?? '');
                if ($textOut !== '') {
                    $this->feed[] = ['role' => 'assistant', 'type' => 'message', 'data' => ['text' => $textOut]];
                    $this->saveMessage('assistant', $textOut, ['kind' => 'message']);
                }
                break;
            }
            // Tool-Aufruf
            $intent = $step['intent'] ?? '';
            $slots  = $step['slots'] ?? [];
            $autoAllowed = $this->isAutoAllowed($intent);
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
            $result = $this->executeIntent($intent, $slots, true);
            if (($result['ok'] ?? false) && !empty($result['navigate'] ?? null)) {
                // Sofortige Navigation
                return;
            }
            // Tool-Resultat in den LLM-Kontext rückführen
            $messages[] = [
                'role' => 'tool',
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                'name' => $intent,
            ];
        }

        $this->input = '';
    }

    public function confirmAndRun(string $intent, array $slots = []): void
    {
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
            // Direkt navigieren; Sidebar schließt sich durch Seitenwechsel
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
        foreach (['list', 'show', 'get', 'open'] as $kw) {
            if (str_contains($i, $kw)) return true;
        }
        if ($i === 'planner.list_my_tasks') return true;
        return false;
    }

    protected function multiChainOpenProjectByName(string $name): void
    {
        $list = $this->executeIntent('planner.list_projects', [] , true);
        $projects = $list['data']['projects'] ?? [];
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
            $this->executeIntent('planner.open_project', ['id' => $matches[0]['id']], true);
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
        $this->executeIntent('planner.open_project', ['id' => $id], true);
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
        $messages = CoreChatMessage::where('core_chat_id', $this->chatId)
            ->orderBy('id')
            ->limit(50)
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


