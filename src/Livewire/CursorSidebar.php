<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\Event;
// Command-Services entfernt - Sidebar soll leer sein
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Models\CoreChatEvent;
use Platform\Core\Services\IntelligentAgent;

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
    public array $agentActivities = [];
    public int $currentStep = 0;
    public int $totalSteps = 0;
    public bool $showActivityStream = false;


    #[On('cursor-sidebar-toggle')]
    public function toggle(): void
    {
        $this->open = !$this->open;
        if ($this->open) {
            $this->ensureChat();
        }
    }
    
    public function mount(): void
    {
        // Laravel Event Listener registrieren
        Event::listen('agent.activity.update', function($data) {
            // Update existing activity or add new one
            $this->agentActivities[] = $data;
            $this->currentStep++;
            $this->showActivityStream = true;
            
            // Force Livewire re-render
            $this->dispatch('$refresh');
        });
        
        Event::listen('agent.activity.complete', function() {
            $this->showActivityStream = false;
            $this->agentActivities = [];
            $this->currentStep = 0;
            $this->isWorking = false;
            
            // Force Livewire re-render
            $this->dispatch('$refresh');
        });
        
        // Aktiven Chat aus Session wiederherstellen
        $sid = session('core_chat_id');
        if ($sid) {
            $this->chatId = (int) $sid;
            $this->loadFeedFromChat();
        }
    }

    public function send(): void
    {
        $text = trim($this->input);
        if (empty($text)) return;
        
        // SOFORT: Input clearen und User-Message speichern
        $this->input = '';
        $this->lastUserText = $text;
        $this->ensureChat();
        $this->saveMessage('user', $text, ['forceExecute' => $this->forceExecute]);
        
        // WICHTIG: Feed aus DB laden (nicht manuell hinzufügen!)
        $this->loadFeedFromChat();
        
        // SOFORT: Agent Activities zurücksetzen und erste Aktivität anzeigen
        $this->agentActivities = [];
        $this->currentStep = 0;
        $this->totalSteps = 0;
        $this->isWorking = true;
        $this->showActivityStream = true;
        
        // SOFORT: Erste Aktivität anzeigen
        $this->agentActivities[] = [
            'step' => 'Analysiere Anfrage...',
            'tool' => '',
            'status' => 'running',
            'message' => 'Verstehe was der User möchte',
            'duration' => 0,
            'icon' => '🔄',
            'timestamp' => now()->format('H:i:s')
        ];

            // IntelligentAgent verwenden für echte ChatGPT-Integration
            try {
                $agent = app(IntelligentAgent::class);
                $response = $agent->processMessage($text, $this->chatId);

                if ($response['ok']) {
                    // Nur in DB speichern, nicht in Feed hinzufügen
                    $this->saveMessage('assistant', $response['content']);
                } else {
                    // Nur in DB speichern, nicht in Feed hinzufügen
                    $this->saveMessage('assistant', 'Fehler: ' . $response['error']);
                }
            } catch (\Throwable $e) {
                // Nur in DB speichern, nicht in Feed hinzufügen
                $this->saveMessage('assistant', 'Fehler beim Verarbeiten: ' . $e->getMessage());
            }

            $this->isWorking = false;
            $this->showActivityStream = false;
            $this->agentActivities = [];
            
            // WICHTIG: Feed neu laden für Live-Updates
            $this->loadFeedFromChat();
    }

    public function newChat(): void
    {
        $this->chatId = null;
        $this->feed = [];
        session()->forget('core_chat_id');
        $this->ensureChat();
    }

    public function switchChat(int $chatId): void
    {
        $this->chatId = $chatId;
        session(['core_chat_id' => $chatId]);
        $this->loadFeedFromChat();
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