<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;

class Dashboard extends Component
{
    public $modules;

    public function mount()
    {
        $this->modules = PlatformCore::getModules();
    }

    public function render()
    {
        return view('platform::livewire.dashboard')->layout('platform::layouts.app');
    }
}