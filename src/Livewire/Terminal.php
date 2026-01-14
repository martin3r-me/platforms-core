<?php

namespace Platform\Core\Livewire;

use Livewire\Component;

/**
 * Terminal UI shell (no chat, no threads, no AI).
 *
 * The open/close state is controlled client-side (Alpine store + `toggle-terminal` event).
 * This component intentionally contains no persistence or messaging logic.
 */
class Terminal extends Component
{
    public function render()
    {
        return view('platform::livewire.terminal');
    }
}

