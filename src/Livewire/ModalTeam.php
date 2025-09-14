<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Enums\TeamRole;
use Illuminate\Support\Facades\Auth;
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
    public $lastInvoiceDate = null;
    public $nextInvoiceDate = null;

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
    }

    public function loadTeam()
    {
        $user = Auth::user();
        $this->team = $user->currentTeam;
        $this->teamTitle = $this->team?->name ?? '';
        
        // Member-Rollen laden
        if ($this->team) {
            $this->memberRoles = $this->team->users->pluck('pivot.role', 'id')->toArray();
        }
        
        // Abrechnungsdaten laden
        $this->loadBillingInfo();
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

    public function updatedMemberRoles($value, $userId)
    {
        $this->authorizeEdit();
        
        // Validierung der Rolle
        $validRoles = [TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value, TeamRole::VIEWER->value];
        if (!in_array($value, $validRoles)) {
            $this->addError('memberRoles.' . $userId, 'Ungültige Rolle');
            return;
        }
        
        // Rolle in der Datenbank aktualisieren
        $this->team->users()->updateExistingPivot($userId, ['role' => $value]);
        
        $this->dispatch('member-role-updated');
    }

    public function removeMember($userId)
    {
        $this->authorizeEdit();
        
        // Verhindern, dass sich der Owner selbst entfernt
        if ($userId === Auth::id()) {
            $this->addError('member', 'Du kannst dich nicht selbst aus dem Team entfernen');
            return;
        }
        
        // Mitglied aus dem Team entfernen
        $this->team->users()->detach($userId);
        
        // Member-Rollen neu laden
        $this->loadTeam();
        
        $this->dispatch('member-removed');
    }

    public function addPaymentMethod()
    {
        $this->authorizeEdit();
        
        // Hier würde die Mollie-Integration für das Hinzufügen einer Zahlungsmethode implementiert
        // Für jetzt simulieren wir es
        $this->dispatch('open-payment-modal', ['action' => 'add']);
    }

    public function updatePaymentMethod()
    {
        $this->authorizeEdit();
        
        // Hier würde die Mollie-Integration für das Aktualisieren einer Zahlungsmethode implementiert
        $this->dispatch('open-payment-modal', ['action' => 'update']);
    }

    public function removePaymentMethod()
    {
        $this->authorizeEdit();
        
        // Zahlungsmethode aus der Datenbank entfernen
        $this->team->update([
            'mollie_payment_method_id' => null,
            'payment_method_last_4' => null,
            'payment_method_brand' => null,
            'payment_method_expires_at' => null,
        ]);
        
        $this->loadTeam();
        $this->dispatch('payment-method-removed');
    }

    private function loadBillingInfo()
    {
        if (!$this->team) {
            return;
        }
        
        // Hier würde die Integration mit dem Billing-System implementiert
        // Für jetzt simulieren wir die Daten
        $this->lastInvoiceDate = $this->team->created_at->format('d.m.Y');
        $this->nextInvoiceDate = now()->addMonth()->format('d.m.Y');
    }

    private function authorizeEdit()
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) {
            abort(403, 'Keine Berechtigung');
        }
    }

    public function render()
    {
        return view('platform::livewire.modal-team', [
            'team' => $this->team,
            'roles' => TeamRole::cases(),
        ]);
    }
}