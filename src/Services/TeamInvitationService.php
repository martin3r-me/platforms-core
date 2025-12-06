<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\User;
use Platform\Core\Mail\TeamInvitationMail;

class TeamInvitationService
{
    /**
     * Erstellt Einladungen, versendet E-Mails (Postmark, falls konfiguriert)
     * und gibt erstelle/übersprungene Einladungen zurück.
     *
     * @param Team $team
     * @param array<int, string> $emails
     * @param string $role
     * @return array{created: array<int, TeamInvitation>, skipped: array<int, array{email: string, reason: string}>}
     */
    public function createInvitations(Team $team, array $emails, string $role): array
    {
        $normalized = collect($emails)
            ->map(fn ($email) => Str::lower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $validator = Validator::make(
            [
                'emails' => $normalized->all(),
                'role' => $role,
            ],
            [
                'emails' => ['required', 'array', 'min:1', 'max:50'],
                'emails.*' => [
                    'required',
                    'email',
                    function ($attribute, $value, $fail) use ($team) {
                        if ($team->users()->where('email', $value)->exists()) {
                            $fail("{$value} ist bereits Mitglied des Teams.");
                        }
                    },
                ],
                'role' => ['required', Rule::in([
                    TeamRole::OWNER->value,
                    TeamRole::ADMIN->value,
                    TeamRole::MEMBER->value,
                    'viewer',
                ])],
            ],
            [],
            [
                'emails.*' => 'E-Mail-Adresse',
            ]
        );

        $validator->validate();

        $result = [
            'created' => [],
            'skipped' => [],
        ];

        foreach ($normalized as $email) {
            if (TeamInvitation::query()
                ->where('team_id', $team->id)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->exists()) {
                $result['skipped'][] = ['email' => $email, 'reason' => 'already_invited'];
                continue;
            }

            if ($team->users()->where('email', $email)->exists()) {
                $result['skipped'][] = ['email' => $email, 'reason' => 'already_member'];
                continue;
            }

            $invitation = TeamInvitation::create([
                'team_id' => $team->id,
                'email'   => $email,
                'token'   => Str::uuid(),
                'role'    => $role,
            ]);

            $this->sendInvitationMail($invitation);
            $result['created'][] = $invitation;
        }

        return $result;
    }

    /**
     * Versendet die Einladung per E-Mail.
     */
    public function sendInvitationMail(TeamInvitation $invitation): void
    {
        try {
            $this->mailer()
                ->to($invitation->email)
                ->send(new TeamInvitationMail($invitation));
        } catch (\Throwable $e) {
            Log::error('Team invitation mail failed', [
                'invitation_id' => $invitation->id ?? null,
                'email' => $invitation->email ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Wählt bevorzugt den Postmark-Mailer, fällt sonst auf den Default zurück.
     */
    protected function mailer()
    {
        try {
            if (config('mail.mailers.postmark') !== null) {
                return Mail::mailer('postmark');
            }
        } catch (\Throwable $e) {
            // Fallback unten
        }

        return Mail::mailer(config('mail.default'));
    }

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


