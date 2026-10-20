<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Platform\Core\Models\TeamUserLastModule;

class ModalModules extends Component
{
    public $modalShow;
    public $modules;

    #[On('open-modal-modules')]
    public function openModalModules()
    {
        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;

        $user = Auth::user();
        $baseTeam = $user?->currentTeamRelation;
        if (!$baseTeam) {
            $this->modules = collect();
            return;
        }

        $modules = PlatformCore::getVisibleModules();

        // Ein gebündelter Zugriffs-Check für alle Module (vorher 2–4 Queries je Modul)
        $accessible = array_flip(\Platform\Core\Models\Module::accessibleKeys($user, $baseTeam));
        $this->modules = collect($modules)->filter(fn ($module) => isset($accessible[$module['key']]))->values();
    }

    public function switchTeam(int $teamId)
    {
        $user = Auth::user();
        if (!$user) { return; }

        $user->current_team_id = $teamId;
        $user->save();

        session(['switching_team' => true]);

        $this->modalShow = false;

        $lastModuleKey = TeamUserLastModule::getLastModule($user->id, $teamId);

        if ($lastModuleKey && $lastModuleKey !== 'dashboard') {
            $moduleModel = \Platform\Core\Models\Module::where('key', $lastModuleKey)->first();
            if ($moduleModel) {
                $team = $user->currentTeam;
                $teamAllowed = $team
                    ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                    : false;

                if ($teamAllowed) {
                    return $this->redirect('/' . $lastModuleKey);
                }
            }
        }

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

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('platform::livewire.modal-modules');
    }
}
