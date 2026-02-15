<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Mcp\McpSessionTeamManager;
use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;

/**
 * Tool zum Abrufen des aktuellen Kontexts
 *
 * MCP-Pattern: Das Sprachmodell kann diesen Tool auf Bedarf nutzen,
 * um den aktuellen Kontext (User, Team, Modul, Route) zu erfahren.
 * Statt alles immer mitzuschicken, fragt das Modell bei Bedarf nach.
 *
 * Unterstützt optionalen team_id Parameter für cross-team Abfragen.
 * Der User darf nur Teams abfragen, in denen er Mitglied ist.
 */
class GetContextTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.GET';
    }

    public function getDescription(): string
    {
        return 'Gibt den aktuellen Kontext zurück (User, Team, Modul, Route, URL). Nutze dieses Tool, wenn du Informationen über den aktuellen Kontext benötigst. Rufe dieses Tool automatisch auf, wenn der Nutzer nach seinem aktuellen Kontext fragt oder wenn du wissen musst, in welchem Modul/Team der Nutzer sich befindet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines Teams, dessen Kontext (Module, Members) abgerufen werden soll. Der User muss Mitglied dieses Teams sein. Wenn nicht angegeben, wird das aktuelle Team verwendet. Nutze "core.teams.GET" um verfügbare Team-IDs zu sehen.'
                ],
                'include_metadata' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soll auch erweiterte Metadaten (Zeit, Zeitzone, etc.) enthalten sein? Standard: true'
                ],
                'include_modules' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soll eine kompakte Modul-Übersicht (allowed_modules/denied_modules) enthalten sein? Standard: true'
                ],
                'include_members' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soll eine Liste der Team-Mitglieder enthalten sein? Standard: false'
                ],
            ],
            'required' => []
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $includeMetadata = $arguments['include_metadata'] ?? true;
            $includeModules = $arguments['include_modules'] ?? true;
            $includeMembers = $arguments['include_members'] ?? false;
            $requestedTeamId = $arguments['team_id'] ?? null;

            $user = $context->user;

            // Team bestimmen: aus Argumenten oder Context
            $targetTeam = null;
            $isCurrentTeam = true;

            if ($requestedTeamId !== null) {
                // Cross-team Abfrage: Prüfe Mitgliedschaft
                if (!$user) {
                    return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
                }

                $targetTeam = Team::find($requestedTeamId);
                if (!$targetTeam) {
                    return ToolResult::error('TEAM_NOT_FOUND', 'Das angegebene Team wurde nicht gefunden. Nutze das Tool "core.teams.GET" um alle verfügbaren Teams zu sehen.');
                }

                // Scope/Policy: User darf nur Teams abfragen, in denen er Mitglied ist
                $userHasAccess = $user->teams()->where('teams.id', $targetTeam->id)->exists();
                if (!$userHasAccess) {
                    return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team. Nutze das Tool "core.teams.GET" um alle verfügbaren Teams zu sehen.');
                }

                $isCurrentTeam = $context->team && $context->team->id === $targetTeam->id;
            } else {
                $targetTeam = $context->team;
            }

            // Prüfe ob ein Session-Team-Override aktiv ist
            $sessionTeamOverride = false;
            $sessionId = McpSessionTeamManager::resolveSessionId();
            if ($sessionId) {
                $sessionTeamOverride = McpSessionTeamManager::hasTeamOverride($sessionId);
            }

            $teamData = null;
            if ($targetTeam) {
                $teamData = [
                    'id' => $targetTeam->id,
                    'name' => $targetTeam->name ?? null,
                    'is_current_team' => $isCurrentTeam,
                ];
                // Wenn kein expliziter team_id-Parameter und ein Session-Override aktiv ist,
                // markiere dies im Response
                if ($requestedTeamId === null && $sessionTeamOverride) {
                    $teamData['is_session_override'] = true;
                    $teamData['info'] = 'Team-Kontext wurde per "core.team.switch" gewechselt. Nutze "core.team.switch" um in ein anderes Team zu wechseln.';
                }
            }

            $result = [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                ] : null,
                'team' => $teamData,
            ];

            // best-effort: Team-Scope für hasAccess
            $baseTeam = $targetTeam
                ?? ($user?->currentTeamRelation ?? null)
                ?? ($user?->currentTeam ?? null);

            // Modul-Berechtigungen (kompakt)
            if ($includeModules) {
                $allowed = [];
                $denied = [];

                $registered = ModuleRegistry::all(); // key => config

                // Pseudo-Module, die immer erlaubt sind (ohne DB-Prüfung)
                $alwaysAllowed = ['core', 'tools', 'communication'];

                foreach ($registered as $key => $cfg) {
                    if (!is_string($key) || $key === '') {
                        continue;
                    }

                    // Core/Tools/Communication: Immer erlauben (keine DB-Prüfung)
                    if (in_array($key, $alwaysAllowed, true)) {
                        $title = is_array($cfg) ? ($cfg['title'] ?? null) : null;
                        $entry = ['key' => $key];
                        if (is_string($title) && $title !== '') {
                            $entry['title'] = $title;
                        }
                        $allowed[] = $entry;
                        continue;
                    }

                    $title = is_array($cfg) ? ($cfg['title'] ?? null) : null;

                    $hasAccess = false;
                    try {
                        if ($user && $baseTeam) {
                            $module = Module::where('key', $key)->first();
                            if ($module) {
                                $hasAccess = (bool) $module->hasAccess($user, $baseTeam);
                            } else {
                                $hasAccess = false;
                            }
                        }
                    } catch (\Throwable $e) {
                        $hasAccess = false;
                    }

                    $entry = ['key' => $key];
                    if (is_string($title) && $title !== '') {
                        $entry['title'] = $title;
                    }

                    if ($hasAccess) {
                        $allowed[] = $entry;
                    } else {
                        $denied[] = $entry;
                    }
                }

                // Communication explizit hinzufügen (falls nicht in ModuleRegistry)
                if (!isset($registered['communication'])) {
                    $allowed[] = [
                        'key' => 'communication',
                        'title' => 'Communication',
                    ];
                }

                $result['modules'] = [
                    'allowed_modules' => $allowed,
                    'denied_modules' => $denied,
                    'counts' => [
                        'allowed' => count($allowed),
                        'denied' => count($denied),
                        'registered_total' => count($registered),
                    ],
                ];
            }

            // Team-Mitglieder (optional)
            if ($includeMembers && $targetTeam) {
                $members = $targetTeam->users()->get();
                $result['members'] = $members->map(function ($member) use ($user) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => $member->pivot->role ?? null,
                        'is_current_user' => $user && $user->id === $member->id,
                    ];
                })->values()->toArray();
                $result['member_count'] = count($result['members']);
            }

            // Erweiterte Metadaten (wenn verfügbar)
            if ($includeMetadata) {
                try {
                    // Versuche CoreContextTool zu nutzen (falls verfügbar)
                    if (class_exists(\Platform\Core\Tools\CoreContextTool::class)) {
                        $coreContextTool = app(\Platform\Core\Tools\CoreContextTool::class);
                        if (method_exists($coreContextTool, 'getContext')) {
                            $coreContext = $coreContextTool->getContext();
                            $result['module'] = $coreContext['data']['module'] ?? null;
                            $result['route'] = $coreContext['data']['route'] ?? null;
                            $result['url'] = $coreContext['data']['url'] ?? null;
                            $result['current_time'] = $coreContext['data']['current_time'] ?? null;
                            $result['timezone'] = $coreContext['data']['timezone'] ?? null;
                        }
                    }
                } catch (\Throwable $e) {
                    // Kontext nicht verfügbar - ignoriere
                }
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen des Kontexts: ' . $e->getMessage());
        }
    }
}
