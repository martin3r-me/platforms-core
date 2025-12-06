<?php

namespace Platform\Core\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Platform\Core\Models\TeamInvitation;
use Platform\Core\Enums\TeamRole;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public TeamInvitation $invitation;

    public function __construct(TeamInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function build(): self
    {
        $team = $this->invitation->team;
        $teamName = $team?->name ?? 'Team';
        $acceptUrl = url('/invitations/accept/' . $this->invitation->token);

        $owner = $team?->users()
            ->wherePivot('role', TeamRole::OWNER->value)
            ->first();

        return $this
            ->subject("Einladung zum Team {$teamName}")
            ->view('platform::emails.team-invitation', [
                'teamName' => $teamName,
                'acceptUrl' => $acceptUrl,
                'inviterName' => $owner->name ?? config('app.name', 'Platform'),
            ]);
    }
}

