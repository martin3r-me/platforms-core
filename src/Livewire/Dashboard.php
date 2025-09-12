<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Core\Models\TeamBillableUsage;
use Platform\Core\Models\Team;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class Dashboard extends Component
{
    public $modules; // sichtbare Module (guard-basiert)
    public $currentTeam;
    public $monthlyTotal = 0;
    public $moduleCosts = [];
    public $teamMembers = [];
    public $usageStats = [];
    public $currentDate;
    public $currentDay;
    public $modulePricings = [];
    public $allowedModuleKeys = [];
    public $sortedModules = [];

    public function mount()
    {
        // Nur sichtbare Module für den aktuellen Guard
        $this->modules = PlatformCore::getVisibleModules();
        $this->currentDate = now()->format('d.m.Y');
        $this->currentDay = now()->format('l');

        // Pricing (derzeit nicht gerendert, aber vorbereitet)
        $this->modulePricings = $this->buildModulePricings();
        
        if (Auth::check()) {
            $this->currentTeam = Auth::user()->currentTeam;
            $this->allowedModuleKeys = $this->buildAllowedModuleKeys();
            $this->loadTeamData();
        } else {
            // Fallback-Sortierung nach Titel, wenn nicht eingeloggt
            $this->sortedModules = collect($this->modules)
                ->sortBy(function ($m) {
                    return $m['title'] ?? ($m['key'] ?? '');
                })
                ->values()
                ->all();
        }
    }

    protected function loadTeamData()
    {
        if (!$this->currentTeam) {
            $this->teamMembers = [];
            $this->monthlyTotal = 0.0;
            $this->sortedModules = collect($this->modules)->values()->all();
            return;
        }

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        
        $sum = TeamBillableUsage::where('team_id', $this->currentTeam->id)
            ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
            ->sum('total_cost');
        $this->monthlyTotal = is_numeric($sum) ? (float) $sum : 0.0;

        $this->moduleCosts = $this->getModuleCosts($startOfMonth, $endOfMonth);
        $this->teamMembers = $this->currentTeam->users()->get()->all();
        $this->usageStats = $this->getUsageStats($startOfMonth, $endOfMonth);

        // Module nach monatlicher Belastung absteigend sortieren
        $this->sortedModules = collect($this->modules)
            ->sortByDesc(function ($m) {
                $key = $m['key'] ?? null;
                return $this->moduleCosts[$key]['cost'] ?? 0.0;
            })
            ->values()
            ->all();
    }

    protected function buildAllowedModuleKeys(): array
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        if (!$user) {
            return [];
        }

        $keys = [];
        foreach ($this->modules as $module) {
            $key = $module['key'] ?? null;
            if (!$key) continue;
            $moduleModel = \Platform\Core\Models\Module::where('key', $key)->first();
            if (!$moduleModel) continue;

            $userAllowed = $user->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists();
            $teamAllowed = $team
                ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                : false;

            if ($userAllowed || $teamAllowed) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    protected function buildModulePricings(): array
    {
        $result = [];
        $today = now()->toDateString();

        foreach ($this->modules as $moduleKey => $module) {
            $billables = $module['billables'] ?? [];
            if (!is_array($billables) || empty($billables)) {
                continue;
            }

            $moduleItems = [];
            foreach ($billables as $billable) {
                if (isset($billable['active']) && !$billable['active']) {
                    continue;
                }
                $label = $billable['label'] ?? ($billable['type'] ?? 'Billable');
                $price = $this->getCurrentPricing((array)($billable['pricing'] ?? []), $today);
                $moduleItems[] = [
                    'label' => $label,
                    'type' => $billable['type'] ?? null,
                    'price' => $price !== null ? (float) $price : null,
                ];
            }

            if (!empty($moduleItems)) {
                $result[$module['key'] ?? $moduleKey] = $moduleItems;
            }
        }

        return $result;
    }

    protected function getCurrentPricing(array $pricingArr, string $date): ?float
    {
        foreach ($pricingArr as $price) {
            $start = $price['start_date'] ?? null;
            $end = $price['end_date'] ?? null;
            if (!$start) {
                continue;
            }
            if ($date >= $start && (empty($end) || $date <= $end)) {
                $value = $price['cost_per_day'] ?? null;
                return $value !== null ? (float) $value : null;
            }
        }
        return null;
    }

    protected function getModuleCosts($startDate, $endDate)
    {
        $costs = [];
        
        foreach ($this->modules as $module) {
            $key = $module['key'] ?? null;
            if (!$key) continue;

            $moduleCost = TeamBillableUsage::where('team_id', $this->currentTeam->id)
                ->whereBetween('usage_date', [$startDate, $endDate])
                ->where('billable_model', 'like', '%' . $key . '%')
                ->sum('total_cost');
                
            $moduleCost = is_numeric($moduleCost) ? (float) $moduleCost : 0.0;
            $costs[$key] = [
                'title' => $module['title'] ?? ucfirst($key),
                'cost' => $moduleCost,
                'icon' => $module['navigation']['icon'] ?? 'heroicon-o-cube'
            ];
        }
        
        return $costs;
    }

    protected function getUsageStats($startDate, $endDate)
    {
        $stats = [];
        
        foreach ($this->modules as $module) {
            $key = $module['key'] ?? null;
            if (!$key) continue;

            $usage = TeamBillableUsage::where('team_id', $this->currentTeam->id)
                ->whereBetween('usage_date', [$startDate, $endDate])
                ->where('billable_model', 'like', '%' . $key . '%')
                ->sum('count');
                
            $usage = (int) ($usage ?? 0);
            $stats[$key] = [
                'title' => $module['title'] ?? ucfirst($key),
                'usage' => $usage,
                'icon' => $module['navigation']['icon'] ?? 'heroicon-o-cube'
            ];
        }
        
        return $stats;
    }

    public function render()
    {
        return view('platform::livewire.dashboard')->layout('platform::layouts.app');
    }
}