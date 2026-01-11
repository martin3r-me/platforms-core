<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Models\User;
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Services\TeamInvitationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class ModalTeam extends Component
{
    public $modalShow = false;
    public $allTeams = [];
    public $user = [];
    public $newTeamName = '';
    public $newParentTeamId = null;
    public $availableParentTeams = [];
    public $currentParentTeamId = null;
    public $inviteEmails = '';
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

    // AI User properties
    public $aiUserForm = [
        'name' => '',
        'core_ai_model_id' => null,
        'instruction' => '',
    ];
    public $aiUsers = [];
    public $availableAiModels = [];

    protected $listeners = ['open-modal-team' => 'openModal'];

    public function mount()
    {
        $this->user = auth()->user()->toArray();
        $this->team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        $this->loadTeams();
        $this->loadAvailableParentTeams();
        $this->loadCurrentParentTeam();
        $this->loadMemberRoles();
        $this->loadBillingData();
        $this->loadBillingTotals();
        $this->loadAiUsers();
        $this->loadAvailableAiModels();
    }

    public function openModal()
    {
        $this->modalShow = true;
        $this->team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        $this->loadAvailableParentTeams();
        $this->loadCurrentParentTeam();
        $this->loadMemberRoles();
        $this->loadBillingData();
        $this->loadBillingTotals();
    }

    public function loadTeams()
    {
        $this->allTeams = Team::whereHas('users', function ($query) {
            $query->where('user_id', auth()->id());
        })->get();
    }

    /**
     * Wechselt das aktuelle Team des Users.
     * (Wird im Blade via @change aufgerufen.)
     */
    public function changeCurrentTeam($teamId)
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $teamId = (int) $teamId;
        if ($teamId <= 0) {
            return;
        }

        // Sicherheitscheck: User muss Mitglied des Ziel-Teams sein
        $hasAccess = $user->teams()->where('teams.id', $teamId)->exists();
        if (! $hasAccess) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Du hast keinen Zugriff auf dieses Team.',
            ]);
            return;
        }

        $user->current_team_id = $teamId;
        $user->save();

        // Session-Flag setzen, damit Middleware (falls vorhanden) Teamwechsel erkennt
        session(['switching_team' => true]);

        $this->modalShow = false;

        // Harte Weiterleitung, damit Sidebar/Guards sauber neu laden
        return $this->redirect(request()->fullUrl());
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

        // Root-Teams, zu denen der User Zugriff hat (und nicht das aktuelle Team)
        $rootTeamsQuery = Team::query()
            ->whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereNull('parent_team_id');

        if ($currentTeamId) {
            $rootTeamsQuery->where('id', '!=', $currentTeamId);
        }

        $rootTeams = $rootTeamsQuery->get();

        // Wenn bereits ein Parent-Team gesetzt ist, dieses auch in die Liste aufnehmen,
        // aber nur, wenn der User Zugriff hat (sonst würden wir fremde Teams anzeigen).
        if ($currentParentTeamId) {
            $currentParent = Team::query()
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->where('id', $currentParentTeamId)
                ->first();

            if ($currentParent) {
                $rootTeams->push($currentParent);
            }
        }

        $this->availableParentTeams = $rootTeams
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
     * Wird von wire:change aufgerufen, wenn currentParentTeamId sich ändert.
     */
    public function updateParentTeam()
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
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

        // Wert aus Property lesen
        $parentTeamId = $this->currentParentTeamId;

        // Konvertiere leeren String zu null und String zu Integer
        if ($parentTeamId === '' || $parentTeamId === 'null') {
            $parentTeamId = null;
        } elseif ($parentTeamId) {
            $parentTeamId = (int) $parentTeamId;
            
            // Validierung
            $parentTeam = Team::find($parentTeamId);
            if (!$parentTeam) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Das ausgewählte Parent-Team existiert nicht.'
                ]);
                return;
            }

            if ($parentTeam->parent_team_id !== null) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Nur Root-Teams können als Parent-Team verwendet werden.'
                ]);
                return;
            }

            $user = auth()->user();
            if (!$parentTeam->users()->where('user_id', $user->id)->exists()) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Du hast keinen Zugriff auf das ausgewählte Parent-Team.'
                ]);
                return;
            }

            if ($parentTeam->id === $team->id || $parentTeam->isChildOf($team)) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Zirkuläre Referenz: Ein Team kann nicht Parent von sich selbst oder einem Kind-Team sein.'
                ]);
                return;
            }
        }

        // Speichern
        $team->parent_team_id = $parentTeamId;
        $team->save();

        // Aktualisieren
        $this->team = $team->fresh();
        $this->currentParentTeamId = $this->team->parent_team_id;
        $this->loadAvailableParentTeams();

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'Parent-Team erfolgreich aktualisiert.'
        ]);
    }

    public function createTeam()
    {
        // Normalisieren: Livewire liefert bei nullable Selects oft '' statt null
        if ($this->newParentTeamId === '' || $this->newParentTeamId === 'null') {
            $this->newParentTeamId = null;
        } elseif ($this->newParentTeamId !== null) {
            $this->newParentTeamId = (int) $this->newParentTeamId;
        }

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

        // Direkt auf das neue Team wechseln
        $user = auth()->user();
        if ($user) {
            $user->current_team_id = $team->id;
            $user->save();
            session(['switching_team' => true]);
        }

        $this->modalShow = false;

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'Team erfolgreich erstellt.',
        ]);

        // Harte Weiterleitung, damit Sidebar/Guards sauber neu laden
        return $this->redirect(request()->fullUrl());
    }

    public function updateMemberRole($memberId, $role)
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
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

    public function inviteToTeam(TeamInvitationService $invitationService)
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        if (! $team) { return; }

        $emails = collect(preg_split('/[\s,;]+/', (string) $this->inviteEmails, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($email) => Str::lower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            $this->addError('inviteEmails', 'Bitte mindestens eine E-Mail-Adresse angeben (Komma oder Zeilenumbruch getrennt).');
            return;
        }

        // Rolle validieren (das Service validiert erneut, hier nur schnelle Prüfung)
        Validator::make(
            ['inviteRole' => $this->inviteRole],
            ['inviteRole' => ['required', Rule::in([TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value, 'viewer'])]]
        )->validate();

        $result = $invitationService->createInvitations($team, $emails->all(), $this->inviteRole);

        $createdCount = count($result['created']);
        $skipped = collect($result['skipped']);

        if ($createdCount > 0) {
            $this->dispatch('notice', [
                'type' => 'success',
                'message' => "{$createdCount} Einladung(en) gesendet.",
            ]);
        }

        if ($skipped->isNotEmpty()) {
            $skippedList = $skipped->pluck('email')->implode(', ');
            $this->dispatch('notice', [
                'type' => 'warning',
                'message' => "Übersprungen: {$skippedList}",
            ]);
        }

        $this->inviteEmails = '';
        $this->inviteRole = 'member';
        $this->dispatch('invitation-sent');
    }

    public function revokeInvitation(int $invitationId): void
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
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
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        if (! $team) { return collect(); }
        return $team->invitations()->whereNull('accepted_at')->latest()->get();
    }

    public function removeMember(int $memberId): void
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
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
        $this->team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        unset($this->memberRoles[$memberId]);
    }

    private function loadMemberRoles(): void
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        if ($team) {
            $this->memberRoles = $team->users->pluck('pivot.role', 'id')->toArray();
        } else {
            $this->memberRoles = [];
        }
    }

    public function loadBillingData()
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        if ($team) {
            $billingData = $team->billing_data ?? [];
            $this->billing = array_merge($this->billing, $billingData);
        }
    }
    
    public function loadBillingTotals()
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
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
        
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
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

    public function loadAiUsers()
    {
        $team = auth()->user()->currentTeamRelation;
        if ($team) {
            $this->aiUsers = $team->users()->where('type', 'ai_user')->get();
        } else {
            $this->aiUsers = [];
        }
    }

    public function loadAvailableAiModels()
    {
        $this->availableAiModels = collect([]);
        
        if (Schema::hasTable('core_ai_models') && class_exists(CoreAiModel::class)) {
            try {
                $this->availableAiModels = CoreAiModel::all()->pluck('name', 'id')->toArray();
            } catch (\Exception $e) {
                $this->availableAiModels = [];
            }
        }
    }

    public function createAiUser()
    {
        $team = auth()->user()->currentTeamRelation;
        if (!$team) {
            return;
        }

        Gate::authorize('addTeamMember', $team);

        $rules = [
            'aiUserForm.name' => 'required|string|max:255',
            'aiUserForm.instruction' => 'nullable|string',
        ];

        if (Schema::hasTable('core_ai_models')) {
            $rules['aiUserForm.core_ai_model_id'] = 'nullable|exists:core_ai_models,id';
        } else {
            $rules['aiUserForm.core_ai_model_id'] = 'nullable|integer';
        }

        $this->validate($rules, [
            'aiUserForm.name.required' => 'Der Name ist erforderlich.',
            'aiUserForm.core_ai_model_id.exists' => 'Das ausgewählte AI-Model existiert nicht.',
        ]);

        // Erstelle den AI-User
        $aiUser = User::create([
            'name' => $this->aiUserForm['name'],
            'type' => 'ai_user',
            'core_ai_model_id' => $this->aiUserForm['core_ai_model_id'],
            'instruction' => $this->aiUserForm['instruction'] ?? null,
            'team_id' => $team->id,
            'email' => null,
            'password' => null,
        ]);

        // Füge den AI-User direkt zum Team hinzu
        $team->users()->attach($aiUser, ['role' => TeamRole::MEMBER->value]);

        // Reset form
        $this->aiUserForm = [
            'name' => '',
            'core_ai_model_id' => null,
            'instruction' => '',
        ];

        // Liste neu laden
        $this->loadAiUsers();
        $this->team = $team->fresh();

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'AI-User erfolgreich erstellt und zum Team hinzugefügt.',
        ]);
    }

    public function removeAiUser(int $userId): void
    {
        $team = auth()->user()->currentTeamRelation;
        if (!$team) {
            return;
        }

        // Nur Owner darf AI-User entfernen
        if ($team->user_id !== auth()->id()) {
            return;
        }

        $aiUser = User::find($userId);
        if (!$aiUser || !$aiUser->isAiUser()) {
            return;
        }

        $team->users()->detach($userId);
        $this->loadAiUsers();
        $this->team = $team->fresh();

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'AI-User erfolgreich entfernt.',
        ]);
    }

    public function render()
    {
        return view('platform::livewire.modal-team');
    }
}