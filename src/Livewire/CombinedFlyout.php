<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamUserLastModule;
use Platform\Core\PlatformCore;

class CombinedFlyout extends Component
{
    public $userTeams;
    public $currentTeam;
    public $modules;
    public $currentModule;

    public function mount()
    {
        $this->loadTeams();
        $this->loadModules();
        $this->loadCurrentModule();
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

    public function loadCurrentModule()
    {
        $currentPath = request()->segment(1);
        
        if ($currentPath === 'dashboard' || empty($currentPath)) {
            $this->currentModule = 'Dashboard';
        } else {
            $moduleModel = \Platform\Core\Models\Module::where('key', $currentPath)->first();
            if ($moduleModel) {
                // config ist bereits ein Array, nicht JSON
                $config = is_array($moduleModel->config) ? $moduleModel->config : json_decode($moduleModel->config, true);
                $this->currentModule = $config['title'] ?? ucfirst($currentPath);
            } else {
                $this->currentModule = ucfirst($currentPath);
            }
        }
    }

    public function switchTeam(int $teamId)
    {
        $user = Auth::user();
        if (!$user) { return; }
        
        // Aktuelles Modul für das alte Team speichern
        $oldTeamId = $user->current_team_id;
        
        // Zuerst versuchen: Vom Middleware gesetztes current_module
        $currentModuleKey = request()->attributes->get('current_module');
        
        // Fallback 1: Aus Session (für Livewire-Requests)
        if (!$currentModuleKey) {
            $currentModuleKey = session('current_module_key');
        }
        
        // Fallback 2: Aus URL-Segmenten extrahieren
        if (!$currentModuleKey) {
            $currentPath = request()->segment(1);
            if ($currentPath && $currentPath !== 'dashboard' && $currentPath !== 'livewire') {
                $moduleModel = \Platform\Core\Models\Module::where('key', $currentPath)->first();
                if ($moduleModel) {
                    $currentModuleKey = $currentPath;
                }
            }
        }
        
        if ($oldTeamId && $currentModuleKey) {
            TeamUserLastModule::updateLastModule($user->id, $oldTeamId, $currentModuleKey);
        }

        // Team wechseln
        $user->current_team_id = $teamId;
        $user->save();

        // Zuletzt verwendetes Modul für das neue Team laden
        $lastModuleKey = TeamUserLastModule::getLastModule($user->id, $teamId);
        
        if ($lastModuleKey) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $lastModuleKey)->first();
            if ($moduleModel) {
                $team = $user->currentTeam;
                $teamAllowed = $team
                    ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                    : false;

                if ($teamAllowed) {
                    // Zum zuletzt verwendeten Modul navigieren
                    return $this->redirect('/' . $lastModuleKey);
                }
            }
        }

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
