<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatThread;
use Illuminate\Support\Facades\Auth;

class Terminal extends Component
{
    public $chats = [];
    public $activeChatId = null;
    public $activeThreadId = null;
    public $show = false;

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
    }

    public function render()
    {
        return view('platform::livewire.terminal');
    }
}
