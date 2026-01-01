<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;

/**
 * Tool zum Auflisten aller Nutzer eines Teams
 * 
 * Ermöglicht es der AI, alle Mitglieder eines Teams zu sehen.
 */
class ListTeamUsersTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.teams.users.list';
    }

    public function getDescription(): string
    {
        return 'Listet alle Nutzer/Mitglieder eines Teams auf. RUF DIESES TOOL SOFORT UND AUTOMATISCH AUF, wenn der Nutzer nach Team-Mitgliedern fragt (z.B. "zeige mir alle Nutzer des Teams", "welche Personen sind im Team", "wer gehört zum Team", "alle Mitglieder anzeigen"). Nutze dieses Tool auch, wenn du wissen musst, welche Nutzer in einem Team sind, bevor du Aufgaben oder Projekte erstellst. Wenn keine Team-ID angegeben ist, wird das aktuelle Team aus dem Kontext verwendet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID des Teams, dessen Nutzer aufgelistet werden sollen. Wenn nicht angegeben, wird das aktuelle Team aus dem Kontext verwendet. Nutze "core.teams.list" um Teams zu finden.'
                ]
            ],
            'required' => []
        ];
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
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden. Nutze das Tool "core.teams.list" um alle verfügbaren Teams zu sehen.');
            }

            // Team finden
            $team = Team::find($teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Das angegebene Team wurde nicht gefunden. Nutze das Tool "core.teams.list" um alle verfügbaren Teams zu sehen.');
            }

            // Prüfe, ob User Zugriff auf dieses Team hat
            $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team. Nutze das Tool "core.teams.list" um alle verfügbaren Teams zu sehen.');
            }

            // Alle Nutzer des Teams holen
            $users = $team->users()
                ->orderBy('name')
                ->get();

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

