<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;

class Okr extends Component
{
    use WithTerminalContext;

    public function render()
    {
        return view('platform::livewire.terminal.okr');
    }
}
