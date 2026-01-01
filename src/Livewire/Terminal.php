<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolOrchestrator;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Support\Facades\Auth;

class Terminal extends Component
{
    public $chats = [];
    public $activeChatId = null;
    public $activeThreadId = null;
    public $show = false;
    public $messageInput = '';
    public $messages = [];
    
    // UX State Management
    public $isProcessing = false;
    public $isStreaming = false;
    public $currentTool = null;
    public $progressText = '';
    public $canCancel = false;
    public $idempotencyKey = '';
    public $streamUrl = '';

    protected $listeners = [
        'refresh' => 'refreshComponent',
    ];

    public function refreshComponent()
    {
        $this->loadMessages();
        $this->dispatch('terminal-scroll');
    }

    public function mount()
    {
        $this->loadChats();
    }

    public function loadChats()
    {
        $user = Auth::user();
        if (!$user) return;

        $this->chats = CoreChat::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['threads' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        if (empty($this->activeChatId) && !empty($this->chats)) {
            $this->activeChatId = $this->chats[0]['id'];
            $this->loadActiveThread();
            $this->loadMessages();
            $this->dispatch('terminal-scroll');
        }
    }

    public function createNewChat()
    {
        $user = Auth::user();
        if (!$user) return;

        $chat = CoreChat::create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam?->id,
            'title' => 'Neuer Chat',
            'status' => 'active',
        ]);

