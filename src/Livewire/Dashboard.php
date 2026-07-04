<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Core\Models\TeamBillableUsage;
use Platform\Core\Models\TeamUserLastModule;
use Platform\Core\Models\Module;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public array $modules = [];
    public $currentTeam;
    public array $teamMembers = [];
    public float $monthlyTotal = 0.0;

    public function mount()
    {
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();
        $this->currentTeam = $user->currentTeam;

        if (!$this->currentTeam) {
            return;
        }

        $baseTeam = $user->currentTeamRelation;

        if ($baseTeam) {
            $lastModuleKey = TeamUserLastModule::getLastModule($user->id, $this->currentTeam->id);

            if ($lastModuleKey && $lastModuleKey !== 'dashboard') {
                $moduleModel = Module::where('key', $lastModuleKey)->first();
                if ($moduleModel && $moduleModel->hasAccess($user, $baseTeam)) {
                    $this->redirect('/' . $lastModuleKey, navigate: false);
                    return;
                }
            }
        }

        $this->teamMembers = $this->currentTeam->users()->get()->all();
        $this->modules = $this->loadAccessibleModules($user, $baseTeam);
        $this->loadMonthlyCosts();
    }

    /**
     * Liefert die im Code registrierten Module, gefiltert auf jene,
     * auf die der User im aktuellen Team Zugriff hat.
     */
    protected function loadAccessibleModules($user, $baseTeam): array
    {
        if (!$baseTeam) {
            return [];
        }

        $registered = PlatformCore::getVisibleModules();
        if (empty($registered)) {
            return [];
        }

        $keys = array_values(array_filter(array_map(fn ($m) => $m['key'] ?? null, $registered)));
        $modelsByKey = Module::whereIn('key', $keys)->get()->keyBy('key');

        return collect($registered)
            ->filter(function ($module) use ($modelsByKey, $user, $baseTeam) {
                $model = $modelsByKey->get($module['key'] ?? null);
                return $model && $model->hasAccess($user, $baseTeam);
            })
            ->sortBy(fn ($module) => $module['navigation']['order'] ?? 999)
            ->values()
            ->all();
    }

    protected function loadMonthlyCosts(): void
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $sum = TeamBillableUsage::where('team_id', $this->currentTeam->id)
            ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
            ->sum('total_cost');

        $this->monthlyTotal = is_numeric($sum) ? (float) $sum : 0.0;
    }

    public function render()
    {
        return view('platform::livewire.dashboard')->layout('platform::layouts.app');
    }
}
