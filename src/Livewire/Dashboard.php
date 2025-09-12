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
    public $modules;
    public $currentTeam;
    public $monthlyTotal = 0;
    public $moduleCosts = [];
    public $teamMembers = [];
    public $usageStats = [];
    public $currentDate;
    public $currentDay;
    public $modulePricings = [];

    public function mount()
    {
        $this->modules = PlatformCore::getModules();
        $this->currentDate = now()->format('d.m.Y');
        $this->currentDay = now()->format('l');

        // Pricing-Aufbereitung unabhängig von Login anzeigen (Registry basiert)
        $this->modulePricings = $this->buildModulePricings();
        
        if (Auth::check()) {
            $this->currentTeam = Auth::user()->currentTeam;
            $this->loadTeamData();
        }
    }

    protected function loadTeamData()
    {
        if (!$this->currentTeam) {
            $this->teamMembers = [];
            $this->monthlyTotal = 0.0;
            return;
        }

        // Monatliche Gesamtkosten
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        
        $sum = TeamBillableUsage::where('team_id', $this->currentTeam->id)
            ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
            ->sum('total_cost');
        $this->monthlyTotal = (float) ($sum ?? 0);

        // Kosten pro Modul
        $this->moduleCosts = $this->getModuleCosts($startOfMonth, $endOfMonth);
        
        // Team-Mitglieder
        $this->teamMembers = $this->currentTeam->users()->get()->all();
        
        // Usage-Statistiken
        $this->usageStats = $this->getUsageStats($startOfMonth, $endOfMonth);
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
                $result[$moduleKey] = $moduleItems;
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
        
        foreach ($this->modules as $moduleKey => $module) {
            $moduleCost = TeamBillableUsage::where('team_id', $this->currentTeam->id)
                ->whereBetween('usage_date', [$startDate, $endDate])
                ->where('billable_model', 'like', '%' . $moduleKey . '%')
                ->sum('total_cost');
                
            $moduleCost = (float) ($moduleCost ?? 0);
            if ($moduleCost > 0) {
                $costs[$moduleKey] = [
                    'title' => $module['title'] ?? ucfirst($moduleKey),
                    'cost' => $moduleCost,
                    'icon' => $module['navigation']['icon'] ?? 'heroicon-o-cube'
                ];
            }
        }
        
        return $costs;
    }

    protected function getUsageStats($startDate, $endDate)
    {
        $stats = [];
        
        foreach ($this->modules as $moduleKey => $module) {
            $usage = TeamBillableUsage::where('team_id', $this->currentTeam->id)
                ->whereBetween('usage_date', [$startDate, $endDate])
                ->where('billable_model', 'like', '%' . $moduleKey . '%')
                ->sum('count');
                
            $usage = (int) ($usage ?? 0);
            if ($usage > 0) {
                $stats[$moduleKey] = [
                    'title' => $module['title'] ?? ucfirst($moduleKey),
                    'usage' => $usage,
                    'icon' => $module['navigation']['icon'] ?? 'heroicon-o-cube'
                ];
            }
        }
        
        return $stats;
    }

    public function render()
    {
        return view('platform::livewire.dashboard')->layout('platform::layouts.app');
    }
}