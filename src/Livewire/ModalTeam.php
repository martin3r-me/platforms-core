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


    public function render()
    {
        return view('platform::livewire.modal-team', [
            'team' => $this->team,
            'roles' => TeamRole::cases(),
        ]);
    }
}