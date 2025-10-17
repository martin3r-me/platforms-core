<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Enums\TeamRole;

class ModalTeam extends Component
{
    public $modalShow = false;
    public $allTeams = [];
    public $user = [];
    public $newTeamName = '';
    public $inviteEmail = '';
    public $inviteRole = 'member';
    public $team;
    public $memberRoles = [];
    
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
    
    // Detailed billing data
    public $monthlyUsages = [];
    
    // Payment method options
    public $paymentMethodOptions = [
        'sepa' => 'SEPA-Lastschrift',
        'invoice' => 'Rechnung',
        'credit_card' => 'Kreditkarte',
    ];

    protected $listeners = ['open-modal-team' => 'openModal'];

    public function mount()
    {
        $this->user = auth()->user()->toArray();
        $this->team = auth()->user()->currentTeam;
        $this->loadTeams();
        $this->loadMemberRoles();
        $this->loadBillingData();
        $this->loadBillingTotals();
    }

    public function openModal()
    {
        $this->modalShow = true;
        $this->team = auth()->user()->currentTeam;
        $this->loadMemberRoles();
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
            $this->memberRoles[$memberId] = $role;
            $this->dispatch('member-role-updated');
        }
    }

    public function updatedUser($property, $value)
    {
        // Teamwechsel wurde entfernt – keine automatische Umschaltung mehr hier
    }

    // changeCurrentTeam entfernt – Wechsel erfolgt zentral über Module-Modal

    public function inviteToTeam()
    {
        $team = auth()->user()->currentTeam;
        if (! $team) { return; }

        $this->validate([
            'inviteEmail' => [
                'required',
                'email',
                Rule::unique('team_invitations', 'email')->where(fn($q) => $q->where('team_id', $team->id)),
            ],
            'inviteRole' => [
                'required',
                Rule::in([TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value, 'viewer']),
            ],
        ]);

        TeamInvitation::create([
            'team_id' => $team->id,
            'email'   => $this->inviteEmail,
            'token'   => Str::uuid(),
            'role'    => $this->inviteRole,
        ]);

        $this->inviteEmail = '';
        $this->inviteRole = 'member';
        $this->dispatch('invitation-sent');
    }

    public function revokeInvitation(int $invitationId): void
    {
        $team = auth()->user()->currentTeam;
        if (! $team) { return; }

        $inv = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->whereNull('accepted_at')
            ->find($invitationId);

        if ($inv) {
            $inv->delete();
            $this->dispatch('invitation-revoked');
        }
    }

    public function getPendingInvitationsProperty()
    {
        $team = auth()->user()->currentTeam;
        if (! $team) { return collect(); }
        return $team->invitations()->whereNull('accepted_at')->latest()->get();
    }

    public function removeMember(int $memberId): void
    {
        $team = auth()->user()->currentTeam;
        if (! $team) { return; }

        // Nur Owner darf Mitglieder entfernen
        if ($team->user_id !== auth()->id()) {
            return;
        }

        // Selbst-Entfernen zulassen, sofern es weitere Owner gibt
        $member = $team->users()->where('users.id', $memberId)->first();
        if (! $member) { return; }

        $isMemberOwner = ($member->pivot->role ?? null) === TeamRole::OWNER->value;
        if ($isMemberOwner) {
            $ownerCount = $team->users()->wherePivot('role', TeamRole::OWNER->value)->count();
            if ($ownerCount <= 1) {
                // Letzten Owner nicht entfernen
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Der letzte Team-Owner kann nicht entfernt werden.'
                ]);
                return;
            }
        }

        $team->users()->detach($memberId);
        $this->dispatch('member-removed');
        // Liste neu laden
        $this->loadTeams();
        $this->team = auth()->user()->currentTeam;
        unset($this->memberRoles[$memberId]);
    }

    private function loadMemberRoles(): void
    {
        $team = auth()->user()->currentTeam;
        if ($team) {
            $this->memberRoles = $team->users->pluck('pivot.role', 'id')->toArray();
        } else {
            $this->memberRoles = [];
        }
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
        
        // Dummy-Daten für die detaillierte Abrechnungstabelle
        $this->monthlyUsages = collect([
            (object) [
                'usage_date' => now()->format('Y-m-d'),
                'label' => 'Planner-Aufgabe',
                'billable_type' => 'Platform\Planner\Models\PlannerTask',
                'count' => 5,
                'cost_per_unit' => 0.0025,
                'total_cost' => 0.0125,
            ],
            (object) [
                'usage_date' => now()->subDay()->format('Y-m-d'),
                'label' => 'Planner-Projekt',
                'billable_type' => 'Platform\Planner\Models\PlannerProject',
                'count' => 2,
                'cost_per_unit' => 0.005,
                'total_cost' => 0.01,
            ],
        ]);
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