<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\CoreChat;
use Illuminate\Support\Facades\Auth;

class Terminal extends Component
{
    public $chats = [];
    public $activeChatId = null;
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
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        // Set first chat as active if none selected
        if (empty($this->activeChatId) && !empty($this->chats)) {
            $this->activeChatId = $this->chats[0]['id'];
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

        $this->loadChats();
        $this->activeChatId = $chat->id;
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
    }

    public function render()
    {
        return view('platform::livewire.terminal');
    }
}
