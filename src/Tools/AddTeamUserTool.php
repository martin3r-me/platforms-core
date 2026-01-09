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
 * Tool zum Hinzufügen eines Users zu einem Team (Team-Mitgliedschaft).
 *
 * WICHTIG: orientiert sich an der UI-Logik (ModalTeam):
 * - Nur der Team-Owner (teams.user_id) darf Mitglieder hinzufügen/entfernen bzw. Rollen setzen.
 * - Letzten Owner nicht entfernen (siehe RemoveTeamUserTool).
 */
class AddTeamUserTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.teams.users.POST';
    }

    public function getDescription(): string
    {
        return 'POST /teams/{team_id}/users - Fügt einen User zu einem Team hinzu. Parameter: team_id (required), user_id (required), role (optional: owner|admin|member|viewer; default member).';
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
                    'description' => 'User-ID (ERFORDERLICH).',
                ],
                'role' => [
                    'type' => 'string',
                    'description' => 'Optional: Rolle im Team (owner|admin|member|viewer). Standard: member.',
                    'enum' => [TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value, 'viewer'],
                    'default' => TeamRole::MEMBER->value,
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

            $role = (string)($arguments['role'] ?? TeamRole::MEMBER->value);
            $allowedRoles = [TeamRole::OWNER->value, TeamRole::ADMIN->value, TeamRole::MEMBER->value, 'viewer'];
            if (!in_array($role, $allowedRoles, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültige Rolle. Erlaubt: owner|admin|member|viewer.');
            }

            $team = Team::find($teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Das angegebene Team wurde nicht gefunden.');
            }

            // UI-Logik: nur der Team-Owner darf Mitglieder verwalten
            if ((int)$team->user_id !== (int)$context->user->id) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Mitglieder zu diesem Team hinzufügen (nur Team-Owner).');
            }

            $targetUser = User::find($userId);
            if (!$targetUser) {
                return ToolResult::error('USER_NOT_FOUND', 'Der anzufügende User wurde nicht gefunden.');
            }

            $already = $team->users()->where('users.id', $targetUser->id)->first();
            if ($already) {
                $currentRole = $already->pivot->role ?? null;
                if ($currentRole !== $role) {
                    $team->users()->updateExistingPivot($targetUser->id, ['role' => $role]);
                    $currentRole = $role;
                }

                return ToolResult::success([
                    'message' => "User ist bereits Team-Mitglied. Rolle: {$currentRole}.",
                    'team_id' => (int)$team->id,
                    'team_name' => $team->name,
                    'user_id' => (int)$targetUser->id,
                    'user_name' => $targetUser->name,
                    'role' => $currentRole,
                    'already_member' => true,
                ]);
            }

            DB::transaction(function () use ($team, $targetUser, $role) {
                $team->users()->attach($targetUser->id, ['role' => $role]);
            });

            return ToolResult::success([
                'message' => "User '{$targetUser->name}' wurde dem Team '{$team->name}' als '{$role}' hinzugefügt.",
                'team_id' => (int)$team->id,
                'team_name' => $team->name,
                'user_id' => (int)$targetUser->id,
                'user_name' => $targetUser->name,
                'role' => $role,
                'already_member' => false,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Hinzufügen des Users zum Team: ' . $e->getMessage());
        }
    }
}


