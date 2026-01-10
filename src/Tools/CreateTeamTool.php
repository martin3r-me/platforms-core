<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Team;

/**
 * core.teams.POST
 *
 * Entspricht der UI-Logik aus Livewire ModalTeam::createTeam():
 * - Root-Team erstellen: erlaubt
 * - Kind-Team erstellen: parent_team_id muss ein Root-Team sein, User muss Mitglied sein und role in [owner, admin]
 * - Creator wird Owner (team_user pivot), team.user_id wird auf Creator gesetzt
 */
class CreateTeamTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.teams.POST';
    }

    public function getDescription(): string
    {
        return 'POST /core/teams - Legt ein neues Team an (optional als Kind-Team eines Root-Teams). Parameter: name (required), parent_team_id (optional). Für Kind-Teams gilt: parent_team_id muss Root-Team sein und du musst Owner/Admin dieses Parent-Teams sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Teams (ERFORDERLICH).',
                ],
                'parent_team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines Root-Teams, unter dem das neue Team als Kind-Team angelegt wird. Nutze core.teams.GET, um Root-Teams zu finden.',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }
            if (mb_strlen($name) > 255) {
                return ToolResult::error('VALIDATION_ERROR', 'name ist zu lang (max 255 Zeichen).');
            }

            $parentTeamId = $arguments['parent_team_id'] ?? null;
            if ($parentTeamId === 0 || $parentTeamId === '0' || $parentTeamId === '' || $parentTeamId === 'null') {
                $parentTeamId = null;
            }
            if ($parentTeamId !== null) {
                $parentTeamId = (int)$parentTeamId;
                if ($parentTeamId <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'parent_team_id muss eine positive Zahl sein.');
                }

                $parentTeam = Team::find($parentTeamId);
                if (!$parentTeam) {
                    return ToolResult::error('PARENT_TEAM_NOT_FOUND', 'Das ausgewählte Parent-Team existiert nicht.');
                }

                // Nur Root-Teams können Parent sein
                if ($parentTeam->parent_team_id !== null) {
                    return ToolResult::error('VALIDATION_ERROR', 'Nur Root-Teams können als Parent-Team verwendet werden.');
                }

                // User muss Mitglied des Parent-Teams sein
                $membership = $parentTeam->users()->where('user_id', $user->id)->first();
                if (!$membership) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf das ausgewählte Parent-Team.');
                }

                // Nur Owner/Admin kann Kind-Teams erstellen
                $role = $membership->pivot->role ?? null;
                if (!in_array($role, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true)) {
                    return ToolResult::error('ACCESS_DENIED', 'Nur Owner oder Admin können Kind-Teams erstellen.');
                }
            }

            $team = Team::create([
                'name' => $name,
                'user_id' => $user->id,
                'parent_team_id' => $parentTeamId,
                'personal_team' => false,
            ]);

            // Creator ist Owner
            $team->users()->attach($user->id, ['role' => TeamRole::OWNER->value]);

            return ToolResult::success([
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'user_id' => $team->user_id,
                    'parent_team_id' => $team->parent_team_id,
                    'personal_team' => (bool)$team->personal_team,
                    'created_at' => $team->created_at?->toIso8601String(),
                ],
                'message' => 'Team erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Teams: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'team', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}


