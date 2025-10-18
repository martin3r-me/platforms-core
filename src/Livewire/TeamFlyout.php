<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Livewire\Attributes\On;

class TeamFlyout extends Component
{
    public $show = false;
    public $userTeams = [];
    public $currentTeam;
    public $currentModule;

    #[On('open-team-flyout')]
    public function openFlyout()
    {
        $this->show = true;
        $this->loadTeams();
    }

    public function mount()
    {
        $this->loadTeams();
        $this->loadCurrentModule();
    }

    public function loadTeams()
    {
        $user = Auth::user();
        $this->currentTeam = $user?->currentTeam;
        $this->userTeams = $user?->teams()->take(4)->get() ?? collect();
    }

    public function loadCurrentModule()
    {
        $currentPath = request()->segment(1);
        
        if ($currentPath === 'dashboard' || empty($currentPath)) {
            $this->currentModule = 'Dashboard';
        } else {
            $moduleModel = \Platform\Core\Models\Module::where('key', $currentPath)->first();
            if ($moduleModel) {
                $config = is_array($moduleModel->config) ? $moduleModel->config : json_decode($moduleModel->config, true);
                $this->currentModule = $config['title'] ?? ucfirst($currentPath);
            } else {
                $this->currentModule = ucfirst($currentPath);
            }
        }
    }

    public function switchTeam($teamId)
    {
        $user = Auth::user();
        if (!$user) return;

        $user->current_team_id = $teamId;
        $user->save();

        $this->show = false;

        // Versuche auf der aktuellen Seite zu bleiben, wenn das neue Team Zugriff auf das Modul hat
        $currentUrl = request()->fullUrl();
        $moduleKey = request()->segment(1);

        if (is_string($moduleKey) && strlen($moduleKey) > 0) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $moduleKey)->first();
            if ($moduleModel) {
                $team = $user->currentTeam;
                $teamAllowed = $team
                    ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                    : false;

                if ($teamAllowed) {
                    return $this->redirect($currentUrl);
                }
            }
        }

        // Fallback: Plattform-Dashboard
        return $this->redirect(route('platform.dashboard'));
    }

    public function openModal()
    {
        $this->show = false;
        $this->dispatch('open-modal-modules', tab: 'modules');
    }

    public function render()
    {
        return view('platform::livewire.team-flyout');
    }
}
