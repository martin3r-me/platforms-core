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

        // Set first chat as active if none selected
        if (empty($this->activeChatId) && !empty($this->chats)) {
            $this->activeChatId = $this->chats[0]['id'];
            $this->loadActiveThread();
            $this->loadMessages();
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

        // Create first thread for the chat
        $thread = CoreChatThread::create([
            'core_chat_id' => $chat->id,
            'title' => 'Thread 1',
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->loadChats();
        $this->activeChatId = $chat->id;
        $this->activeThreadId = $thread->id;
    }

    public function deleteChat($chatId)
    {
        $chat = CoreChat::find($chatId);
        if ($chat && $chat->user_id === Auth::id()) {
            $chat->update(['status' => 'archived']);
            $this->loadChats();
            
            // If deleted chat was active, select first available
            if ($this->activeChatId === $chatId) {
                $this->activeChatId = !empty($this->chats) ? $this->chats[0]['id'] : null;
            }
        }
    }

    public function setActiveChat($chatId)
    {
        $this->activeChatId = $chatId;
        $this->loadActiveThread();
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

        // Close current thread if exists
        if ($this->activeThreadId) {
            $currentThread = CoreChatThread::find($this->activeThreadId);
            if ($currentThread) {
                $currentThread->close();
            }
        }

        // Create new thread
        $thread = CoreChatThread::create([
            'core_chat_id' => $chat->id,
            'title' => 'Thread ' . ($chat->threads()->count() + 1),
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->activeThreadId = $thread->id;
        $this->loadChats(); // Reload to get updated threads
    }

    public function setActiveThread($threadId)
    {
        $this->activeThreadId = $threadId;
        $this->loadMessages();
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

        // Generate idempotency key
        $this->idempotencyKey = 'chat_' . time() . '_' . uniqid();
        
        // 1. Sofortiges UI-Feedback (0-200ms)
        $this->isProcessing = true;
        $this->isStreaming = true;
        $this->canCancel = true;
        $this->progressText = 'Denke...';

        $userMessage = $this->messageInput;
        
        // Create user message
        CoreChatMessage::create([
            'core_chat_id' => $this->activeChatId,
            'thread_id' => $this->activeThreadId,
            'role' => 'user',
            'content' => $userMessage,
            'tokens_in' => strlen($userMessage),
        ]);

        // Clear input
        $this->messageInput = '';
        $this->loadMessages();

        // Start SSE streaming on client
        $streamUrl = route('core.ai.stream', ['thread' => $this->activeThreadId]);
        // Livewire v3: Payload als benannte Argumente dispatchen, damit es in event.detail ankommt
        $this->dispatch('ai-stream-start', url: $streamUrl);
    }

    public function getAiResponse()
    {
        try {
            $openAi = app(OpenAiService::class);
            
            // Get conversation history
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

            // Add system message if this is the first user message
            if (count($messages) === 1) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => 'Du bist ein hilfreicher AI-Assistent. Antworte kurz und präzise auf Deutsch.',
                ]);
            }

            // 2. Progress-Mikrotexte für bessere UX
            $this->progressText = 'Ressourcen prüfen...';
            $this->dispatch('terminal-progress', ['text' => $this->progressText]);

            // Get AI response with timeout handling
            $response = $openAi->chat($messages, 'gpt-3.5-turbo', [
                'max_tokens' => 1000,
                'temperature' => 0.7,
                'stream' => false,
            ]);

            // 3. Tool-Call-Handling
            if (!empty($response['tool_calls'])) {
                $this->handleToolCalls($response['tool_calls']);
                return;
            }

            // 4. Finale Antwort
            $this->progressText = 'Antwort wird formatiert...';
            $this->dispatch('terminal-progress', ['text' => $this->progressText]);
            
            // Create AI message
            CoreChatMessage::create([
                'core_chat_id' => $this->activeChatId,
                'thread_id' => $this->activeThreadId,
                'role' => 'assistant',
                'content' => $response['content'],
                'tokens_out' => $response['usage']['completion_tokens'] ?? 0,
            ]);

            // 5. Klares Ende
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
            
            // Simulate tool execution (in real implementation, execute actual tools)
            sleep(1); // Simulate processing time
            
            // Create tool result message
            CoreChatMessage::create([
                'core_chat_id' => $this->activeChatId,
                'thread_id' => $this->activeThreadId,
                'role' => 'tool',
                'content' => "Tool '{$this->currentTool}' erfolgreich ausgeführt.",
                'tokens_in' => 0,
            ]);
        }
        
        // Continue with AI response after tool execution
        $this->getAiResponse();
    }

    private function finishResponse()
    {
        $this->isProcessing = false;
        $this->isStreaming = false;
        $this->canCancel = false;
        $this->currentTool = null;
        $this->progressText = '';
        
        $this->loadMessages();
        $this->dispatch('terminal-complete');
    }

    private function handleError(\Exception $e)
    {
        $this->isProcessing = false;
        $this->isStreaming = false;
        $this->canCancel = false;
        $this->currentTool = null;
        $this->progressText = '';
        
        // Create error message
        CoreChatMessage::create([
            'core_chat_id' => $this->activeChatId,
            'thread_id' => $this->activeThreadId,
            'role' => 'assistant',
            'content' => 'Entschuldigung, es gab einen Fehler: ' . $e->getMessage(),
            'tokens_in' => 0,
        ]);

        $this->loadMessages();
        $this->dispatch('terminal-error', ['message' => $e->getMessage()]);
    }

    public function cancelRequest()
    {
        if (!$this->canCancel) return;
        
        $this->isProcessing = false;
        $this->isStreaming = false;
        $this->canCancel = false;
        $this->currentTool = null;
        $this->progressText = '';
        
        $this->dispatch('terminal-cancelled');
    }

    public function render()
    {
        return view('platform::livewire.terminal');
    }
}
