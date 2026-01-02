<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Core\Models\Team;

/**
 * Tool zum Auflisten aller Nutzer eines Teams
 * 
 * Ermöglicht es der AI, alle Mitglieder eines Teams zu sehen.
 */
class ListTeamUsersTool implements ToolContract
{
    use HasStandardGetOperations;
    public function getName(): string
    {
        return 'core.teams.users.GET';
    }

    public function getDescription(): string
    {
        return 'GET /teams/{team_id}/users?filters=[...]&search=...&sort=[...] - Listet Nutzer/Mitglieder eines Teams auf. REST-Parameter: team_id (optional, integer) - wenn nicht angegeben, wird aktuelles Team verwendet. filters (optional, array) - Filter-Array mit field, op, value. search (optional, string) - Suchbegriff. sort (optional, array) - Sortierung mit field, dir. limit/offset (optional) - Pagination.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'REST-Parameter (optional): ID des Teams. Beispiel: team_id=9. Wenn nicht angegeben, wird aktuelles Team aus Kontext verwendet. Nutze "core.teams.GET" um verfügbare Team-IDs zu sehen.'
                    ]
                ]
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Team bestimmen: aus Argumenten oder Context
            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Nutze das Tool "core.teams.GET" um alle verfügbaren Teams zu sehen.');
            }

            // Team finden
            $team = Team::find($teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Das angegebene Team wurde nicht gefunden. Nutze das Tool "core.teams.GET" um alle verfügbaren Teams zu sehen.');
            }

            // Prüfe, ob User Zugriff auf dieses Team hat
            $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team. Nutze das Tool "core.teams.GET" um alle verfügbaren Teams zu sehen.');
            }

            // Query aufbauen (über Relationship)
            $query = $team->users();
            
            // Standard-Operationen anwenden
            $this->applyStandardFilters($query, $arguments, [
                'name', 'email', 'role', 'created_at'
            ]);
            
            $this->applyStandardSearch($query, $arguments, ['name', 'email']);
            
            $this->applyStandardSort($query, $arguments, [
                'name', 'email', 'created_at'
            ], 'name', 'asc');
            
            $this->applyStandardPagination($query, $arguments);
            
            // Nutzer holen
            $users = $query->get();

            // Nutzer formatieren
            $usersList = $users->map(function($user) use ($team, $context) {
                // Hole Rolle aus Pivot-Tabelle
                $pivot = $user->pivot;
                $role = $pivot->role ?? null;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role,
                    'is_current_user' => $context->user && $context->user->id === $user->id,
                ];
            })->values()->toArray();

            return ToolResult::success([
                'users' => $usersList,
                'count' => count($usersList),
                'team_id' => $team->id,
                'team_name' => $team->name,
                'message' => count($usersList) > 0 
                    ? count($usersList) . ' Nutzer im Team gefunden.'
                    : 'Keine Nutzer im Team gefunden.'
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Team-Nutzer: ' . $e->getMessage());
        }
    }
}

