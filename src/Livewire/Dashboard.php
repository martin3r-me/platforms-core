<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Core\Models\TeamBillableUsage;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public $modules;
    public $currentTeam;
    public $teamMembers = [];
    public $monthlyTotal = 0;

    public function mount()
    {
        $this->modules = PlatformCore::getModules();
        
        if (Auth::check()) {
            $this->currentTeam = Auth::user()->currentTeam;
            if ($this->currentTeam) {
                $this->teamMembers = $this->currentTeam->users()->get()->all();
                $this->loadMonthlyCosts();
            }
        }
    }

    protected function loadMonthlyCosts()
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        
        $sum = TeamBillableUsage::where('team_id', $this->currentTeam->id)
            ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
            ->sum('total_cost');
            
        // Sicherstellen, dass es ein Float ist
        if (is_numeric($sum)) {
            $this->monthlyTotal = (float) $sum;
        } else {
            $this->monthlyTotal = 0.0;
        }
    }

    public function render()
    {
        return view('core::livewire.dashboard')->layout('core::layouts.app');
    }
}