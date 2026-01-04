<?php

namespace Platform\Core\Livewire;

use Livewire\Component;

class SimpleToolPlayground extends Component
{
    public $message = '';
    public $messages = [];
    public $isStreaming = false;

    public function mount()
    {
        // Initialisierung
    }

    public function sendMessage()
    {
        if (empty(trim($this->message)) || $this->isStreaming) {
            return;
        }

        $userMessage = trim($this->message);
        $this->message = '';
        
        // Füge User-Message hinzu
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        $this->isStreaming = true;
        $this->dispatch('start-stream', message: $userMessage);
    }

    public function render()
    {
        return view('platform::livewire.simple-tool-playground')
            ->layout('platform::layouts.app');
    }
}

