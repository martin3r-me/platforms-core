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
    
    // Context-Management
    public array $currentContext = [];
    public ?string $currentModel = null;
    public ?int $currentModelId = null;
    public ?string $currentSubject = null;
    public ?string $currentUrl = null;
    public bool $contextPanelOpen = false;
    public bool $includeContext = true;
    public bool $collapsed = true;


    #[On('cursor-sidebar-toggle')]
    public function toggle(): void
    {
        $this->open = !$this->open;
        $this->collapsed = !$this->collapsed;
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
            $this->isWorking = true;
            
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
        
        // Event-Listener für Context-Updates (Livewire Event)
        $this->listen('comms', function($context) {
            $this->currentContext = $context;
            $this->currentModel = $context['model'] ?? null;
            $this->currentModelId = $context['modelId'] ?? null;
            $this->currentSubject = $context['subject'] ?? null;
            $this->currentUrl = $context['url'] ?? null;
            
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
                
                // Context an Agent weiterleiten (nur wenn aktiviert)
                if ($this->includeContext) {
                    $contextText = $this->buildContextText();
                    if ($contextText) {
                        $text = "KONTEXT: {$contextText}\n\nANFRAGE: {$text}\n\nWICHTIG: Der Context ist nur ein Hinweis. Beantworte ALLE Fragen normal, auch wenn sie nicht mit dem Context zusammenhängen. Verwende den Context nur wenn er hilfreich ist.";
                    }
                }
                
                $response = $agent->processMessage($text, $this->chatId);

                if ($response['ok']) {
                    // Response-Struktur: ['ok' => true, 'data' => ...]
                    $data = $response['data'] ?? $response['message'] ?? 'Antwort erhalten';
                    
                    // Prüfe ob data ein Array ist (Tool-Response) oder String (Chat-Response)
                    if (is_array($data)) {
                        // Tool-Response: Formatiere Array zu String
                        $content = $this->formatToolResponse($data);
                    } else {
                        // Chat-Response: Direkt verwenden
                        $content = $data;
                    }
                    
                    $this->saveMessage('assistant', $content);
                } else {
                    // Nur in DB speichern, nicht in Feed hinzufügen
                    $this->saveMessage('assistant', 'Fehler: ' . ($response['error'] ?? 'Unbekannter Fehler'));
                }
            } catch (\Throwable $e) {
                // Nur in DB speichern, nicht in Feed hinzufügen
                $this->saveMessage('assistant', 'Fehler beim Verarbeiten: ' . $e->getMessage());
            }

            $this->isWorking = false;
            $this->showActivityStream = false;
            $this->agentActivities = [];
            
            // WICHTIG: Feed EINMAL neu laden nach Agent-Antwort
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
    
    /**
     * Formatiere Tool-Response Array zu String
     */
    protected function formatToolResponse(array $data): string
    {
        // Prüfe ob es eine Tool-Response ist
        if (isset($data['data']) && is_array($data['data'])) {
            $items = $data['data'];
            $count = $data['count'] ?? count($items);
            
            if (empty($items)) {
                return "Keine Daten gefunden.";
            }
            
            // Formatiere basierend auf Item-Typ
            $formatted = "Gefunden: {$count} Einträge\n\n";
            
            foreach ($items as $index => $item) {
                if ($index >= 10) { // Limit für bessere Lesbarkeit
                    $remaining = count($items) - 10;
                    $formatted .= "... und {$remaining} weitere Einträge\n";
                    break;
                }
                
                if (is_array($item)) {
                    // Formatiere Array-Item
                    $formatted .= $this->formatArrayItem($item, $index + 1);
                } else {
                    $formatted .= ($index + 1) . ". " . $item . "\n";
                }
            }
            
            return $formatted;
        }
        
        // Fallback: JSON-String
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Formatiere einzelnes Array-Item
     */
    protected function formatArrayItem(array $item, int $index): string
    {
        $formatted = "{$index}. ";
        
        // Priorisiere wichtige Felder
        $priorityFields = ['name', 'title', 'id', 'uuid', 'description'];
        $displayFields = [];
        
        foreach ($priorityFields as $field) {
            if (isset($item[$field])) {
                $displayFields[] = $field . ': ' . $item[$field];
            }
        }
        
        if (!empty($displayFields)) {
            $formatted .= implode(', ', $displayFields);
        } else {
            // Fallback: Erste paar Felder
            $fields = array_slice($item, 0, 3);
            $formatted .= implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($fields), $fields));
        }
        
        return $formatted . "\n";
    }
    
    /**
     * Baue Context-Text aus aktuellen Context-Daten
     */
    private function buildContextText(): string
    {
        if (empty($this->currentContext)) {
            return '';
        }
        
        $context = [];
        
        if ($this->currentModel) {
            $context[] = "Aktuelles Model: {$this->currentModel}";
        }
        
        if ($this->currentModelId) {
            $context[] = "Model ID: {$this->currentModelId}";
        }
        
        if ($this->currentSubject) {
            $context[] = "Betreff: {$this->currentSubject}";
        }
        
        if ($this->currentUrl) {
            $context[] = "URL: {$this->currentUrl}";
        }
        
        // Meta-Daten hinzufügen
        if (isset($this->currentContext['meta'])) {
            $meta = $this->currentContext['meta'];
            foreach ($meta as $key => $value) {
                if ($value) {
                    $context[] = ucfirst($key) . ": {$value}";
                }
            }
        }
        
        return implode(', ', $context);
    }
    
    /**
     * Context-Panel umschalten
     */
    public function toggleContextPanel(): void
    {
        $this->contextPanelOpen = !$this->contextPanelOpen;
    }
    
    /**
     * Context ein-/ausschalten
     */
    public function toggleContext(): void
    {
        $this->includeContext = !$this->includeContext;
    }
    
    /**
     * Context leeren
     */
    public function clearContext(): void
    {
        $this->currentContext = [];
        $this->currentModel = null;
        $this->currentModelId = null;
        $this->currentSubject = null;
        $this->currentUrl = null;
    }
}