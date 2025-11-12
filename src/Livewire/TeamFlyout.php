<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamUserLastModule;
use Livewire\Attributes\On;

class TeamFlyout extends Component
{
    public $show = false;
    public $userTeams = [];
    public $groupedTeams = []; // Gruppierte Teams: Parent-Teams mit ihren Kindern
    public $personalTeams = []; // Persönliche Teams (personal_team = true)
    public $currentTeam;
    public $baseTeam; // Das ursprüngliche Team (Child)
    public $parentTeam; // Das Parent-Team (falls vorhanden)
    public $currentModule;
    public $isParentModule = false; // Ob wir in einem Parent-Modul sind

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
        if (!$user) {
            return;
        }

        // Basis-Team (das ursprünglich ausgewählte Team)
        $this->baseTeam = $user->currentTeamRelation;
        
        // Current Team (kann Root-Team sein, wenn in Parent-Modul)
        $this->currentTeam = $user->currentTeam;
        
        // Parent-Team ermitteln (wenn Basis-Team ein Child ist)
        $this->parentTeam = null;
        if ($this->baseTeam && $this->baseTeam->parent_team_id) {
            $this->parentTeam = $this->baseTeam->parentTeam;
        }
        
        // Alle Teams des Users holen
        $allTeams = $user->teams()->with('childTeams', 'parentTeam')->orderBy('name')->get() ?? collect();
        
        // Persönliche Teams trennen (personal_team = true)
        $personalTeams = $allTeams->filter(function($team) {
            return $team->personal_team === true;
        })->sortBy('name');
        
        // Organisations-Teams (nicht persönlich)
        $orgTeams = $allTeams->filter(function($team) {
            return $team->personal_team !== true;
        });
        
        // Teams gruppieren: Parent-Teams mit ihren Kindern (nur Organisations-Teams)
        $parentTeams = $orgTeams->filter(function($team) {
            return $team->parent_team_id === null; // Nur Root-Teams
        })->sortBy('name');
        
        $this->groupedTeams = $parentTeams->map(function($parentTeam) use ($orgTeams) {
            // Kind-Teams dieses Parent-Teams finden (nur die, die der User hat)
            $childTeams = $orgTeams->filter(function($team) use ($parentTeam) {
                return $team->parent_team_id === $parentTeam->id;
            })->sortBy('name');
            
            return [
                'parent' => $parentTeam,
                'children' => $childTeams->values()
            ];
        })->values();
        
        // Persönliche Teams speichern
        $this->personalTeams = $personalTeams->values();
        
        // Für Rückwärtskompatibilität behalten wir auch userTeams
        $this->userTeams = $allTeams;
    }

    public function loadCurrentModule()
    {
        $currentPath = request()->segment(1);
        
        if ($currentPath === 'dashboard' || empty($currentPath)) {
            $this->currentModule = 'Dashboard';
            $this->isParentModule = false;
        } else {
            $moduleModel = \Platform\Core\Models\Module::where('key', $currentPath)->first();
            if ($moduleModel) {
                $config = is_array($moduleModel->config) ? $moduleModel->config : json_decode($moduleModel->config, true);
                $this->currentModule = $config['title'] ?? ucfirst($currentPath);
                // Prüfe ob es ein Parent-Modul ist
                $this->isParentModule = $moduleModel->isRootScoped();
            } else {
                $this->currentModule = ucfirst($currentPath);
                $this->isParentModule = false;
            }
        }
    }

    public function switchTeam($teamId)
    {
        $user = Auth::user();
        if (!$user) return;

        // Aktuelles Modul für das alte Team speichern
        $oldTeamId = $user->current_team_id;
        $currentModuleKey = null;
        
        // Durch URL-Segmente iterieren und ersten gültigen Modul-Key finden
        // Ignoriere "livewire", "dashboard" und andere Nicht-Modul-Segmente
        $ignoreSegments = ['livewire', 'dashboard', 'api', 'storage', 'assets'];
        
        for ($i = 1; $i <= 5; $i++) {
            $segment = request()->segment($i);
            if (empty($segment)) {
                break;
            }
            
            // Überspringe ignorierte Segmente
            if (in_array(strtolower($segment), $ignoreSegments)) {
                continue;
            }
            
            // Prüfe ob es ein gültiges Modul ist
            $moduleModel = \Platform\Core\Models\Module::where('key', $segment)->first();
            if ($moduleModel) {
                $currentModuleKey = $segment;
                break;
            }
        }
        
        if ($oldTeamId && $currentModuleKey) {
            TeamUserLastModule::updateLastModule($user->id, $oldTeamId, $currentModuleKey);
        }

        // Team wechseln
        $user->current_team_id = $teamId;
        $user->save();

        $this->show = false;

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
