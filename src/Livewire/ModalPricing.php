<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\TeamBillableUsage;
use Platform\Core\PlatformCore;
use Livewire\Attributes\On;
use Carbon\Carbon;

class ModalPricing extends Component
{
    public $modalShow;

    public $monthlyUsages = [];
    public $monthlyTotal = 0;

    #[On('open-modal-pricing')]
    public function openModalPricing()
    {
        $this->modalShow = true;
        $this->loadMonthlyUsages();
    }

    public function mount()
    {
        $this->modalShow = false;
        $this->loadMonthlyUsages();
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    protected function loadMonthlyUsages()
    {
        $team = auth()->user()->currentTeam; // oder andere Team-Logik
        if (!$team) {
            $this->monthlyUsages = [];
            $this->monthlyTotal = 0;
            return;
        }

        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $usages = TeamBillableUsage::where('team_id', $team->id)
            ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
            ->orderBy('usage_date')
            ->get();

        $this->monthlyUsages = $usages;
        $this->monthlyTotal = $usages->sum('total_cost');
    }

    public function render()
    {
        return view('core::livewire.modal-pricing', [
            'monthlyUsages' => $this->monthlyUsages,
            'monthlyTotal'  => $this->monthlyTotal,
        ]);
    }
}