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
    public $groupedModules = [];
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
        $this->loadCurrentModule();
    }

    public function loadModules()
    {
        $user = Auth::user();
        if (!$user) {
            $this->modules = collect();
            return;
        }

        $baseTeam = $user->currentTeamRelation; // Basis-Team (nicht dynamisch)
        if (!$baseTeam) {
            $this->modules = collect();
            return;
        }

        $rootTeam = $baseTeam->getRootTeam();
        $rootTeamId = $rootTeam->id;
        $baseTeamId = $baseTeam->id;

        // Hole alle sichtbaren Module
        $modules = PlatformCore::getVisibleModules();

        // Filtere Module nach Berechtigung
        $this->modules = collect($modules)->filter(function($module) use ($user, $baseTeam, $baseTeamId, $rootTeam, $rootTeamId) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $module['key'])->first();
            if (!$moduleModel) return false;

            // Für Parent-Module: Rechte aus Root-Team prüfen
            // Für Single-Module: Rechte aus aktuellem Team prüfen
            if ($moduleModel->isRootScoped()) {
                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $rootTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $rootTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            } else {
            $userAllowed = $user->modules()
                ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $baseTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $baseTeam->modules()
                    ->where('module_id', $moduleModel->id)
                ->wherePivot('enabled', true)
                ->exists();
            }

            return $userAllowed || $teamAllowed;
        })->sortBy(function($module) {
            return $module['title'] ?? $module['label'] ?? '';
        })->values();

        // Gruppierung aufbauen
        $groups = PlatformCore::getModuleGroups();
        $grouped = [];

        foreach ($this->modules as $module) {
            $groupKey = $module['group'] ?? 'other';
            $grouped[$groupKey][] = $module;
        }

        // Admin-Module werden im AdminFlyout angezeigt
        unset($grouped['admin']);

        // Nach Gruppen-Order sortieren
        uksort($grouped, function ($a, $b) use ($groups) {
            $orderA = $groups[$a]['order'] ?? 999;
            $orderB = $groups[$b]['order'] ?? 999;
            return $orderA <=> $orderB;
        });

        $this->groupedModules = collect($grouped)->map(function ($modules, $groupKey) use ($groups) {
            return [
                'label'   => $groups[$groupKey]['label'] ?? ucfirst($groupKey),
                'modules' => $modules,
            ];
        })->toArray();
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
