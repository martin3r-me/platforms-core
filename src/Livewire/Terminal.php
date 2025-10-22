<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Services\OpenAiService;
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
                    return [
                        'role' => $message->role,
                        'content' => $message->content,
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
        foreach ($toolCalls as $toolCall) {
            $this->currentTool = $toolCall['function']['name'] ?? 'unknown';
            $this->progressText = "Tool '{$this->currentTool}' wird ausgeführt...";
            $this->dispatch('terminal-progress', ['text' => $this->progressText]);
            sleep(1);
            CoreChatMessage::create([
                'core_chat_id' => $this->activeChatId,
                'thread_id' => $this->activeThreadId,
                'role' => 'tool',
                'content' => "Tool '{$this->currentTool}' erfolgreich ausgeführt.",
                'tokens_in' => 0,
            ]);
        }
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
        
        CoreChatMessage::create([
            'core_chat_id' => $this->activeChatId,
            'thread_id' => $this->activeThreadId,
            'role' => 'assistant',
            'content' => 'Entschuldigung, es gab einen Fehler: ' . $e->getMessage(),
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


    public function render()
    {
        return view('platform::livewire.terminal');
    }
}
