<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\PlatformCore;
use Livewire\Attributes\On;

class ModuleFlyout extends Component
{
    public $show = false;
    public $modules = [];
    public $currentModule;

    #[On('open-module-flyout')]
    public function openFlyout()
    {
        $this->show = true;
        $this->loadModules();
    }

    public function mount()
    {
        $this->loadModules();
    }

    public function loadModules()
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        $teamId = $team?->id;

        // Hole alle sichtbaren Module
        $modules = PlatformCore::getVisibleModules();

        // Filtere Module nach Berechtigung
        $this->modules = collect($modules)->filter(function($module) use ($user, $team, $teamId) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $module['key'])->first();
            if (!$moduleModel) return false;

            // User-Erlaubnis nur im aktuellen Team-Scope
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

        // Aktuelles Modul ermitteln
        $currentPath = request()->segment(1) ?? 'dashboard';
        $this->currentModule = $currentPath === 'dashboard' ? 'Dashboard' : ucfirst($currentPath);
    }

    public function openModal()
    {
        $this->show = false;
        $this->dispatch('open-modal-modules', tab: 'modules');
    }

    public function render()
    {
        return view('platform::livewire.module-flyout');
    }
}
