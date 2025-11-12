<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Models\TeamUserLastModule;
use Platform\Core\Enums\TeamRole;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Platform\Core\Models\TeamBillableUsage;
use Carbon\Carbon;

class ModalModules extends Component
{
    public $modalShow;
    public $showMatrix;
    public $modules;
    public $matrixUsers;
    public $matrixModules;
    public $userModuleMap = [];

    // Team/User/Payment Konsolidierung
    public $team;
    public $user;
    public $allTeams = [];
    public $newTeamName = '';
    public $newParentTeamId = null;
    public $availableParentTeams = [];
    public $inviteEmail = '';
    public $inviteRole = TeamRole::MEMBER;
    public $memberRoles = [];
    public $addingPayment = false;
    public $mollieKey = null;


    #[On('open-modal-modules')] 
    public function openModalModules()
    {
        $this->modalShow = true;
    }

    

    public function mount()
    {
        $this->modalShow = false;
        $this->user = Auth::user();
        $this->allTeams = $this->user?->teams()->get() ?? collect();
        $this->loadTeam();
        $this->loadAvailableParentTeams();
        $this->mollieKey = env('MOLLIE_KEY');

        $user = $this->user;
        $baseTeam = $user->currentTeamRelation; // Basis-Team (nicht dynamisch)
        if (!$baseTeam) {
            $this->modules = collect();
            $this->matrixUsers = collect();
            $this->matrixModules = collect();
            $this->userModuleMap = [];
            return;
        }
        
        $rootTeam = $baseTeam->getRootTeam();
        $rootTeamId = $rootTeam->id;
        $baseTeamId = $baseTeam->id;

        // Hole alle sichtbaren Module (z. B. nach Guard gefiltert)
        $modules = PlatformCore::getVisibleModules();

        // Filtere Module nach Berechtigung
        $this->modules = collect($modules)->filter(function($module) use ($user, $baseTeam, $baseTeamId, $rootTeam, $rootTeamId) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $module['key'])->first();
            if (!$moduleModel) return false;

            // Für Parent-Module: Rechte aus Root-Team prüfen
            // Für Single-Module: Rechte aus aktuellem Team prüfen
            if ($moduleModel->isRootScoped()) {
                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $rootTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $rootTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            } else {
                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $baseTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $baseTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            }

            return $userAllowed || $teamAllowed;
        })->values();

        // Matrix-Vorbereitung wie gehabt
        $this->matrixUsers = \Platform\Core\Models\User::whereHas('teams', function($q) use ($baseTeamId) {
            $q->where('teams.id', $baseTeamId);
        })->get();

        $this->matrixModules = \Platform\Core\Models\Module::all();
        $this->refreshMatrix();

    }

    public function toggleMatrix($userId, $moduleId)
    {
        $user = auth()->user();
        $currentTeam = $user->currentTeamRelation; // Basis-Team (nicht dynamisch)
        if (!$currentTeam) { return; }

        $targetUser = \Platform\Core\Models\User::findOrFail($userId);
        $module = \Platform\Core\Models\Module::findOrFail($moduleId);

        // Für Parent-Module: Nur im Root-Team vergeben
        if ($module->isRootScoped()) {
            $rootTeam = $currentTeam->getRootTeam();
            
            // Prüfen ob wir im Root-Team sind
            if ($currentTeam->id !== $rootTeam->id) {
                // In Kind-Teams können Parent-Module nicht vergeben werden
                return;
            }

            $teamId = $rootTeam->id;
        } else {
            // Für Single-Module: Im aktuellen Team vergeben
            $teamId = $currentTeam->id;
        }

        // Prüfen ob bereits zugewiesen
        $alreadyAssigned = $targetUser->modules()
            ->where('module_id', $moduleId)
            ->wherePivot('team_id', $teamId)
            ->exists();

        if ($alreadyAssigned) {
            // Pivot löschen
            $targetUser->modules()->newPivotStatement()
                ->where('modulable_id', $targetUser->id)
                ->where('modulable_type', \Platform\Core\Models\User::class)
                ->where('module_id', $moduleId)
                ->where('team_id', $teamId)
                ->delete();
        } else {
            // Pivot erstellen
            $targetUser->modules()->attach($moduleId, [
                'role' => null,
                'enabled' => true,
                'guard' => 'web',
                'team_id' => $teamId,
            ]);
        }

        // Matrix neu laden
        $this->refreshMatrix();
    }

    // Setzt alle Modul-IDs, die ein User hat, als Array für den schnellen Zugriff
     // [user_id => [module_id, ...], ...]

    public function refreshMatrix()
    {
        $user = auth()->user();
        $currentTeam = $user->currentTeamRelation; // Basis-Team (nicht dynamisch)
        if (!$currentTeam) {
            $this->matrixUsers = collect();
            $this->matrixModules = collect();
            $this->userModuleMap = [];
            return;
        }

        $teamId = $currentTeam->id;
        $rootTeam = $currentTeam->getRootTeam();
        $rootTeamId = $rootTeam->id;

        // User aus dem aktuellen Team laden
        $this->matrixUsers = \Platform\Core\Models\User::whereHas('teams', function($q) use ($teamId) {
            $q->where('teams.id', $teamId);
        })->get();

        $this->matrixModules = \Platform\Core\Models\Module::all();

        // Build map: user_id => [module_id, ...]
        // Für Parent-Module: Rechte aus Root-Team berücksichtigen
        // Für Single-Module: Rechte aus aktuellem Team berücksichtigen
        $this->userModuleMap = [];
        foreach ($this->matrixUsers as $targetUser) {
            $moduleIds = [];

            foreach ($this->matrixModules as $module) {
                if ($module->isRootScoped()) {
                    // Parent-Modul: Rechte aus Root-Team prüfen
                    $hasPermission = $targetUser->modules()
                        ->where('module_id', $module->id)
                        ->wherePivot('team_id', $rootTeamId)
                        ->wherePivot('enabled', true)
                        ->exists();
                } else {
                    // Single-Modul: Rechte aus aktuellem Team prüfen
                    $hasPermission = $targetUser->modules()
                        ->where('module_id', $module->id)
                        ->wherePivot('team_id', $teamId)
                        ->wherePivot('enabled', true)
                        ->exists();
                }

                if ($hasPermission) {
                    $moduleIds[] = $module->id;
                }
            }

            $this->userModuleMap[$targetUser->id] = $moduleIds;
        }
    }

    public function switchTeam(int $teamId)
    {
        $user = Auth::user();
        if (!$user) { return; }
        
        // Das Modul wird automatisch vom Middleware gespeichert, wenn es erkannt wird

        // Team wechseln
        $user->current_team_id = $teamId;
        $user->save();

        $this->modalShow = false;

        // Zuletzt verwendetes Modul für das neue Team laden
        $lastModuleKey = TeamUserLastModule::getLastModule($user->id, $teamId);
        
        if ($lastModuleKey) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $lastModuleKey)->first();
            if ($moduleModel) {
                $team = $user->currentTeam; // aktualisiert
                $teamAllowed = $team
                    ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                    : false;

                if ($teamAllowed) {
                    // Zum zuletzt verwendeten Modul navigieren
                    return $this->redirect('/' . $lastModuleKey);
                }
            }
        }

        // Versuche auf der aktuellen Seite zu bleiben, wenn das neue Team Zugriff auf das Modul hat
        $currentUrl = request()->fullUrl();
        $moduleKey = request()->segment(1); // z.B. planner, cms, okr, ...

        if (is_string($moduleKey) && strlen($moduleKey) > 0) {
            $moduleModel = \Platform\Core\Models\Module::where('key', $moduleKey)->first();
            if ($moduleModel) {
                $team = $user->currentTeam; // aktualisiert
                $teamAllowed = $team
                    ? $team->modules()->where('module_id', $moduleModel->id)->wherePivot('enabled', true)->exists()
                    : false;

                if ($teamAllowed) {
                    // Harte Weiterleitung auf die aktuelle URL (volle Seite), keine navigate-Redirects
                    return $this->redirect($currentUrl);
                }
            }
        }

        // Fallback: Plattform-Dashboard
        return $this->redirect(route('platform.dashboard'));
    }

    // --- Team/User/Payment Logik (aus ModalTeam) ---
    protected $rules = [
        'team.name' => 'required|string|max:255',
        'user.current_team_id' => 'required|integer',
    ];

    public function loadTeam(): void
    {
        $user = Auth::user();
        $this->team = $user?->currentTeam;
        if ($this->team) {
            $this->memberRoles = $this->team->users->pluck('pivot.role', 'id')->toArray();
        } else {
            $this->memberRoles = [];
        }
    }

    /**
     * Lädt verfügbare Parent-Teams (Root-Teams, zu denen der User Zugriff hat).
     * Nur Root-Teams können als Parent-Teams verwendet werden.
     */
    public function loadAvailableParentTeams(): void
    {
        $user = Auth::user();
        $this->availableParentTeams = Team::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereNull('parent_team_id') // Nur Root-Teams
        ->where('id', '!=', $user->currentTeam?->id) // Aktuelles Team ausschließen
        ->get()
        ->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
            ];
        })
        ->toArray();
    }

    public function updatedTeam($property, $value): void
    {
        $this->validateOnly("team.$property");
        $this->team?->save();
    }

    public function updatedUser($property, $value): void
    {
        $this->validateOnly("user.$property");
        $this->user?->save();
        // Nach Teamwechsel: Daten aktualisieren und Modal schließen
        $this->loadTeam();
        $this->refreshMatrix();
        $this->modalShow = false;
        // Auf die aktuelle Seite zurück, damit Guards/Sidebar etc. korrekt neu laden
        $this->redirect(request()->fullUrl());
    }

    public function inviteToTeam(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'inviteEmail' => 'required|email|unique:team_invitations,email,NULL,id,team_id,' . ($this->team?->id ?? 0),
            'inviteRole' => [
                'required',
                Rule::in([TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value]),
            ],
        ]);

        if (!$this->team) { return; }

        TeamInvitation::create([
            'team_id' => $this->team->id,
            'email'   => $this->inviteEmail,
            'token'   => Str::uuid(),
            'role'    => $this->inviteRole,
        ]);

        $this->inviteEmail = '';
        $this->inviteRole = TeamRole::MEMBER;
        $this->dispatch('team-invited');
    }

    public function createTeam(): void
    {
        $this->validate([
            'newTeamName' => 'required|string|max:255',
            'newParentTeamId' => [
                'nullable',
                'integer',
                'exists:teams,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $user = Auth::user();
                        $parentTeam = Team::find($value);
                        
                        if (!$parentTeam) {
                            $fail('Das ausgewählte Parent-Team existiert nicht.');
                            return;
                        }

                        if ($parentTeam->parent_team_id !== null) {
                            $fail('Nur Root-Teams können als Parent-Team verwendet werden.');
                            return;
                        }

                        if (!$parentTeam->users()->where('user_id', $user->id)->exists()) {
                            $fail('Du hast keinen Zugriff auf das ausgewählte Parent-Team.');
                            return;
                        }

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
            'user_id' => Auth::id(),
            'parent_team_id' => $this->newParentTeamId,
            'personal_team' => false,
        ]);

        $team->users()->attach(Auth::id(), ['role' => TeamRole::OWNER->value]);

        if ($this->user) {
            $this->user->current_team_id = $team->id;
            $this->user->save();
        }

        $this->newTeamName = '';
        $this->newParentTeamId = null;
        $this->loadTeam();
        $this->loadAvailableParentTeams();
    }

    public function updateMemberRole($userId, $newRole): void
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) { return; }
        $validRoles = [TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value, TeamRole::VIEWER->value];
        if (!in_array($newRole, $validRoles)) { return; }
        $this->team->users()->updateExistingPivot($userId, ['role' => $newRole]);
        $this->loadTeam();
        $this->dispatch('member-role-updated');
    }

    public function addPaymentMethod(): void
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) { return; }
        $this->addingPayment = true;
    }

    public function updatePaymentMethod(): void
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) { return; }
        $this->addingPayment = true;
    }

    public function removePaymentMethod(): void
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) { return; }
        $this->team->update([
            'mollie_payment_method_id' => null,
            'payment_method_last_4' => null,
            'payment_method_brand' => null,
            'payment_method_expires_at' => null,
        ]);
        $this->loadTeam();
        $this->dispatch('payment-method-removed');
    }

    public function cancelAddPayment(): void
    {
        $this->addingPayment = false;
    }

    public function savePaymentMethod(string $cardToken): void
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) { return; }

        $secretKey = env('MOLLIE_KEY');
        if (!$secretKey) { return; }

        if (!$this->team->mollie_customer_id) {
            $customerResp = Http::withToken($secretKey)
                ->acceptJson()
                ->post('https://api.mollie.com/v2/customers', [
                    'name' => $this->team->name,
                    'email' => $this->user?->email,
                ]);
            if ($customerResp->failed()) { return; }
            $this->team->mollie_customer_id = $customerResp->json('id');
            $this->team->save();
        }

        $mandateResp = Http::withToken($secretKey)
            ->acceptJson()
            ->post('https://api.mollie.com/v2/customers/' . $this->team->mollie_customer_id . '/mandates', [
                'method' => 'creditcard',
                'cardToken' => $cardToken,
                'consumerName' => $this->team->name,
            ]);
        if ($mandateResp->failed()) { return; }

        $details = $mandateResp->json('details') ?: [];
        $cardLabel = $details['cardLabel'] ?? ($details['cardHolder'] ?? 'card');
        $last4 = $details['cardNumber'] ?? null;
        if (is_string($last4) && strlen($last4) >= 4) { $last4 = substr($last4, -4); } else { $last4 = null; }

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

    private function authorizeEdit(): void
    {
        if (!$this->team || $this->team->user_id !== Auth::id()) {
            abort_if(true, 403, 'Unzulässig');
        }
    }


    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('platform::livewire.modal-modules');
    }
}

