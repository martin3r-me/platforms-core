<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Enums\TeamRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;

class ModalTeam extends Component
{
    public $modalShow = false;
    public $team;
    public $newTeamName = '';
    public $inviteEmail = '';
    public $inviteRole = TeamRole::MEMBER;
    public $allTeams = [];
    public $user;
    public $memberRoles = [];
    public $addingPayment = false;
    public $mollieKey = null;

    protected $rules = [
        'team.name' => 'required|string|max:255',
        'user.current_team_id' => 'required|integer',
    ];

    #[On('open-modal-team')]
    public function openModalTeam()
    {
        $this->loadTeam();
        $this->modalShow = true;
    }

    public function mount()
    {
        $this->user = Auth::user();
        $this->allTeams = $this->user->teams()->get();
        $this->loadTeam();
        $this->mollieKey = env('MOLLIE_KEY');
    }

    public function loadTeam()
    {
        $user = Auth::user();
        $this->team = $user->currentTeam;
        $this->teamTitle = $this->team?->name ?? '';
        
        // Member-Rollen laden
        if ($this->team) {
            $this->memberRoles = $this->team->users->pluck('pivot.role', 'id')->toArray();
        } else {
            $this->memberRoles = [];
        }
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function updatedTeam($property, $value)
    {
        $this->validateOnly("team.$property");
        $this->team->save();
    }

    public function updatedUser($property, $value)
    {
        $this->validateOnly("user.$property");
        $this->user->save();

        $this->redirect('/');
    }

    public function inviteToTeam()
    {
        $this->authorizeEdit();

        $this->validate([
            'inviteEmail' => 'required|email|unique:team_invitations,email,NULL,id,team_id,' . $this->team->id,
            'inviteRole' => [
                'required',
                Rule::in([TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value]),
            ],
        ]);

        TeamInvitation::create([
            'team_id' => $this->team->id,
            'email'   => $this->inviteEmail,
            'token'   => Str::uuid(),
            'role'    => $this->inviteRole,
        ]);

        // Einladung ggf. Mail oder Notification anstoßen (dein Workflow)
        $this->inviteEmail = '';
        $this->inviteRole = TeamRole::MEMBER;
        $this->dispatch('team-invited');
    }

    public function createTeam()
    {
        $this->validate([
            'newTeamName' => 'required|string|max:255'
        ]);

        // Erstellen
        $team = Team::create([
            'name' => $this->newTeamName,
            'user_id' => Auth::id(),
            'personal_team' => false,
        ]);

        // Direkt als Mitglied eintragen
        $team->users()->attach(Auth::id(), ['role' => TeamRole::OWNER->value]);

        // Optional: aktuelles Team auf das neue setzen
        Auth::user()->current_team_id = $team->id;
        Auth::user()->save();

        $this->newTeamName = '';
        $this->loadTeam();
    }

    public function updateMemberRole($userId, $newRole)
    {
        // Nur Team-Owner kann Rollen ändern
        if (!$this->team || $this->team->user_id !== Auth::id()) {
            return;
        }
        
        // Validierung der Rolle
        $validRoles = [TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value, TeamRole::VIEWER->value];
        if (!in_array($newRole, $validRoles)) {
            return;
        }
        
        // Rolle in der Datenbank aktualisieren
        $this->team->users()->updateExistingPivot($userId, ['role' => $newRole]);
        
        // Member-Rollen neu laden
        $this->loadTeam();
        
        $this->dispatch('member-role-updated');
    }

    public function addPaymentMethod()
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) {
            return;
        }
        $this->addingPayment = true;
    }

    public function updatePaymentMethod()
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) {
            return;
        }
        $this->addingPayment = true;
    }

    public function removePaymentMethod()
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) {
            return;
        }
        $this->team->update([
            'mollie_payment_method_id' => null,
            'payment_method_last_4' => null,
            'payment_method_brand' => null,
            'payment_method_expires_at' => null,
        ]);
        $this->loadTeam();
        $this->dispatch('payment-method-removed');
    }

    public function cancelAddPayment()
    {
        $this->addingPayment = false;
    }

    public function savePaymentMethod(string $cardToken)
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) {
            return;
        }

        $secretKey = env('MOLLIE_KEY');
        if (!$secretKey) {
            // Ohne Secret-Key kein Server-Call möglich
            return;
        }

        // 1) Customer anlegen (falls nicht vorhanden)
        if (!$this->team->mollie_customer_id) {
            $customerResp = Http::withToken($secretKey)
                ->acceptJson()
                ->post('https://api.mollie.com/v2/customers', [
                    'name' => $this->team->name,
                    'email' => $this->user->email,
                ]);

            if ($customerResp->failed()) {
                return;
            }
            $this->team->mollie_customer_id = $customerResp->json('id');
            $this->team->save();
        }

        // 2) Mandat mit cardToken erstellen
        $mandateResp = Http::withToken($secretKey)
            ->acceptJson()
            ->post('https://api.mollie.com/v2/customers/' . $this->team->mollie_customer_id . '/mandates', [
                'method' => 'creditcard',
                'cardToken' => $cardToken,
                'consumerName' => $this->team->name,
            ]);

        if ($mandateResp->failed()) {
            return;
        }

        // Details aus Mandat (falls verfügbar)
        $details = $mandateResp->json('details') ?: [];
        $cardLabel = $details['cardLabel'] ?? ($details['cardHolder'] ?? 'card');
        $last4 = $details['cardNumber'] ?? null; // kann maskiert sein
        if (is_string($last4) && strlen($last4) >= 4) {
            $last4 = substr($last4, -4);
        } else {
            $last4 = null;
        }

        $this->team->update([
            'mollie_payment_method_id' => $mandateResp->json('id'),
            'payment_method_brand' => $cardLabel,
            'payment_method_last_4' => $last4,
            'payment_method_expires_at' => null,
        ]);

        $this->addingPayment = false;
        $this->loadTeam();
        $this->dispatch('payment-method-saved');
    }

    public function render()
    {
        return view('platform::livewire.modal-team', [
            'team' => $this->team,
            'roles' => TeamRole::cases(),
        ]);
    }
}