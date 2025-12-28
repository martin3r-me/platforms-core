<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Tool zum Auflisten aller Teams, denen der User angehört
 * 
 * Ermöglicht es der AI, alle verfügbaren Teams zu sehen und dem User
 * bei der Auswahl zu helfen.
 */
class ListTeamsTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.teams.list';
    }

    public function getDescription(): string
    {
        return 'Listet alle Teams auf, denen der aktuelle User angehört. Nutze dieses Tool, wenn der Nutzer nach Teams fragt, ein Team auswählen möchte, oder wenn du wissen musst, in welchem Team etwas erstellt werden soll.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_personal' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soll auch persönliche Teams (personal_team = true) angezeigt werden? Standard: true'
                ]
            ],
            'required' => []
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTHENTICATION_REQUIRED', 'User must be authenticated to list teams.');
            }

            $includePersonal = $arguments['include_personal'] ?? true;

            // Alle Teams des Users holen
            $teams = $context->user->teams()
                ->with('parentTeam')
                ->orderBy('name')
                ->get();

            // Persönliche Teams filtern, falls nicht gewünscht
            if (!$includePersonal) {
                $teams = $teams->filter(function($team) {
                    return !($team->personal_team ?? false);
                });
            }

            // Teams formatieren
            $teamsList = $teams->map(function($team) {
                $parentTeam = $team->parentTeam;
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'is_personal' => $team->personal_team ?? false,
                    'parent_team_id' => $team->parent_team_id,
                    'parent_team_name' => $parentTeam?->name,
                    'is_current' => $context->team && $context->team->id === $team->id,
                ];
            })->values()->toArray();

            return ToolResult::success([
                'teams' => $teamsList,
                'count' => count($teamsList),
                'current_team_id' => $context->team?->id,
                'current_team_name' => $context->team?->name,
                'message' => count($teamsList) > 0 
                    ? count($teamsList) . ' Team(s) gefunden.'
                    : 'Keine Teams gefunden.'
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Teams: ' . $e->getMessage());
        }
    }
}