        $thread = CoreChatThread::create([
            'core_chat_id' => $chat->id,
            'title' => 'Thread 1',
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->loadChats();
        $this->activeChatId = $chat->id;
        $this->activeThreadId = $thread->id;
        $this->dispatch('terminal-scroll');
    }

    public function deleteChat($chatId)
    {
        $chat = CoreChat::find($chatId);
        if ($chat && $this->isOwner($chat)) {
            $chat->update(['status' => 'archived']);
            $this->loadChats();
            if ($this->activeChatId === $chatId) {
                $this->activeChatId = !empty($this->chats) ? $this->chats[0]['id'] : null;
                $this->loadActiveThread();
                $this->loadMessages();
                $this->dispatch('terminal-scroll');
            }
        }
    }

    private function isOwner(CoreChat $chat): bool
    {
        return $chat->user_id === Auth::id();
    }

    public function setActiveChat($chatId)
    {
        $this->activeChatId = $chatId;
        $this->loadActiveThread();
        $this->loadMessages();
        $this->dispatch('terminal-scroll');
    }

    public function loadActiveThread()
    {
        if (!$this->activeChatId) return;

        $chat = CoreChat::find($this->activeChatId);
        if ($chat) {
            $activeThread = $chat->activeThread()->first();
            $this->activeThreadId = $activeThread ? $activeThread->id : null;
        }
    }

    public function createNewThread()
    {
        if (!$this->activeChatId) return;

        $chat = CoreChat::find($this->activeChatId);
        if (!$chat) return;

        if ($this->activeThreadId) {
            $currentThread = CoreChatThread::find($this->activeThreadId);
            if ($currentThread) {
                $currentThread->close();
            }
        }

        $thread = CoreChatThread::create([
            'core_chat_id' => $chat->id,
            'title' => 'Thread ' . ($chat->threads()->count() + 1),
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->activeThreadId = $thread->id;
        $this->loadChats();
        $this->dispatch('terminal-scroll');
    }

    public function setActiveThread($threadId)
    {
        $this->activeThreadId = $threadId;
        $this->loadMessages();
        $this->dispatch('terminal-scroll');
    }

    public function loadMessages()
    {
        if (!$this->activeThreadId) {
            $this->messages = [];
            return;
        }

        $this->messages = CoreChatMessage::where('thread_id', $this->activeThreadId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }

    public function sendMessage()
    {
        if (empty($this->messageInput) || !$this->activeThreadId || $this->isProcessing) {
            return;
        }

        $this->idempotencyKey = 'chat_' . time() . '_' . uniqid();
        
        $this->isProcessing = true;
        $this->isStreaming = true;
        $this->canCancel = true;
        $this->progressText = 'Denke...';

        $userMessage = $this->messageInput;
        
        $created = CoreChatMessage::create([
            'core_chat_id' => $this->activeChatId,
            'thread_id' => $this->activeThreadId,
            'role' => 'user',
            'content' => $userMessage,
            'tokens_in' => strlen($userMessage),
        ]);

        // Optimistisch in die aktuelle Liste einfügen, statt neu zu laden
        $this->messages[] = [
            'id' => $created->id,
            'role' => 'user',
            'content' => $userMessage,
            'thread_id' => $this->activeThreadId,
            'core_chat_id' => $this->activeChatId,
        ];

        $this->messageInput = '';
        $this->dispatch('terminal-scroll');

        // Assistant-Placeholder in DB anlegen und optimistisch in Liste pushen
        $assistant = CoreChatMessage::create([
            'core_chat_id' => $this->activeChatId,
            'thread_id' => $this->activeThreadId,
            'role' => 'assistant',
            'content' => '',
            'tokens_out' => 0,
            'meta' => ['is_streaming' => true],
        ]);

        $this->messages[] = [
            'id' => $assistant->id,
            'role' => 'assistant',
            'content' => '',
            'thread_id' => $this->activeThreadId,
            'core_chat_id' => $this->activeChatId,
        ];

        $currentRoute = request()->route()?->getName();
        $sourceModule = null;
        if (is_string($currentRoute) && str_contains($currentRoute, '.')) {
            $sourceModule = strstr($currentRoute, '.', true);
        }
        $this->streamUrl = route('core.ai.stream', [
            'thread' => $this->activeThreadId,
            'assistant' => $assistant->id,
            'source_route' => $currentRoute,
            'source_module' => $sourceModule,
            'source_url' => url()->current(),
        ]);
        $this->dispatch('ai-stream-start', url: $this->streamUrl);
    }

    public function refreshLastAssistant(): void
    {
        if (!$this->activeThreadId) return;
        $last = CoreChatMessage::where('thread_id', $this->activeThreadId)
            ->where('role', 'assistant')
            ->orderBy('created_at', 'desc')
            ->first();
        if (!$last) return;
        // Ersetze die letzte Assistant-Message im lokalen Array gezielt
        for ($i = count($this->messages) - 1; $i >= 0; $i--) {
            if (($this->messages[$i]['role'] ?? null) === 'assistant') {
                $this->messages[$i] = $last->toArray();
                break;
            }
        }
    }

    public function getAiResponse()
    {
        try {
            $openAi = app(OpenAiService::class);
            
            $messages = CoreChatMessage::where('thread_id', $this->activeThreadId)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function($message) {
                    $content = $message->content;
                    
                    // Wenn Tool-Message, versuche JSON zu parsen und Tool-Call-ID zu extrahieren
                    if ($message->role === 'tool') {
                        $parsed = json_decode($content, true);
                        if (is_array($parsed) && isset($parsed['tool_call_id'])) {
                            return [
                                'role' => 'tool',
                                'tool_call_id' => $parsed['tool_call_id'],
                                'content' => json_encode([
                                    'ok' => $parsed['success'] ?? true,
                                    'data' => $parsed['data'] ?? null,
                                    'error' => $parsed['error'] ?? null,
                                ], JSON_UNESCAPED_UNICODE),
                            ];
                        }
                    }
                    
                    return [
                        'role' => $message->role,
                        'content' => $content,
                    ];
                })
                ->toArray();

            if (count($messages) === 1) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => 'Du bist ein hilfreicher AI-Assistent. Antworte kurz und präzise auf Deutsch.',
                ]);
            }

            $this->progressText = 'Ressourcen prüfen...';
            $this->dispatch('terminal-progress', ['text' => $this->progressText]);

            $response = $openAi->chat($messages, 'gpt-3.5-turbo', [
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'stream' => false,
            ]);

            if (!empty($response['tool_calls'])) {
                $this->handleToolCalls($response['tool_calls']);
                return;
            }

            $this->progressText = 'Antwort wird formatiert...';
            $this->dispatch('terminal-progress', ['text' => $this->progressText]);
            
            CoreChatMessage::create([
                'core_chat_id' => $this->activeChatId,
                'thread_id' => $this->activeThreadId,
                'role' => 'assistant',
                'content' => $response['content'],
                'tokens_out' => $response['usage']['completion_tokens'] ?? 0,
            ]);

            $this->finishResponse();

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    private function handleToolCalls(array $toolCalls)
    {
        // WICHTIG: Nutze echte Tool-Execution wie CoreAiStreamController
        $registry = app(ToolRegistry::class);
        $executor = app(ToolExecutor::class);
        $orchestrator = app(ToolOrchestrator::class);
        $context = ToolContext::fromAuth();
        
        $toolResults = [];
        
        foreach ($toolCalls as $toolCall) {
            $openAiToolName = $toolCall['function']['name'] ?? null;
            $toolArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
            $toolCallId = $toolCall['id'] ?? null;
            
            if (!$openAiToolName) continue;
            
            // Tool-Name zurückmappen (von OpenAI-Format zu internem Format)
            // OpenAI: "planner_projects_create" -> Intern: "planner.projects.create"
            $internalToolName = str_replace('_', '.', $openAiToolName);
            
            $this->currentTool = $internalToolName;
            $this->progressText = "Tool '{$internalToolName}' wird ausgeführt...";
            $this->dispatch('terminal-progress', ['text' => $this->progressText]);
            
            try {
                // Nutze ToolOrchestrator für automatische Tool-Chains (wie CoreAiStreamController)
                $result = $orchestrator->executeWithDependencies(
                    $internalToolName,
                    $toolArguments,
                    $context,
                    maxDepth: 5,
                    planFirst: true
                );
                
                // Prüfe ob User-Input benötigt wird
                if ($result->success && isset($result->data['requires_user_input']) && $result->data['requires_user_input'] === true) {
                    // User-Input benötigt - speichere State und zeige User-Input-UI
                    CoreChatMessage::create([
                        'core_chat_id' => $this->activeChatId,
                        'thread_id' => $this->activeThreadId,
                        'role' => 'tool',
                        'content' => json_encode([
                            'tool_call_id' => $toolCallId,
                            'requires_user_input' => true,
                            'message' => $result->data['message'] ?? 'Bitte wähle aus der Liste aus.',
                            'data' => $result->data['dependency_tool_result'] ?? $result->data,
                            'next_tool' => $result->data['next_tool'] ?? $internalToolName,
                            'next_tool_args' => $result->data['next_tool_args'] ?? $toolArguments,
                        ], JSON_UNESCAPED_UNICODE),
                        'tokens_in' => 0,
                    ]);
                    
                    // TODO: User-Input-UI anzeigen (kann später implementiert werden)
                    // Für jetzt: Verwende aktuelles Team oder ersten Eintrag
                    $this->progressText = 'Warte auf User-Input...';
                    $this->dispatch('terminal-progress', ['text' => $this->progressText]);
                    
                    // Fallback: Verwende aktuelles Team oder ersten Eintrag
                    $userInput = null;
                    if (isset($result->data['dependency_tool_result']['teams'])) {
                        $teams = $result->data['dependency_tool_result']['teams'];
                        if (isset($result->data['current_team_id'])) {
                            $userInput = $result->data['current_team_id'];
                        } elseif (count($teams) === 1) {
                            $userInput = $teams[0]['id'] ?? null;
                        }
                    }
                    
                    if ($userInput) {
                        // Automatisch mit User-Input fortfahren
                        $nextTool = $result->data['next_tool'] ?? $internalToolName;
                        $nextToolArgs = $result->data['next_tool_args'] ?? $toolArguments;
                        $nextToolArgs['team_id'] = $userInput;
                        
                        // Führe Tool erneut aus mit User-Input
                        $result = $orchestrator->executeWithDependencies(
                            $nextTool,
                            $nextToolArgs,
                            $context,
                            maxDepth: 5,
                            planFirst: true
                        );
                    } else {
                        // Kein automatischer Fallback möglich - User muss eingreifen
                        $this->loadMessages();
                        $this->dispatch('terminal-scroll');
                        return;
                    }
                }
                
                // Tool-Result für OpenAI-Format vorbereiten
                $resultArray = $result->toArray();
                $toolResults[] = [
                    'tool_call_id' => $toolCallId,
                    'role' => 'tool',
                    'content' => json_encode($resultArray, JSON_UNESCAPED_UNICODE),
                ];
                
                // Speichere Tool-Result in DB
                CoreChatMessage::create([
                    'core_chat_id' => $this->activeChatId,
                    'thread_id' => $this->activeThreadId,
                    'role' => 'tool',
                    'content' => json_encode([
                        'tool_call_id' => $toolCallId,
                        'tool' => $internalToolName,
                        'success' => $result->success,
                        'data' => $result->data,
                        'error' => $result->error,
                    ], JSON_UNESCAPED_UNICODE),
                    'tokens_in' => 0,
                ]);
                
            } catch (\Throwable $e) {
                // Fehler beim Tool-Execution
                $errorResult = [
                    'ok' => false,
                    'error' => [
                        'code' => 'EXECUTION_ERROR',
                        'message' => $e->getMessage()
                    ]
                ];
                
                $toolResults[] = [
                    'tool_call_id' => $toolCallId,
                    'role' => 'tool',
                    'content' => json_encode($errorResult, JSON_UNESCAPED_UNICODE),
                ];
                
                CoreChatMessage::create([
                    'core_chat_id' => $this->activeChatId,
                    'thread_id' => $this->activeThreadId,
                    'role' => 'tool',
                    'content' => json_encode([
                        'tool_call_id' => $toolCallId,
                        'tool' => $internalToolName,
                        'error' => $e->getMessage(),
                    ], JSON_UNESCAPED_UNICODE),
                    'tokens_in' => 0,
                ]);
            }
        }
        
        // Lade Messages neu (inkl. Tool-Results)
        $this->loadMessages();
        
        // Rufe OpenAI erneut auf mit Tool-Results (Multi-Step)
        $this->getAiResponse();
    }

    private function finishResponse()
    {
        $this->isStreaming = false;
        $this->canCancel = false;
        $this->isProcessing = false;
        $this->currentTool = null;
        $this->progressText = '';
        
        // Keine DB-Neuladung hier – Streaming-Blase bleibt stehen und wird nicht ersetzt
        $this->dispatch('terminal-scroll');
        $this->dispatch('terminal-complete');
    }

    private function handleError(\Exception $e)
    {
        $this->isStreaming = false;
        $this->canCancel = false;
        $this->isProcessing = false;
        $this->currentTool = null;
        $this->progressText = '';
        
        $errorMessage = '❌ Fehler: ' . $e->getMessage() . "\n\n";
        $errorMessage .= "📋 Details:\n";
        $errorMessage .= "Datei: " . $e->getFile() . "\n";
        $errorMessage .= "Zeile: " . $e->getLine() . "\n";
        if ($e->getTraceAsString()) {
            $errorMessage .= "\n🔍 Stack Trace:\n" . substr($e->getTraceAsString(), 0, 1000);
        }
        
        CoreChatMessage::create([
            'core_chat_id' => $this->activeChatId,
            'thread_id' => $this->activeThreadId,
            'role' => 'assistant',
            'content' => $errorMessage,
            'tokens_in' => 0,
        ]);

        $this->loadMessages();
        $this->dispatch('terminal-scroll');
        $this->dispatch('terminal-error', ['message' => $e->getMessage()]);
    }

    public function cancelRequest()
    {
        if (!$this->canCancel) return;
        
        $this->isStreaming = false;
        $this->canCancel = false;
        $this->isProcessing = false;
        $this->currentTool = null;
        $this->progressText = '';
        
        $this->dispatch('terminal-cancelled');
    }

    public function addErrorMessage($message)
    {
        if (!$this->activeThreadId) return;
        
        CoreChatMessage::create([
            'core_chat_id' => $this->activeChatId,
            'thread_id' => $this->activeThreadId,
            'role' => 'assistant',
            'content' => $message,
            'tokens_in' => 0,
        ]);
        
        $this->loadMessages();
        $this->dispatch('terminal-scroll');
    }


    public function render()
    {
        return view('platform::livewire.terminal');
    }
}
