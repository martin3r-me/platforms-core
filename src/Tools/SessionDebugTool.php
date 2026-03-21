<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\McpSession;

/**
 * Diagnostik-Tool: Zeigt alle aktiven MCP-Sessions des Users
 *
 * Ermöglicht die Überprüfung, ob die Team-Trennung zwischen
 * verschiedenen MCP-Sessions korrekt funktioniert.
 */
class SessionDebugTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.session.debug';
    }

    public function getDescription(): string
    {
        return 'Zeigt alle aktiven MCP-Sessions des aktuellen Users mit Team-Zustand. Nützlich zur Diagnose, ob die Team-Trennung zwischen verschiedenen Sessions korrekt funktioniert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_all_users' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Zeigt Sessions aller User (nur für Admins). Standard: false',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $includeAllUsers = $arguments['include_all_users'] ?? false;
            $currentSessionId = $context->metadata['mcp_session_id'] ?? null;

            // Sessions abfragen (letzte 24h, sortiert nach Aktivität)
            $query = McpSession::where('last_activity_at', '>=', now()->subHours(24))
                ->orderByDesc('last_activity_at');

            if (!$includeAllUsers) {
                $query->where('user_id', $user->id);
            }

            $sessions = $query->get();

            // UI-Team des Users ermitteln
            $uiTeam = null;
            if (method_exists($user, 'currentTeamRelation') && $user->currentTeamRelation) {
                $uiTeam = $user->currentTeamRelation;
            } elseif (method_exists($user, 'currentTeam')) {
                $uiTeam = $user->currentTeam;
            }

            // Aktuelle Session Details
            $currentSession = null;
            $allSessions = [];

            foreach ($sessions as $session) {
                $team = $session->team;
                $isCurrent = $currentSessionId && $session->id === $currentSessionId;

                $sessionData = [
                    'id' => substr($session->id, 0, 12) . '...',
                    'team_id' => $team?->id,
                    'team_name' => $team?->name,
                    'last_activity_at' => $session->last_activity_at?->toIso8601String(),
                    'is_current' => $isCurrent,
                ];

                if (!$team && $uiTeam) {
                    $sessionData['team_fallback'] = "{$uiTeam->name} (from UI)";
                }

                if ($includeAllUsers) {
                    $sessionData['user_id'] = $session->user_id;
                }

                if ($isCurrent) {
                    $currentSession = [
                        'id' => substr($session->id, 0, 12) . '...',
                        'team_id' => $team?->id,
                        'team_name' => $team?->name,
                        'source' => $team ? 'mcp_session_override' : 'user_current_fallback',
                        'created_at' => $session->created_at?->toIso8601String(),
                        'last_activity_at' => $session->last_activity_at?->toIso8601String(),
                    ];
                }

                $allSessions[] = $sessionData;
            }

            // Info-Text generieren
            $sessionCount = count($allSessions);
            $overrideCount = collect($allSessions)->whereNotNull('team_id')->count();
            $info = "{$sessionCount} aktive Session(s) in den letzten 24h.";
            if ($currentSession) {
                $source = $currentSession['source'] === 'mcp_session_override'
                    ? "nutzt Team-Override auf '{$currentSession['team_name']}'"
                    : "nutzt UI-Fallback" . ($uiTeam ? " '{$uiTeam->name}'" : '');
                $info .= " Aktuelle Session {$source}.";
            }
            if ($overrideCount > 0) {
                $info .= " {$overrideCount} Session(s) mit Team-Override.";
            }

            $result = [
                'current_session' => $currentSession,
                'all_user_sessions' => $allSessions,
                'ui_team' => $uiTeam ? [
                    'id' => $uiTeam->id,
                    'name' => $uiTeam->name,
                ] : null,
                'team_context_override_active' => $currentSession && $currentSession['source'] === 'mcp_session_override',
                'session_count' => $sessionCount,
                'info' => $info,
            ];

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen der Session-Daten: ' . $e->getMessage());
        }
    }
}
