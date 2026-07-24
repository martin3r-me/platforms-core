<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\Models\Module;
use Platform\Core\Models\McpSession;
use Platform\Core\Models\Team;

/**
 * Tool zum Abrufen des aktuellen Kontexts
 *
 * PFLICHT-EINSTIEGSPUNKT: Dieses Tool MUSS als allererster Call jeder
 * MCP-Session ausgeführt werden, bevor irgendein anderes Tool genutzt wird.
 * Es liefert den aktiven UI-Team-Kontext des Users (is_current_team: true)
 * und stellt sicher, dass alle folgenden Operationen im richtigen Team laufen.
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
        return 'WICHTIG: Dieses Tool IMMER als allerersten Call ausführen, bevor andere Tools genutzt werden. Gibt den aktuellen Kontext zurück (User, Team, Modul, Route, URL) und liefert den aktiven UI-Team-Kontext des Users (is_current_team: true). Ohne diesen initialen Call besteht das Risiko, dass Operationen im falschen Team-Kontext ausgeführt werden. Auch aufrufen, wenn der Nutzer nach seinem aktuellen Kontext fragt oder wenn du wissen musst, in welchem Modul/Team der Nutzer sich befindet.';
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
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Kontext (v.a. allowed_modules/denied_modules) eines ANDEREN Users abrufen. Nur für Team-Owner/Admins. Der Ziel-User muss Mitglied des (Ziel-)Teams sein. Default: aktueller User.'
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

            // Optional: Kontext eines ANDEREN Users abfragen (nur Team-Owner/Admin).
            // Zweck: den Alt-Stand pro User ziehen (z.B. für die Authz-Migration),
            // damit man ihn übertragen und die alte modulables-Matrix später entfernen kann.
            $requestedUserId = $arguments['user_id'] ?? null;
            if ($requestedUserId !== null && $user && (int) $requestedUserId !== (int) $user->id) {
                if (!$targetTeam) {
                    return ToolResult::error('TEAM_NOT_FOUND', 'Für user_id muss ein Team bestimmbar sein (team_id angeben oder aktives Team setzen).');
                }
                // Root of Trust: nur Owner/Admin des Ziel-Teams darf fremde Kontexte sehen.
                $requesterRole = $user->teams()->where('teams.id', $targetTeam->id)->first()?->pivot?->role;
                if (!in_array($requesterRole, \Platform\Core\Enums\StandardRole::getAdminRoles(), true)) {
                    return ToolResult::error('ACCESS_DENIED', 'Nur Team-Owner/Admins dürfen den Kontext anderer User abfragen.');
                }
                $targetUser = \Platform\Core\Models\User::find((int) $requestedUserId);
                if (!$targetUser || !$targetUser->teams()->where('teams.id', $targetTeam->id)->exists()) {
                    return ToolResult::error('USER_NOT_FOUND', 'User nicht gefunden oder nicht Mitglied des Ziel-Teams.');
                }
                // Ab hier wird der gesamte Kontext (Module, Entities) für den Ziel-User berechnet.
                $user = $targetUser;
            }

            // Prüfe ob ein MCP-Team-Override aktiv ist (team_id in mcp_sessions)
            $mcpSessionId = $context->metadata['mcp_session_id'] ?? null;
            $mcpTeamOverride = false;
            if ($mcpSessionId) {
                $mcpSession = McpSession::find($mcpSessionId);
                $mcpTeamOverride = $mcpSession && $mcpSession->team_id !== null;
            }

            // Team-Source aus Metadata ermitteln
            $teamSource = $context->metadata['team_source'] ?? null;

            $teamData = null;
            if ($targetTeam) {
                $teamData = [
                    'id' => $targetTeam->id,
                    'name' => $targetTeam->name ?? null,
                    'is_current_team' => $isCurrentTeam,
                ];
                // Wenn kein expliziter team_id-Parameter und ein MCP-Override aktiv ist,
                // markiere dies im Response
                if ($requestedTeamId === null && $mcpTeamOverride) {
                    $teamData['is_mcp_override'] = true;
                    $teamData['info'] = 'MCP-Team-Kontext wurde per "core.team.switch" gesetzt. Nutze "core.team.switch" um in ein anderes Team zu wechseln.';
                }
                // Session-Info für Observability
                if ($mcpSessionId) {
                    $teamData['session_id'] = substr($mcpSessionId, 0, 12) . '...';
                    $teamData['session_source'] = $teamSource ?? ($mcpTeamOverride ? 'mcp_session' : 'user_current');
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

            // SemanticLayer — additiv, nur wenn aktiv + Modul freigeschaltet (oder production)
            try {
                $resolver = app(\Platform\Core\SemanticLayer\Services\SemanticLayerResolver::class);
                $moduleForLayer = $context->metadata['module'] ?? null;
                $resolvedLayer = $resolver->resolveFor($targetTeam, is_string($moduleForLayer) ? $moduleForLayer : null);
                if (!$resolvedLayer->isEmpty()) {
                    $result['semantic_layer'] = $resolvedLayer->toArray();
                }
            } catch (\Throwable $e) {
                // Defensive: Layer ist additiv, darf nie brechen
            }

            // best-effort: Team-Scope für hasAccess
            $baseTeam = $targetTeam
                ?? ($user?->currentTeamRelation ?? null)
                ?? ($user?->currentTeam ?? null);

            // Organization-Orientierung — nur wenn User Zugriff auf organization-Modul hat
            try {
                $orgModuleKey = 'organization';
                $orgModule = Module::where('key', $orgModuleKey)->first();
                $hasOrgAccess = $orgModule && $user && $baseTeam && $orgModule->hasAccess($user, $baseTeam);

                if ($hasOrgAccess) {
                    $rootTeam = ($targetTeam ?? $baseTeam)->getRootTeam();
                    $rootEntity = \Platform\Organization\Models\OrganizationEntity::where('team_id', $rootTeam->id)
                        ->whereNull('parent_entity_id')
                        ->where('is_active', true)
                        ->first();

                    if ($rootEntity) {
                        $result['organization'] = [
                            'root' => [
                                'name' => $rootEntity->name,
                                'uuid' => $rootEntity->uuid,
                                'entity_id' => $rootEntity->id,
                            ],
                            'hint' => 'Die Organisation ist das Rückgrat (VSM/Beer). Entities bilden einen Baum mit Perspektiven. '
                                . 'Über DimensionLinks hängen alle Modul-Objekte (Projekte, Aufgaben, Kontakte, ...) an Entities. '
                                . 'Zwei Richtungen: organization.dimension_links.GET mit context_type+context_id (forward: was hängt an Objekt X?) '
                                . 'oder dimension_item_id (reverse: was hängt an Entity Y?). '
                                . 'Einstieg: organization.entities.GET(parent_entity_id=null) für den Root-Knoten.',
                        ];
                    }

                    // User-Entities — welche Person-Entities sind mit dem aktuellen User verknüpft?
                    $userEntities = \Platform\Organization\Models\OrganizationEntity::where('linked_user_id', $user->id)
                        ->where('is_active', true)
                        ->get(['id', 'uuid', 'name', 'entity_type_id', 'parent_entity_id']);

                    if ($userEntities->isNotEmpty()) {
                        $result['organization']['user_entities'] = $userEntities->map(function ($entity) {
                            $entry = [
                                'name' => $entity->name,
                                'uuid' => $entity->uuid,
                                'entity_id' => $entity->id,
                            ];
                            if ($entity->parent_entity_id) {
                                $parent = \Platform\Organization\Models\OrganizationEntity::find($entity->parent_entity_id);
                                if ($parent) {
                                    $entry['parent'] = $parent->name;
                                }
                            }
                            return $entry;
                        })->values()->toArray();
                    }

                    // VSM-Tools — konzeptionell zentral, unabhängig von Usage-Statistik
                    $result['vsm_tools'] = [
                        'diagnose' => 'organization.entity.summary.GET — Gesundheit einer Entity: Signals, Items, Cashflow, Completion. include_children=true für den ganzen Baum.',
                        'navigate' => 'organization.context.resolve.GET — Von jedem Objekt zur zugehörigen Entity. object_type + object_id → Org-Pfad.',
                        'traverse' => 'organization.entities.GET — entity_type_id oder parent_entity_id für Baum-Navigation.',
                    ];
                }
            } catch (\Throwable $e) {
                // Organization-Orientierung ist additiv, darf nie brechen
            }

            // Strategie-Orientierung — nur wenn User Zugriff auf okr-Modul hat
            try {
                $okrModuleKey = 'okr';
                $okrModule = Module::where('key', $okrModuleKey)->first();
                $hasOkrAccess = $okrModule && $user && $baseTeam && $okrModule->hasAccess($user, $baseTeam);

                if ($hasOkrAccess) {
                    $okrRootTeam = ($targetTeam ?? $baseTeam)->getRootTeam();
                    $strategy = [];

                    // Aktiver Forecast mit Fokusräumen
                    $forecast = \Platform\Okr\Models\Forecast::where('team_id', $okrRootTeam->id)
                        ->latest()
                        ->first();

                    if ($forecast) {
                        $focusAreas = $forecast->focusAreas()
                            ->orderBy('order')
                            ->get(['id', 'uuid', 'title']);

                        $strategy['forecast'] = [
                            'id' => $forecast->id,
                            'title' => $forecast->title,
                            'target_date' => $forecast->target_date?->toDateString(),
                            'focus_areas' => $focusAreas->map(fn ($fa) => [
                                'id' => $fa->id,
                                'title' => $fa->title,
                            ])->values()->toArray(),
                        ];
                    }

                    // Aktiver Cycle
                    $currentCycle = \Platform\Okr\Models\Cycle::where('team_id', $okrRootTeam->id)
                        ->where('status', 'active')
                        ->with('template')
                        ->first();

                    if ($currentCycle) {
                        $cycleData = [
                            'id' => $currentCycle->id,
                            'label' => $currentCycle->label,
                        ];
                        if ($currentCycle->ends_at) {
                            $cycleData['ends_in_days'] = (int) now()->diffInDays($currentCycle->ends_at, false);
                        }
                        $strategy['cycle'] = $cycleData;
                    }

                    if (!empty($strategy)) {
                        $strategy['module'] = 'okr';
                        $strategy['hint'] = 'Strategischer Kontext. Details via okr.forecasts.GET, okr.focus_areas.GET, okr.cycles.GET.';
                        $result['strategy'] = $strategy;
                    }
                }
            } catch (\Throwable $e) {
                // Strategie-Orientierung ist additiv, darf nie brechen
            }

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

            // Tool-Katalog: Top-25 mit voller Description als Priming-Hint
            try {
                $catalogService = app(\Platform\Core\Services\ToolCatalogService::class);
                $catalogData = $catalogService->getForTeam($targetTeam);
                if (!empty($catalogData['catalog'])) {
                    // tools.GET rausfiltern — LLMs sollen tool_registry.SEARCH nutzen
                    $catalog = array_values(array_filter(
                        $catalogData['catalog'],
                        fn ($entry) => ($entry['tool'] ?? '') !== 'tools.GET',
                    ));

                    // tool_registry.SEARCH an Position 1 pinnen (nach core.context.GET)
                    $pinned = [
                        'tool' => 'tool_registry.SEARCH',
                        'desc' => 'Semantische Tool-Suche. query="..." für Freitext, name_glob="modul.*" für exakte Matches. IMMER dieses Tool nutzen statt tools.GET.',
                        'count' => null,
                        'pinned' => true,
                    ];

                    // Duplikat entfernen falls schon im Katalog
                    $catalog = array_values(array_filter(
                        $catalog,
                        fn ($entry) => ($entry['tool'] ?? '') !== 'tool_registry.SEARCH',
                    ));

                    // core.context.GET bleibt an Position 0, danach pinned, dann Rest
                    $contextEntry = null;
                    $rest = [];
                    foreach ($catalog as $entry) {
                        if (($entry['tool'] ?? '') === 'core.context.GET') {
                            $contextEntry = $entry;
                        } else {
                            $rest[] = $entry;
                        }
                    }

                    $finalCatalog = [];
                    if ($contextEntry) {
                        $finalCatalog[] = $contextEntry;
                    }
                    $finalCatalog[] = $pinned;
                    $finalCatalog = array_merge($finalCatalog, $rest);

                    $result['tool_catalog'] = array_slice($finalCatalog, 0, 25);
                }
            } catch (\Throwable $e) {
                // Katalog nie brechen lassen
            }

            // Skill-Codes: Nur gecachte Codes als kompakte Liste (kein Vault-Scan hier)
            try {
                if ($user) {
                    $skillService = app(\Platform\Core\Services\SkillRegistryService::class);
                    $teamVaultId = $skillService->resolveTeamVaultId($targetTeam);
                    $codes = $skillService->getCachedCodes($user->id, $teamVaultId);
                    if (!empty($codes)) {
                        $result['skills'] = [
                            'available' => $codes,
                            'hint' => 'Call skill_registry.GET(code="...") to load full instructions. Use skill_registry.SEARCH(query="...") for fuzzy matching.',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Skills nie brechen lassen
            }

            // Discovery-Hint für Tool-Registry
            $result['discovery'] = [
                'primary' => 'tool_registry.SEARCH',
                'hint' => 'Use tool_registry.SEARCH(query="...") to find tools. Do NOT browse modules.',
                'examples' => [
                    'tool_registry.SEARCH(query="notizen in vault schreiben")',
                    'tool_registry.SEARCH(query="task anlegen")',
                    'tool_registry.SEARCH(name_glob="canvas.*")',
                ],
            ];

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
