<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public $modules;
    public $currentTeam;
    public $teamMembers = [];

    public function mount()
    {
        $this->modules = PlatformCore::getModules();
        
        if (Auth::check()) {
            $this->currentTeam = Auth::user()->currentTeam;
            if ($this->currentTeam) {
                $this->teamMembers = $this->currentTeam->users()->get()->all();
            }
        }
    }

    public function render()
    {
        return view('platform::livewire.dashboard')->layout('platform::layouts.app');
    }
}