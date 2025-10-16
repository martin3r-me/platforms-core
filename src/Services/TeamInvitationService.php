<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\User;

class TeamInvitationService
{
    /**
     * Akzeptiere eine Einladung per Token und verknüpfe den Nutzer mit dem Team.
     */
    public function acceptByToken(string $token, User $user): bool
    {
        $invitation = TeamInvitation::query()
            ->whereNull('accepted_at')
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            return false;
        }

        return $this->attachUserToTeam($invitation, $user);
    }

    /**
     * Akzeptiere alle offenen Einladungen für die E-Mail des Nutzers (Auto-Akzept bei Login/SSO).
     */
    public function acceptAllForUser(User $user): int
    {
        if (! $user->email) {
            return 0;
        }

        $openInvites = TeamInvitation::query()
            ->whereNull('accepted_at')
            ->where('email', $user->email)
            ->get();

        $accepted = 0;
        foreach ($openInvites as $invitation) {
            if ($this->attachUserToTeam($invitation, $user)) {
                $accepted++;
            }
        }

        return $accepted;
    }

    private function attachUserToTeam(TeamInvitation $invitation, User $user): bool
    {
        $team = Team::find($invitation->team_id);
        if (! $team) {
            // Einladung verwerfen, falls Team nicht existiert
            $invitation->delete();
            return false;
        }

        try {
            DB::transaction(function () use ($team, $user, $invitation) {
                // Nutzer dem Team hinzufügen (ohne zu duplizieren)
                $team->users()->syncWithoutDetaching([
                    $user->id => ['role' => $invitation->role ?: TeamRole::MEMBER->value],
                ]);

                $invitation->accepted_at = now();
                $invitation->save();
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Team invitation accept failed', [
                'invitation_id' => $invitation->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}


