<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\PlatformCore;
use Platform\Core\Models\Module;
use Livewire\Attributes\On;

class AdminFlyout extends Component
{
    public $show = false;
    public array $adminModules = [];
    public bool $isAdmin = false;
    public bool $isOwner = false;

    #[On('open-admin-flyout')]
    public function openFlyout()
    {
        $this->show = true;
        $this->loadAdminModules();
    }

    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $team = $user->currentTeam;
        if ($team) {
            $pivot = $user->teams()->where('team_id', $team->id)->first()?->pivot;
            $this->isAdmin = $pivot && in_array($pivot->role, ['owner', 'admin']);
            $this->isOwner = $pivot && $pivot->role === 'owner';
        }

        if ($this->isAdmin) {
            $this->loadAdminModules();
        }
    }

    public function loadAdminModules(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $baseTeam = $user->currentTeamRelation;
        if (!$baseTeam) {
            return;
        }

        $rootTeam = $baseTeam->getRootTeam();
        $allModules = PlatformCore::getVisibleModules();

        $this->adminModules = collect($allModules)
            ->filter(function ($module) use ($user, $baseTeam, $rootTeam) {
                if (($module['group'] ?? 'other') !== 'admin') {
                    return false;
                }

                $moduleModel = Module::where('key', $module['key'])->first();
                if (!$moduleModel) {
                    return false;
                }

                $checkTeam = $moduleModel->isRootScoped() ? $rootTeam : $baseTeam;

                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $checkTeam->id)
                    ->wherePivot('enabled', true)
                    ->exists();

                $teamAllowed = $checkTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();

                return $userAllowed || $teamAllowed;
            })
            ->map(function ($module) {
                $icon = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                $routeName = $module['navigation']['route'] ?? null;
                $url = ($routeName && \Illuminate\Support\Facades\Route::has($routeName))
                    ? route($routeName)
                    : ($module['url'] ?? '/' . $module['key']);

                return [
                    'key' => $module['key'],
                    'title' => $module['title'] ?? $module['label'] ?? ucfirst($module['key']),
                    'icon' => $icon,
                    'url' => $url,
                ];
            })
            ->values()
            ->toArray();
    }

    public function render()
    {
        return view('platform::livewire.admin-flyout');
    }
}
