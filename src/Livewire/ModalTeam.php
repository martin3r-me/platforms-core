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
    public $newParentTeamId = null;
    public $availableParentTeams = [];
    public $currentParentTeamId = null;
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
        $this->loadAvailableParentTeams();
        $this->loadCurrentParentTeam();
        $this->loadMemberRoles();
        $this->loadBillingData();
        $this->loadBillingTotals();
    }

    public function openModal()
    {
        $this->modalShow = true;
        $this->team = auth()->user()->currentTeam;
        $this->loadAvailableParentTeams();
        $this->loadCurrentParentTeam();
        $this->loadMemberRoles();
    }

    public function loadTeams()
    {
        $this->allTeams = Team::whereHas('users', function ($query) {
            $query->where('user_id', auth()->id());
        })->get();
    }

    /**
     * Lädt verfügbare Parent-Teams (Root-Teams, zu denen der User Zugriff hat).
     * Nur Root-Teams können als Parent-Teams verwendet werden.
     */
    public function loadAvailableParentTeams()
    {
        $user = auth()->user();
        $currentTeamId = $this->team?->id;
        $currentParentTeamId = $this->team?->parent_team_id;
        
        $query = Team::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereNull('parent_team_id') // Nur Root-Teams
        ->when($currentTeamId, function ($query) use ($currentTeamId) {
            $query->where('id', '!=', $currentTeamId); // Aktuelles Team ausschließen
        });
        
        // Wenn bereits ein Parent-Team gesetzt ist, dieses auch in die Liste aufnehmen
        if ($currentParentTeamId) {
            $query->orWhere('id', $currentParentTeamId);
        }
        
        // Als Key-Value Array für die Select-Komponente: [id => name, ...]
        $this->availableParentTeams = $query
            ->get()
            ->unique('id')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Lädt das aktuelle Parent-Team des Teams.
     */
    public function loadCurrentParentTeam()
    {
        $this->currentParentTeamId = $this->team?->parent_team_id;
    }

    /**
     * Aktualisiert das Parent-Team (nur für Team-Owner).
     */
    public function updateParentTeam($parentTeamId = null)
    {
        $team = auth()->user()->currentTeam;
        if (!$team) {
            return;
        }

        // Nur Owner darf Parent-Team ändern
        if ($team->user_id !== auth()->id()) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Nur der Team-Owner kann das Parent-Team ändern.'
            ]);
            return;
        }

        // Konvertiere leeren String zu null und String zu Integer
        if ($parentTeamId === '' || $parentTeamId === 'null' || $parentTeamId === null) {
            $parentTeamId = null;
        } else {
            $parentTeamId = (int) $parentTeamId;
        }

        // Validierung (nur wenn ein Parent-Team gesetzt werden soll)
        if ($parentTeamId) {
            $parentTeam = Team::find($parentTeamId);
            
            if (!$parentTeam) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Das ausgewählte Parent-Team existiert nicht.'
                ]);
                return;
            }

            // Prüfe ob es ein Root-Team ist
            if ($parentTeam->parent_team_id !== null) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Nur Root-Teams können als Parent-Team verwendet werden.'
                ]);
                return;
            }

            // Prüfe ob User Zugriff auf das Parent-Team hat
            $user = auth()->user();
            if (!$parentTeam->users()->where('user_id', $user->id)->exists()) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Du hast keinen Zugriff auf das ausgewählte Parent-Team.'
                ]);
                return;
            }

            // Prüfe auf zirkuläre Referenzen (Team kann nicht Parent von sich selbst oder einem Kind sein)
            if ($parentTeam->id === $team->id || $parentTeam->isChildOf($team)) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Zirkuläre Referenz: Ein Team kann nicht Parent von sich selbst oder einem Kind-Team sein.'
                ]);
                return;
            }
        }

        // Update durchführen
        $team->parent_team_id = $parentTeamId ?: null;
        $team->save();

        // Team neu laden, damit Änderungen sichtbar sind
        $this->team = $team->fresh();
        $this->currentParentTeamId = $this->team->parent_team_id;
        $this->loadAvailableParentTeams(); // Neu laden, da sich die Hierarchie geändert haben könnte

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'Parent-Team erfolgreich aktualisiert.'
        ]);
    }

    public function createTeam()
    {
        $this->validate([
            'newTeamName' => 'required|string|max:255',
            'newParentTeamId' => [
                'nullable',
                'integer',
                'exists:teams,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Prüfe ob User Zugriff auf das Parent-Team hat
                        $user = auth()->user();
                        $parentTeam = Team::find($value);
                        
                        if (!$parentTeam) {
                            $fail('Das ausgewählte Parent-Team existiert nicht.');
                            return;
                        }

                        // Prüfe ob es ein Root-Team ist (nur Root-Teams können Parent sein)
                        if ($parentTeam->parent_team_id !== null) {
                            $fail('Nur Root-Teams können als Parent-Team verwendet werden.');
                            return;
                        }

                        // Prüfe ob User Mitglied des Parent-Teams ist
                        if (!$parentTeam->users()->where('user_id', $user->id)->exists()) {
                            $fail('Du hast keinen Zugriff auf das ausgewählte Parent-Team.');
                            return;
                        }

                        // Prüfe ob User Owner oder Admin des Parent-Teams ist
                        $userRole = $parentTeam->users()->where('user_id', $user->id)->first()?->pivot->role;
                        if (!in_array($userRole, [TeamRole::OWNER->value, TeamRole::ADMIN->value])) {
                            $fail('Nur Owner oder Admin können Kind-Teams erstellen.');
                            return;
                        }
                    }
                },
            ],
        ]);

        $team = Team::create([
            'name' => $this->newTeamName,
            'user_id' => auth()->id(),
            'parent_team_id' => $this->newParentTeamId,
            'personal_team' => false,
        ]);

        $team->users()->attach(auth()->id(), ['role' => TeamRole::OWNER->value]);

        $this->newTeamName = '';
        $this->newParentTeamId = null;
        $this->loadTeams();
        $this->loadAvailableParentTeams();
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