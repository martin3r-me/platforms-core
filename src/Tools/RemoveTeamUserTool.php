<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * Tool zum Entfernen eines Users aus einem Team (Team-Mitgliedschaft).
 *
 * UI-Logik (ModalTeam) wird gespiegelt:
 * - Nur Team-Owner (teams.user_id) darf entfernen.
 * - Letzten Owner nicht entfernen.
 */
class RemoveTeamUserTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.teams.users.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /teams/{team_id}/users/{user_id} - Entfernt einen User aus einem Team. Parameter: team_id (required), user_id (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Team-ID (ERFORDERLICH). Nutze "core.teams.GET" um Teams zu finden.',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'User-ID (ERFORDERLICH). Nutze "core.teams.users.GET" um Team-Mitglieder zu sehen.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bestätigung (z.B. bei Owner-Entfernung).',
                ],
            ],
            'required' => ['team_id', 'user_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? null;
            $userId = $arguments['user_id'] ?? null;
            if (!$teamId || !$userId) {
                return ToolResult::error('VALIDATION_ERROR', 'team_id und user_id sind erforderlich.');
            }

            $team = Team::find($teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Das angegebene Team wurde nicht gefunden.');
            }

            // UI-Logik: nur der Team-Owner darf Mitglieder verwalten
            if ((int)$team->user_id !== (int)$context->user->id) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Mitglieder aus diesem Team entfernen (nur Team-Owner).');
            }

            $targetUser = User::find($userId);
            if (!$targetUser) {
                return ToolResult::error('USER_NOT_FOUND', 'Der zu entfernende User wurde nicht gefunden.');
            }

            $member = $team->users()->where('users.id', $targetUser->id)->first();
            if (!$member) {
                return ToolResult::success([
                    'message' => 'User ist kein Mitglied dieses Teams (nichts zu tun).',
                    'team_id' => (int)$team->id,
                    'team_name' => $team->name,
                    'user_id' => (int)$targetUser->id,
                    'user_name' => $targetUser->name,
                    'removed' => false,
                ]);
            }

            $memberRole = $member->pivot->role ?? null;
            $isMemberOwner = $memberRole === TeamRole::OWNER->value;

            if ($isMemberOwner) {
                $ownerCount = $team->users()->wherePivot('role', TeamRole::OWNER->value)->count();
                if ($ownerCount <= 1) {
                    return ToolResult::error('VALIDATION_ERROR', 'Der letzte Team-Owner kann nicht entfernt werden.');
                }

                if (!($arguments['confirm'] ?? false)) {
                    return ToolResult::error('CONFIRMATION_REQUIRED', 'Der User ist Owner. Bitte bestätige mit confirm: true.');
                }
            }

            DB::transaction(function () use ($team, $targetUser) {
                $team->users()->detach($targetUser->id);
            });

            return ToolResult::success([
                'message' => "User '{$targetUser->name}' wurde aus dem Team '{$team->name}' entfernt.",
                'team_id' => (int)$team->id,
                'team_name' => $team->name,
                'user_id' => (int)$targetUser->id,
                'user_name' => $targetUser->name,
                'removed' => true,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Entfernen des Users aus dem Team: ' . $e->getMessage());
        }
    }
}


