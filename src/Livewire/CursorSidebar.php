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

    public function mount(): void
    {
        // Aktiven Chat aus Session wiederherstellen
        $sid = session('core_chat_id');
        if ($sid) {
            $this->chatId = (int) $sid;
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

        $planner = new LlmPlanner();
        $plan = $planner->plan($text);
        $this->feed[] = ['role' => 'assistant', 'type' => 'plan', 'data' => $plan];
        $this->saveMessage('assistant', json_encode(['plan' => $plan], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ['kind' => 'plan']);

        if (($plan['ok'] ?? false) && ($plan['intent'] ?? null)) {
            $conf = (float)($plan['confidence'] ?? 0.0);
            $autoAllowed = $this->isAutoAllowed($plan['intent']);
            if ($this->forceExecute || $autoAllowed || (empty($plan['confirmRequired']) && $conf >= 0.8)) {
                $res = $this->executeIntent($plan['intent'], $plan['slots'] ?? [], $autoAllowed);
                if (!$res['ok'] && $plan['intent'] === 'planner.open_project' && !empty(($plan['slots'] ?? [])['name'])) {
                    $this->multiChainOpenProjectByName(($plan['slots'] ?? [])['name']);
                } else {
                    $this->assistantFollowUp($text);
                }
            } else {
                $this->feed[] = ['role' => 'assistant', 'type' => 'confirm', 'data' => [
                    'intent' => $plan['intent'],
                    'impact' => $plan['impact'] ?? 'medium',
                    'slots' => $plan['slots'] ?? [],
                    'confidence' => $conf,
                ]];
                $this->assistantFollowUp($text);
            }
        } else {
            $this->assistantFollowUp($text);
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
        if (($result['ok'] ?? false) && !empty($result['navigate'])) {
            $this->dispatch('cursor-sidebar-toggle'); // Sidebar einklappen
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
}


