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

    protected $listeners = ['open-modal-team' => 'openModal'];

    public function mount()
    {
        $this->user = auth()->user()->toArray();
        $this->loadTeams();
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

    public function render()
    {
        return view('platform::livewire.modal-team');
    }
}