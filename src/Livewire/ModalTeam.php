<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Models\User;
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Models\TeamCoreAiModel;
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
    public $availableAiUsersToAdd = [];
    public bool $canAddUsers = false;
    
    // Create Team properties
    public $newInitialMembers = []; // Array von ['user_id' => X, 'role' => 'member']
    public $availableUsersForTeam = [];

    // Team AI Model properties
    public array $teamModelToggles = [];
    public $teamAiModels = [];
    public ?string $teamModelMessage = null;

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
        $this->loadAvailableAiUsersToAdd();
        $this->loadCanAddUsers();
        $this->loadTeamAiModels();
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
        $this->loadAiUsers();
        $this->loadAvailableAiModels();
        $this->loadAvailableAiUsersToAdd();
        $this->loadCanAddUsers();
        $this->loadAvailableUsersForTeam();
        $this->loadTeamAiModels();
    }

    /**
     * Lädt verfügbare User, die beim Erstellen eines Teams hinzugefügt werden können.
     * Dies sind alle User (inkl. AI-User), die in Teams sind, zu denen der aktuelle User Zugriff hat.
     */
    public function loadAvailableUsersForTeam()
    {
        $user = auth()->user();
        if (!$user) {
            $this->availableUsersForTeam = [];
            return;
        }

        // Hole alle Teams, zu denen der User Zugriff hat
        $userTeams = Team::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        // Sammle alle User aus diesen Teams (außer dem aktuellen User, aber inkl. AI-User)
        $availableUsers = collect();
        foreach ($userTeams as $team) {
            $teamUsers = $team->users()
                ->where('users.id', '!=', $user->id)
                ->get();
            
            foreach ($teamUsers as $teamUser) {
                if (!$availableUsers->contains('id', $teamUser->id)) {
                    $availableUsers->push($teamUser);
                }
            }
        }

        $this->availableUsersForTeam = $availableUsers->sortBy('name')->values()->all();
    }

    /**
     * Toggelt einen User in der Liste der initialen Mitglieder beim Team-Erstellen.
     */
    public function toggleInitialMember(int $userId): void
    {
        $existingIndex = collect($this->newInitialMembers)->search(fn($m) => ($m['user_id'] ?? null) == $userId);

        if ($existingIndex !== false) {
            // User entfernen
            unset($this->newInitialMembers[$existingIndex]);
            $this->newInitialMembers = array_values($this->newInitialMembers);
        } else {
            // User hinzufügen mit Default-Rolle "member"
            $this->newInitialMembers[] = [
                'user_id' => $userId,
                'role' => 'member',
            ];
        }
    }

    protected function loadCanAddUsers(): void
    {
        $team = $this->team ?? auth()->user()?->currentTeamRelation;
        if (!$team) {
            $this->canAddUsers = false;
            return;
        }

        $userRole = $team->users()->where('user_id', auth()->id())->first()?->pivot->role;
        $this->canAddUsers = in_array($userRole, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true);
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
     * 
     * Ein Team qualifiziert sich als Parent-Team, wenn:
     * 1. Es ein Root-Team ist (parent_team_id ist null)
     * 2. Der User Owner oder Admin ist (über team_user Pivot-Tabelle ODER direkt als user_id)
     * 3. Es nicht das aktuelle Team ist (wenn das aktuelle Team bereits ein Child-Team ist)
     * 
     * Das aktuelle Team wird angezeigt, wenn es ein Root-Team ist (z.B. beim Erstellen eines neuen Teams)
     */
    public function loadAvailableParentTeams()
    {
        $user = auth()->user();
        $currentTeamId = $this->team?->id;
        $currentParentTeamId = $this->team?->parent_team_id;

        // Root-Teams, bei denen der User Owner oder Admin ist
        // Option 1: Teams, bei denen der User über die users-Beziehung Owner oder Admin ist
        // Option 2: Teams, bei denen der User direkt als Owner gesetzt ist (user_id)
        
        // Hole alle Team-IDs, bei denen der User Owner oder Admin ist (über Pivot-Tabelle)
        $teamIdsFromPivot = DB::table('team_user')
            ->where('user_id', $user->id)
            ->whereIn('role', [TeamRole::OWNER->value, TeamRole::ADMIN->value])
            ->pluck('team_id')
            ->toArray();
        
        $rootTeamsQuery = Team::query()
            ->where(function ($query) use ($user, $teamIdsFromPivot) {
                // Über users-Beziehung (Owner oder Admin) - über Pivot-Tabelle
                if (!empty($teamIdsFromPivot)) {
                    $query->whereIn('id', $teamIdsFromPivot);
                }
                // Oder direkt als Owner (user_id)
                $query->orWhere('user_id', $user->id);
            })
            ->whereNull('parent_team_id');

        // Das aktuelle Team nur ausschließen, wenn es bereits ein Parent-Team hat
        // (ein Team kann nicht sein eigenes Parent-Team sein)
        // Wenn das aktuelle Team ein Root-Team ist, sollte es angezeigt werden
        // (z.B. wenn man ein neues Team erstellt und das aktuelle Team als Parent wählen will)
        if ($currentTeamId && $this->team && $this->team->parent_team_id !== null) {
            // Nur ausschließen, wenn es bereits ein Child-Team ist
            $rootTeamsQuery->where('id', '!=', $currentTeamId);
        }

        $rootTeams = $rootTeamsQuery->get();

        // Wenn bereits ein Parent-Team gesetzt ist, dieses auch in die Liste aufnehmen,
        // aber nur, wenn der User Owner oder Admin ist (sonst würden wir fremde Teams anzeigen).
        if ($currentParentTeamId) {
            // Prüfe ob User Owner oder Admin ist (über Pivot-Tabelle)
            $isOwnerOrAdmin = DB::table('team_user')
                ->where('team_id', $currentParentTeamId)
                ->where('user_id', $user->id)
                ->whereIn('role', [TeamRole::OWNER->value, TeamRole::ADMIN->value])
                ->exists();
            
            // Oder direkt als Owner (user_id)
            $isDirectOwner = Team::where('id', $currentParentTeamId)
                ->where('user_id', $user->id)
                ->exists();
            
            if ($isOwnerOrAdmin || $isDirectOwner) {
                $currentParent = Team::find($currentParentTeamId);
                if ($currentParent && !$rootTeams->contains('id', $currentParentTeamId)) {
                    $rootTeams->push($currentParent);
                }
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

            // Prüfe ob User Owner oder Admin des Parent-Teams ist
            $userRole = $parentTeam->users()->where('user_id', $user->id)->first()?->pivot->role;
            if (!in_array($userRole, [TeamRole::OWNER->value, TeamRole::ADMIN->value])) {
                $this->dispatch('notice', [
                    'type' => 'error',
                    'message' => 'Nur Owner oder Admin können ein Parent-Team setzen.'
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
            'newInitialMembers' => 'nullable|array',
            'newInitialMembers.*.user_id' => 'required|integer|exists:users,id',
            'newInitialMembers.*.role' => 'required|string|in:owner,admin,member,viewer',
        ]);

        $team = Team::create([
            'name' => $this->newTeamName,
            'user_id' => auth()->id(),
            'parent_team_id' => $this->newParentTeamId,
            'personal_team' => false,
        ]);

        // Owner hinzufügen
        $team->users()->attach(auth()->id(), ['role' => TeamRole::OWNER->value]);

        // Initial members hinzufügen, falls ausgewählt
        // Beim Erstellen eines Teams wird der Ersteller automatisch Owner, also kann er alle Rollen vergeben
        // Aber wir prüfen trotzdem für Konsistenz
        if (!empty($this->newInitialMembers) && is_array($this->newInitialMembers)) {
            $creatorRole = TeamRole::OWNER->value; // Beim Erstellen ist der Ersteller automatisch Owner
            
            foreach ($this->newInitialMembers as $memberData) {
                if (!isset($memberData['user_id']) || !isset($memberData['role'])) {
                    continue;
                }
                
                $memberId = (int) $memberData['user_id'];
                $role = $memberData['role'];
                
                // Validiere Rolle
                if (!in_array($role, ['owner', 'admin', 'member', 'viewer'])) {
                    $role = TeamRole::MEMBER->value;
                }
                
                if ($memberId > 0 && $memberId !== auth()->id()) {
                    // Prüfe, ob User existiert und nicht bereits hinzugefügt wurde
                    $member = User::find($memberId);
                    if ($member && !$team->users()->where('users.id', $memberId)->exists()) {
                        // AI-User können nicht Owner sein
                        if ($member->isAiUser() && $role === 'owner') {
                            $role = TeamRole::MEMBER->value;
                        }
                        
                        // Prüfe, ob die Rolle basierend auf der eigenen Rolle vergeben werden darf
                        if (!$this->canAssignRole($creatorRole, $role)) {
                            // Fallback auf Member, wenn die Rolle nicht vergeben werden darf
                            $role = TeamRole::MEMBER->value;
                        }
                        
                        $team->users()->attach($memberId, ['role' => $role]);
                    }
                }
            }
        }

        $this->newTeamName = '';
        $this->newParentTeamId = null;
        $this->newInitialMembers = [];
        $this->loadTeams();
        $this->loadAvailableParentTeams();
        $this->loadAvailableUsersForTeam();

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

    /**
     * Gibt die maximale Rolle zurück, die ein User basierend auf seiner eigenen Rolle vergeben darf.
     * Owner kann alle Rollen vergeben, Admin kann admin/member/viewer vergeben, Member kann keine Rollen vergeben.
     */
    protected function getMaxAssignableRole(?string $userRole): ?string
    {
        if ($userRole === TeamRole::OWNER->value) {
            return TeamRole::OWNER->value; // Owner kann alle Rollen vergeben
        }
        if ($userRole === TeamRole::ADMIN->value) {
            return TeamRole::ADMIN->value; // Admin kann admin/member/viewer vergeben
        }
        return null; // Member kann keine Rollen vergeben
    }

    /**
     * Prüft, ob eine Rolle basierend auf der eigenen Rolle vergeben werden darf.
     */
    protected function canAssignRole(?string $userRole, string $targetRole): bool
    {
        $maxRole = $this->getMaxAssignableRole($userRole);
        if ($maxRole === null) {
            return false; // Member kann keine Rollen vergeben
        }

        // Owner kann alle Rollen vergeben
        if ($maxRole === TeamRole::OWNER->value) {
            return true;
        }

        // Admin kann admin, member, viewer vergeben (aber nicht owner)
        if ($maxRole === TeamRole::ADMIN->value) {
            return in_array($targetRole, [TeamRole::ADMIN->value, TeamRole::MEMBER->value, 'viewer'], true);
        }

        return false;
    }

    public function updateMemberRole($memberId, $role)
    {
        $team = auth()->user()->currentTeamRelation; // Child-Team (nicht dynamisch)
        if (!$team) {
            return;
        }

        // Prüfe, ob User Owner oder Admin ist
        $userRole = $team->users()->where('user_id', auth()->id())->first()?->pivot->role;
        if (!in_array($userRole, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true)) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Nur Owner oder Admin können Rollen ändern.',
            ]);
            return;
        }

        // Prüfe, ob die Rolle basierend auf der eigenen Rolle vergeben werden darf
        if (!$this->canAssignRole($userRole, $role)) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Du kannst diese Rolle nicht vergeben. ' . 
                    ($userRole === TeamRole::ADMIN->value ? 'Als Admin kannst du nur Admin, Member oder Viewer vergeben.' : ''),
            ]);
            return;
        }

        // Owner kann nur durch Owner vergeben werden
        if ($role === TeamRole::OWNER->value && $userRole !== TeamRole::OWNER->value) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Nur Owner können die Owner-Rolle vergeben.',
            ]);
            return;
        }

        $team->users()->updateExistingPivot($memberId, ['role' => $role]);
        $this->memberRoles[$memberId] = $role;
        $this->dispatch('member-role-updated');
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

    public function loadAvailableAiUsersToAdd()
    {
        $team = $this->team ?? auth()->user()?->currentTeamRelation;
        if (!$team) {
            $this->availableAiUsersToAdd = [];
            return;
        }

        // Hole alle AI-User, die zu diesem Team hinzugefügt werden können
        $allAiUsers = User::where('type', 'ai_user')->get();
        $availableUsers = collect([]);

        foreach ($allAiUsers as $aiUser) {
            // Prüfe, ob der AI-User zu diesem Team hinzugefügt werden kann
            if ($aiUser->canBeAssignedToTeam($team)) {
                // Prüfe, ob der AI-User noch nicht im Team ist
                if (!$team->users()->where('users.id', $aiUser->id)->exists()) {
                    $availableUsers->push($aiUser);
                }
            }
        }

        $this->availableAiUsersToAdd = $availableUsers;
    }

    public function createAiUser()
    {
        $team = $this->team ?? auth()->user()?->currentTeamRelation;
        if (!$team) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Kein Team ausgewählt.',
            ]);
            return;
        }

        // Prüfe, ob User Owner oder Admin ist
        $userRole = $team->users()->where('user_id', auth()->id())->first()?->pivot->role;
        if (!in_array($userRole, [TeamRole::OWNER->value, TeamRole::ADMIN->value])) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Nur Owner oder Admin können AI-User erstellen.',
            ]);
            return;
        }

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
        $this->loadAvailableAiUsersToAdd();
        $this->team = $team->fresh();
        $this->loadCanAddUsers();

        // Formular schließen (Alpine.js Event)
        $this->dispatch('ai-user-created');

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'AI-User erfolgreich erstellt und zum Team hinzugefügt.',
        ]);
    }

    public function addAiUserToTeam(int $userId): void
    {
        $team = $this->team ?? auth()->user()?->currentTeamRelation;
        if (!$team) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Kein Team ausgewählt.',
            ]);
            return;
        }

        // Prüfe, ob User Owner oder Admin ist
        $userRole = $team->users()->where('user_id', auth()->id())->first()?->pivot->role;
        if (!in_array($userRole, [TeamRole::OWNER->value, TeamRole::ADMIN->value])) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Nur Owner oder Admin können AI-User hinzufügen.',
            ]);
            return;
        }

        $aiUser = User::find($userId);
        if (!$aiUser || !$aiUser->isAiUser()) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'AI-User nicht gefunden.',
            ]);
            return;
        }

        // Prüfe, ob der AI-User zu diesem Team hinzugefügt werden kann
        if (!$aiUser->canBeAssignedToTeam($team)) {
            $this->dispatch('notice', [
                'type' => 'error',
                'message' => 'Dieser AI-User kann diesem Team nicht zugewiesen werden (nur Home-Team oder Kind-Teams erlaubt).',
            ]);
            return;
        }

        // Prüfe, ob der AI-User bereits im Team ist
        if ($team->users()->where('users.id', $aiUser->id)->exists()) {
            $this->dispatch('notice', [
                'type' => 'warning',
                'message' => 'AI-User ist bereits im Team.',
            ]);
            return;
        }

        // Füge den AI-User zum Team hinzu
        $team->users()->attach($aiUser, ['role' => TeamRole::MEMBER->value]);

        // Listen neu laden
        $this->loadAiUsers();
        $this->loadAvailableAiUsersToAdd();
        $this->team = $team->fresh();
        $this->loadCanAddUsers();

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'AI-User erfolgreich zum Team hinzugefügt.',
        ]);
    }

    public function removeAiUser(int $userId): void
    {
        $team = $this->team ?? auth()->user()?->currentTeamRelation;
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
        $this->loadAvailableAiUsersToAdd();
        $this->team = $team->fresh();
        $this->loadCanAddUsers();

        $this->dispatch('notice', [
            'type' => 'success',
            'message' => 'AI-User erfolgreich entfernt.',
        ]);
    }

    public function loadTeamAiModels(): void
    {
        $this->teamModelToggles = [];
        $this->teamAiModels = [];
        $this->teamModelMessage = null;

        if (!Schema::hasTable('core_ai_models') || !class_exists(CoreAiModel::class)) {
            return;
        }

        try {
            $this->teamAiModels = CoreAiModel::query()
                ->with('provider')
                ->where('is_active', true)
                ->where('is_deprecated', false)
                ->orderBy('provider_id')
                ->orderBy('model_id')
                ->get();
        } catch (\Exception $e) {
            $this->teamAiModels = collect([]);
            return;
        }

        $team = $this->team ?? auth()->user()?->currentTeamRelation;
        if (!$team) {
            foreach ($this->teamAiModels as $m) {
                $this->teamModelToggles[(int)$m->id] = true;
            }
            return;
        }

        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;
        $records = TeamCoreAiModel::where('scope_team_id', $rootTeam->id)->get();

        if ($records->isEmpty()) {
            foreach ($this->teamAiModels as $m) {
                $this->teamModelToggles[(int)$m->id] = true;
            }
        } else {
            $enabledMap = $records->pluck('is_enabled', 'core_ai_model_id')->all();
            foreach ($this->teamAiModels as $m) {
                $this->teamModelToggles[(int)$m->id] = (bool)($enabledMap[(int)$m->id] ?? false);
            }
        }
    }

    public function canManageAiModels(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        $team = $user->currentTeam ?? $user->currentTeamRelation;
        if (!$team) return false;
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;
        return $rootTeam->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', TeamRole::OWNER->value)
            ->exists();
    }

    public function hasTeamModelRecords(): bool
    {
        $team = $this->team ?? auth()->user()?->currentTeamRelation;
        if (!$team) return false;
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;
        return TeamCoreAiModel::where('scope_team_id', $rootTeam->id)->exists();
    }

    public function toggleTeamModel(int $coreAiModelId): void
    {
        if (!$this->canManageAiModels()) {
            return;
        }

        $user = auth()->user();
        $team = $this->team ?? $user?->currentTeamRelation;
        if (!$team) {
            return;
        }

        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        $currentEnabled = $this->teamModelToggles[$coreAiModelId] ?? true;
        $newEnabled = !$currentEnabled;

        TeamCoreAiModel::updateOrCreate(
            [
                'scope_team_id' => $rootTeam->id,
                'core_ai_model_id' => $coreAiModelId,
            ],
            [
                'is_enabled' => $newEnabled,
                'created_by_user_id' => $user->id,
            ]
        );

        $this->teamModelToggles[$coreAiModelId] = $newEnabled;
    }

    public function resetTeamModels(): void
    {
        if (!$this->canManageAiModels()) {
            return;
        }

        $user = auth()->user();
        $team = $this->team ?? $user?->currentTeamRelation;
        if (!$team) {
            return;
        }

        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        TeamCoreAiModel::where('scope_team_id', $rootTeam->id)->delete();

        foreach ($this->teamModelToggles as $id => $val) {
            $this->teamModelToggles[$id] = true;
        }

        $this->teamModelMessage = 'Team-Filter zurückgesetzt: alle Modelle verfügbar.';
    }

    public function render()
    {
        return view('platform::livewire.modal-team');
    }
}