<?php

namespace Platform\Core\Livewire;

use Livewire\Component;

class ToolPlayground extends Component
{
    public function mount()
    {
        // Initialisierung falls nötig
    }

    public function render()
    {
        return view('platform::livewire.tool-playground')
            ->layout('platform::layouts.app');
    }
}

