<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Team;

class TeamController extends Controller
{
    public function create()
    {
        $user = Auth::user();

        // Parent-Teams: nur Root-Teams, bei denen der User Owner/Admin ist
        $availableParentTeams = $user?->teams()
            ->whereNull('teams.parent_team_id')
            ->wherePivotIn('role', [TeamRole::OWNER->value, TeamRole::ADMIN->value])
            ->orderBy('teams.name')
            ->pluck('teams.name', 'teams.id')
            ->toArray() ?? [];

        return view('platform::teams.create', [
            'availableParentTeams' => $availableParentTeams,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_if(! $user, 401);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_team_id' => [
                'nullable',
                'integer',
                'exists:teams,id',
                function ($attribute, $value, $fail) use ($user) {
                    if (! $value) {
                        return;
                    }

                    $parentTeam = Team::find($value);
                    if (! $parentTeam) {
                        $fail('Das ausgewählte Parent-Team existiert nicht.');
                        return;
                    }

                    // Nur Root-Teams können Parent sein
                    if ($parentTeam->parent_team_id !== null) {
                        $fail('Nur Root-Teams können als Parent-Team verwendet werden.');
                        return;
                    }

                    // Zugriff: User muss Mitglied sein
                    if (! $parentTeam->users()->where('user_id', $user->id)->exists()) {
                        $fail('Du hast keinen Zugriff auf das ausgewählte Parent-Team.');
                        return;
                    }

                    // Nur Owner/Admin dürfen Kind-Teams erstellen
                    $role = $parentTeam->users()->where('user_id', $user->id)->first()?->pivot->role;
                    if (! in_array($role, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true)) {
                        $fail('Nur Owner oder Admin können Kind-Teams erstellen.');
                        return;
                    }
                },
            ],
        ]);

        $team = Team::create([
            'name' => $data['name'],
            'user_id' => $user->id,
            'parent_team_id' => $data['parent_team_id'] ?? null,
            'personal_team' => false,
        ]);

        $team->users()->attach($user->id, ['role' => TeamRole::OWNER->value]);

        // Nach Erstellung direkt auf das neue Team wechseln
        $user->current_team_id = $team->id;
        $user->save();
        session(['switching_team' => true]);

        return redirect()->route('platform.dashboard')
            ->with('status', 'Team erfolgreich erstellt.');
    }
}


