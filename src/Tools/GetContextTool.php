<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\Models\Module;

/**
 * Tool zum Abrufen des aktuellen Kontexts
 * 
 * MCP-Pattern: Das Sprachmodell kann diesen Tool auf Bedarf nutzen,
 * um den aktuellen Kontext (User, Team, Modul, Route) zu erfahren.
 * Statt alles immer mitzuschicken, fragt das Modell bei Bedarf nach.
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
                'include_metadata' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soll auch erweiterte Metadaten (Zeit, Zeitzone, etc.) enthalten sein? Standard: true'
                ],
                'include_modules' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soll eine kompakte Modul-Übersicht (allowed_modules/denied_modules) enthalten sein? Standard: true'
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

            $result = [
                'user' => $context->user ? [
                    'id' => $context->user->id,
                    'name' => $context->user->name ?? null,
                    'email' => $context->user->email ?? null,
                ] : null,
                'team' => $context->team ? [
                    'id' => $context->team->id,
                    'name' => $context->team->name ?? null,
                ] : null,
            ];

            // Modul-Berechtigungen (kompakt)
            if ($includeModules) {
                $allowed = [];
                $denied = [];

                $user = $context->user;
                // best-effort: aktueller Team-Scope für hasAccess
                $baseTeam = $context->team
                    ?? ($user?->currentTeamRelation ?? null)
                    ?? ($user?->currentTeam ?? null);

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
                                // Wenn Modul nicht in DB vorhanden ist, können wir hier keine belastbare Entscheidung treffen.
                                // Für Transparenz markieren wir es als "denied" (nicht sichtbar), statt es fälschlich zu erlauben.
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
                // (wird in GetModulesTool auch so gehandhabt)
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

