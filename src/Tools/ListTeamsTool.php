<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;

/**
 * Tool zum Auflisten aller Teams, denen der User angehört
 * 
 * Ermöglicht es der AI, alle verfügbaren Teams zu sehen und dem User
 * bei der Auswahl zu helfen.
 */
class ListTeamsTool implements ToolContract
{
    use HasStandardGetOperations;
    public function getName(): string
    {
        return 'core.teams.GET';
    }

    public function getDescription(): string
    {
        return 'GET /teams?filters=[...]&search=...&sort=[...]&include_personal={bool} - Listet Teams auf, denen der aktuelle User angehört. REST-Parameter: filters (optional, array) - Filter-Array mit field, op, value. search (optional, string) - Suchbegriff. sort (optional, array) - Sortierung. include_personal (optional, boolean) - persönliche Teams einbeziehen. limit/offset (optional) - Pagination. RUF DIESES TOOL AUF, wenn der Nutzer explizit nach Teams fragt. WICHTIG: Die meisten Tools verwenden automatisch das aktuelle Team - du musst dieses Tool NICHT aufrufen, bevor du andere Tools verwendest.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'include_personal' => [
                        'type' => 'boolean',
                        'description' => 'REST-Parameter (optional): Soll auch persönliche Teams angezeigt werden? Beispiel: include_personal=true. Standard: true. Alternativ: nutze filters mit field="is_personal" und op="eq".'
                    ]
                ]
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTHENTICATION_REQUIRED', 'User must be authenticated to list teams.');
            }
            
            // Prüfe ob User die teams()-Relationship hat
            if (!method_exists($context->user, 'teams')) {
                return ToolResult::error('RELATIONSHIP_NOT_FOUND', 'User model hat keine teams()-Relationship.');
            }

            // Query aufbauen (über Relationship)
            $query = $context->user->teams()->with('parentTeam');
            
            // Standard-Operationen anwenden
            // Hinweis: Bei Relationships müssen wir vorsichtig sein - einige Filter funktionieren direkt
            $this->applyStandardFilters($query, $arguments, [
                'name', 'is_personal', 'parent_team_id', 'created_at'
            ]);
            
            // Legacy: include_personal (für Backwards-Kompatibilität)
            $includePersonal = $arguments['include_personal'] ?? true;
            if (!$includePersonal) {
                $query->where('personal_team', false);
            }
            
            $this->applyStandardSearch($query, $arguments, ['name']);
            
            $this->applyStandardSort($query, $arguments, [
                'name', 'created_at', 'updated_at'
            ], 'name', 'asc');
            
            $this->applyStandardPagination($query, $arguments);
            
            // Teams holen
            $teams = $query->get();

            // Teams formatieren
            $teamsList = $teams->map(function($team) use ($context) {
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

