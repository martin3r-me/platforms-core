<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\Team;
use Platform\Core\Enums\TeamRole;

class ModalTeam extends Component
{
    public $modalShow = false;
    public $allTeams = [];
    public $user = [];
    public $newTeamName = '';
    public $inviteEmail = '';
    public $inviteRole = 'member';
    
    // Billing properties
    public $billing = [
        'company_name' => '',
        'tax_id' => '',
        'vat_id' => '',
        'billing_email' => '',
        'billing_address' => '',
        'payment_method' => 'sepa',
    ];
    
    // Billing totals
    public $monthlyTotal = 0;
    public $lastMonthTotal = 0;
    public $yearlyTotal = 0;

    protected $listeners = ['open-modal-team' => 'openModal'];

    public function mount()
    {
        $this->user = auth()->user()->toArray();
        $this->loadTeams();
        $this->loadBillingData();
        $this->loadBillingTotals();
    }

    public function openModal()
    {
        $this->modalShow = true;
    }

    public function loadTeams()
    {
        $this->allTeams = Team::whereHas('users', function ($query) {
            $query->where('user_id', auth()->id());
        })->get();
    }

    public function createTeam()
    {
        $this->validate([
            'newTeamName' => 'required|string|max:255',
        ]);

        $team = Team::create([
            'name' => $this->newTeamName,
            'user_id' => auth()->id(),
            'personal_team' => false,
        ]);

        $team->users()->attach(auth()->id(), ['role' => TeamRole::OWNER->value]);

        $this->newTeamName = '';
        $this->loadTeams();
        $this->dispatch('team-created');
    }

    public function updateMemberRole($memberId, $role)
    {
        $team = auth()->user()->currentTeam;
        if ($team && $team->user_id === auth()->id()) {
            $team->users()->updateExistingPivot($memberId, ['role' => $role]);
            $this->dispatch('member-role-updated');
        }
    }

    public function inviteToTeam()
    {
        $this->validate([
            'inviteEmail' => 'required|email',
            'inviteRole' => 'required|string',
        ]);

        // Hier würde die Einladungslogik implementiert
        $this->inviteEmail = '';
        $this->inviteRole = 'member';
        $this->dispatch('invitation-sent');
    }

    public function loadBillingData()
    {
        $team = auth()->user()->currentTeam;
        if ($team) {
            $billingData = $team->billing_data ?? [];
            $this->billing = array_merge($this->billing, $billingData);
        }
    }
    
    public function loadBillingTotals()
    {
        $team = auth()->user()->currentTeam;
        if (!$team) {
            return;
        }
        
        // Hier würde normalerweise die Abrechnungslogik implementiert
        // Für jetzt setzen wir Dummy-Werte
        $this->monthlyTotal = 0;
        $this->lastMonthTotal = 0;
        $this->yearlyTotal = 0;
    }
    
    public function saveBillingDetails()
    {
        $this->validate([
            'billing.company_name' => 'required|string|max:255',
            'billing.billing_email' => 'required|email',
            'billing.billing_address' => 'required|string',
            'billing.payment_method' => 'required|in:sepa,invoice,credit_card',
        ]);
        
        $team = auth()->user()->currentTeam;
        if ($team) {
            $team->update([
                'billing_data' => $this->billing
            ]);
            
            $this->dispatch('notice', [
                'type' => 'success',
                'message' => 'Rechnungsdaten erfolgreich gespeichert!'
            ]);
        }
    }

    public function render()
    {
        return view('platform::livewire.modal-team');
    }
}