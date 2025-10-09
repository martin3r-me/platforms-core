<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\TeamBillableUsage;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class Navbar extends Component
{
    public $monthlyTotal = 0;
    public $currentTeamName;

    public function mount()
    {
        if (!Auth::check()) {
            // User nicht eingeloggt – überspringen oder Defaults setzen
            $this->currentTeamName = null;
            $this->monthlyTotal = 0;
            return;
        }

        $user = Auth::user();

        $this->currentTeamName = $user->currentTeam->name ?? null;

        $team = $user->currentTeam;

        if ($team) {
            $startOfMonth = now()->startOfMonth()->toDateString();
            $endOfMonth = now()->endOfMonth()->toDateString();

            $this->monthlyTotal = TeamBillableUsage::where('team_id', $team->id)
                ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
                ->sum('total_cost');
        } else {
            $this->monthlyTotal = 0;
        }
    }

    public function render()
    {
        return view('core::livewire.navbar', [
            'monthlyTotal' => $this->monthlyTotal,
        ]);
    }
}