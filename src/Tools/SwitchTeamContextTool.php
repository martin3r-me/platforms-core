<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Mcp\McpSessionTeamManager;
use Platform\Core\Models\Team;

/**
 * LLM Tool zum Wechseln des aktiven Team-Kontexts
 *
 * Ermöglicht es LLM-Clients (Claude, Agents etc.), programmatisch
 * in ein anderes Team zu wechseln. Nach dem Switch laufen alle
 * Tool-Calls im Kontext des neuen Teams.
 *
 * Der Switch gilt nur für die MCP-Session (in-memory), der
 * UI-Kontext des Users bleibt unberührt.
 */
class SwitchTeamContextTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.team.switch';
    }

    public function getDescription(): string
    {
        return 'Wechselt den aktiven Team-Kontext für diese MCP-Session. Nach dem Switch arbeiten alle Tools (Planner, Helpdesk etc.) im Kontext des neuen Teams. Der Switch gilt nur für die Session und ändert nicht den UI-Kontext des Users. Nutze "core.teams.GET" um verfügbare Teams zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Ziel-Teams, in das gewechselt werden soll. Der User muss Mitglied dieses Teams sein. Nutze "core.teams.GET" um verfügbare Team-IDs zu sehen.',
                ],
            ],
            'required' => ['team_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? null;
            if ($teamId === null || $teamId === '' || $teamId === 0) {
                return ToolResult::error('VALIDATION_ERROR', 'team_id ist erforderlich. Nutze "core.teams.GET" um verfügbare Teams zu sehen.');
            }

            $teamId = (int) $teamId;
            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'team_id muss eine positive Zahl sein.');
            }

            // Ziel-Team laden
            $targetTeam = Team::find($teamId);
            if (!$targetTeam) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Das Team mit ID ' . $teamId . ' wurde nicht gefunden. Nutze "core.teams.GET" um verfügbare Teams zu sehen.');
            }

            // Policy: User muss Mitglied des Ziel-Teams sein
            $isMember = $user->teams()->where('teams.id', $targetTeam->id)->exists();
            if (!$isMember) {
                return ToolResult::error('ACCESS_DENIED', 'Du bist kein Mitglied des Teams "' . $targetTeam->name . '" (ID: ' . $teamId . '). Du kannst nur in Teams wechseln, in denen du Mitglied bist. Nutze "core.teams.GET" um deine Teams zu sehen.');
            }

            // Session-ID ermitteln
            $sessionId = McpSessionTeamManager::resolveSessionId();
            if (!$sessionId) {
                return ToolResult::error('SESSION_ERROR', 'Konnte keine MCP-Session ermitteln. Ist der User authentifiziert?');
            }

            // Vorheriges Team für Response merken
            $previousTeam = $context->team;
            $previousTeamOverrideId = McpSessionTeamManager::getTeamOverrideId($sessionId);

            // Team-Override setzen
            McpSessionTeamManager::setTeamOverride($sessionId, $targetTeam->id);

            // Membership-Rolle abrufen
            $membership = $user->teams()->where('teams.id', $targetTeam->id)->first();
            $role = $membership?->pivot?->role ?? null;

            return ToolResult::success([
                'message' => 'Team-Kontext erfolgreich gewechselt zu "' . $targetTeam->name . '".',
                'team' => [
                    'id' => $targetTeam->id,
                    'name' => $targetTeam->name,
                    'role' => $role,
                ],
                'previous_team' => $previousTeam ? [
                    'id' => $previousTeam->id,
                    'name' => $previousTeam->name,
                    'was_override' => $previousTeamOverrideId !== null,
                ] : null,
                'info' => 'Alle nachfolgenden Tool-Calls arbeiten jetzt im Kontext des Teams "' . $targetTeam->name . '". Der UI-Kontext des Users bleibt unverändert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Team-Kontext-Switch: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'team', 'context', 'switch'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
            'related_tools' => ['core.teams.GET', 'core.context.GET'],
        ];
    }
}
