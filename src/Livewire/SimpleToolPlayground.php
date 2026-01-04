<?php

namespace Platform\Core\Livewire;

use Livewire\Component;

class SimpleToolPlayground extends Component
{
    public $message = '';
    // Livewire hält hier bewusst keinen Streaming-State.
    // Streaming passiert client-side (Fetch + ReadableStream) und wird direkt im Chat gerendert.

    public function mount()
    {
        // Initialisierung
    }

    public function render()
    {
        return view('platform::livewire.simple-tool-playground')
            ->layout('platform::layouts.app');
    }
}

