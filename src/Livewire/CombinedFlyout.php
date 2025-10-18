<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\PlatformCore;

class CombinedFlyout extends Component
{
    public $userTeams;
    public $currentTeam;
    public $modules;

    public function mount()
    {
        $this->loadTeams();
        $this->loadModules();
    }

    public function loadTeams()
    {
        $this->currentTeam = Auth::user()?->currentTeam;
        $this->userTeams = Auth::user()?->teams()->take(4)->get() ?? collect();
    }

    public function loadModules()
    {
        $user = Auth::user();
        $team = method_exists($user, 'currentTeam') ? $user->currentTeam : null;
        $teamId = $team?->id;

        $modules = PlatformCore::getVisibleModules();

        $this->modules = collect($modules)->filter(function($module) use ($user, $team, $teamId) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $module['key'])->first();
            if (!$moduleModel) return false;

            $userAllowed = $user->modules()
                ->where('module_id', $moduleModel->id)
                ->wherePivot('team_id', $teamId)
                ->wherePivot('enabled', true)
                ->exists();
            $teamAllowed = $team
                ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                : false;

            return $userAllowed || $teamAllowed;
        })->take(4)->values();
    }

    public function switchTeam(int $teamId)
    {
        $user = Auth::user();
        if (!$user) { return; }
        
        $user->current_team_id = $teamId;
        $user->save();

        // Intelligent redirection logic
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
        return $this->redirect(route('platform.dashboard'));
    }

    public function render()
    {
        return view('platform::livewire.combined-flyout');
    }
}
