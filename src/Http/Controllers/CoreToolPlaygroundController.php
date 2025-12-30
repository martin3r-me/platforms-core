<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolOrchestrator;
use Platform\Core\Tools\ToolChainPlanner;
use Platform\Core\Tools\ToolDiscoveryService;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Services\OpenAiService;

/**
 * Tool Playground Controller
 * 
 * Vollständiger MCP-Simulator zum Testen der Tool-Orchestrierung mit vollem Debug.
 * MCP-Pattern: Simuliert kompletten Request-Flow, Tool-Discovery, Execution, etc.
 */
class CoreToolPlaygroundController extends Controller
{
    /**
     * API-Endpoint für vollständige MCP-Simulation
     * WICHTIG: Gibt immer JSON zurück, auch bei fatalen Fehlern
     */
    public function simulate(Request $request)
    {
        // WICHTIG: Stelle sicher, dass immer JSON zurückgegeben wird
        // Setze Error Handler für diese Methode
        $previousErrorHandler = set_error_handler(function($severity, $message, $file, $line) {
            // Konvertiere PHP-Warnungen zu Exceptions
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        
        try {
            $request->validate([
                'message' => 'required|string',
                'options' => 'nullable|array',
            ]);

            $message = $request->input('message');
            $options = $request->input('options', []);
            
            $simulation = [
                'timestamp' => now()->toIso8601String(),
                'user_message' => $message,
                'steps' => [],
                'tools_used' => [],
                'tools_discovered' => [],
                'chain_plan' => null,
                'execution_flow' => [],
                'final_response' => null,
            ];
        } catch (\Throwable $e) {
            // Fehler bei Request-Validierung oder Initialisierung
            restore_error_handler();
            return response()->json([
                'success' => false,
                'error' => 'Fehler bei Request-Validierung: ' . $e->getMessage(),
                'error_details' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'class' => get_class($e),
                ],
                'simulation' => [
                    'timestamp' => now()->toIso8601String(),
                    'user_message' => $request->input('message', 'Unbekannt'),
                    'steps' => [],
                    'tools_discovered' => [],
                    'execution_flow' => [],
                    'final_response' => [
                        'type' => 'error',
                        'message' => 'Fehler bei Request-Validierung: ' . $e->getMessage(),
                    ],
                ],
            ], 500);
        }

        try {
            // STEP 1: Tool Discovery
            // WICHTIG: Alle Regex-Operationen sind in ToolDiscoveryService abgesichert
            $registry = app(ToolRegistry::class);
            
            // Prüfe ob Registry verfügbar ist
            if (!$registry) {
                throw new \RuntimeException('ToolRegistry nicht verfügbar');
            }
            
            $discovery = new ToolDiscoveryService($registry);
            
            // Debug: Prüfe ob Tools registriert sind
            $allRegisteredTools = $registry->all();
            $simulation['debug'] = [
                'total_tools_registered' => count($allRegisteredTools),
                'registered_tool_names' => array_map(fn($t) => $t->getName(), $allRegisteredTools),
            ];
            
            $simulation['steps'][] = [
                'step' => 1,
                'name' => 'Tool Discovery',
                'description' => 'Suche nach relevanten Tools für die Anfrage',
                'timestamp' => now()->toIso8601String(),
            ];

            $intent = $message;
            
            // WICHTIG: findByIntent verwendet preg_split - ist bereits abgesichert
            try {
                $discoveredTools = $discovery->findByIntent($intent);
            } catch (\Throwable $e) {
                // Bei Fehlern: leeres Array verwenden
                $discoveredTools = [];
                $simulation['debug']['discovery_error'] = $e->getMessage();
            }
            
            // FALLBACK: Wenn keine Tools gefunden, versuche direkten Match
            if (count($discoveredTools) === 0 && count($allRegisteredTools) > 0) {
                // Versuche direkten Match über Tool-Namen
                $intentLower = strtolower($intent);
                foreach ($allRegisteredTools as $tool) {
                    $toolName = strtolower($tool->getName());
                    // Prüfe ob Intent-Wörter im Tool-Namen vorkommen
                    if ((stripos($toolName, 'projekt') !== false || stripos($intentLower, 'projekt') !== false) && 
                        (stripos($toolName, 'create') !== false || stripos($intentLower, 'erstellen') !== false)) {
                        $discoveredTools[] = $tool;
                        $simulation['debug']['fallback_match'] = $tool->getName();
                        break;
                    }
                }
            }
            
            $simulation['tools_discovered'] = array_map(function($tool) {
                return [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'has_dependencies' => $tool instanceof \Platform\Core\Contracts\ToolDependencyContract,
                ];
            }, $discoveredTools);

            $simulation['steps'][] = [
                'step' => 1,
                'result' => count($discoveredTools) . ' Tools gefunden',
                'tools' => array_map(fn($t) => $t->getName(), $discoveredTools),
            ];

            // STEP 2: Chain Planning (wenn Tool gefunden)
            if (count($discoveredTools) > 0) {
                $primaryTool = $discoveredTools[0];
                $toolName = $primaryTool->getName();
                
                $simulation['steps'][] = [
                    'step' => 2,
                    'name' => 'Chain Planning',
                    'description' => 'Plane Tool-Execution-Chain mit Dependencies',
                    'timestamp' => now()->toIso8601String(),
                ];

                $planner = new ToolChainPlanner($registry);
                $context = ToolContext::fromAuth();
                
                // Versuche Argumente aus Message zu extrahieren (vereinfacht)
                // WICHTIG: extractArguments ist abgesichert mit try-catch und @-Operator
                try {
                    $arguments = $this->extractArguments($message, $primaryTool);
                } catch (\Throwable $e) {
                    // Bei Fehlern: leeres Array verwenden
                    $arguments = [];
                    $simulation['debug']['argument_extraction_error'] = $e->getMessage();
                }
                
                $plan = $planner->planChain($toolName, $arguments, $context);
                $simulation['chain_plan'] = $plan;

                $simulation['steps'][] = [
                    'step' => 2,
                    'result' => 'Chain geplant',
                    'execution_order' => $plan['execution_order'] ?? [],
                    'dependencies' => $plan['dependencies'] ?? [],
                ];

                // STEP 3: Tool Execution Simulation
                $simulation['steps'][] = [
                    'step' => 3,
                    'name' => 'Tool Execution',
                    'description' => 'Simuliere Tool-Execution mit Orchestrator',
                    'timestamp' => now()->toIso8601String(),
                ];

                $executor = new ToolExecutor($registry);
                $orchestrator = new ToolOrchestrator($executor, $registry);
                
                $executionResult = $orchestrator->executeWithDependencies(
                    $toolName,
                    $arguments,
                    $context,
                    maxDepth: 5,
                    planFirst: true
                );

                $simulation['execution_flow'][] = [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'result' => [
                        'success' => $executionResult->success,
                        'has_data' => $executionResult->data !== null,
                        'has_error' => $executionResult->error !== null,
                    ],
                    'timestamp' => now()->toIso8601String(),
                ];

                $simulation['tools_used'][] = [
                    'name' => $toolName,
                    'executed_at' => now()->toIso8601String(),
                    'success' => $executionResult->success,
                ];

                $simulation['steps'][] = [
                    'step' => 3,
                    'result' => $executionResult->success ? 'Tool erfolgreich ausgeführt' : 'Tool-Fehler',
                    'execution_time_ms' => 0, // TODO: Messen
                ];

                // STEP 4: Response Generation (simuliert)
                $simulation['steps'][] = [
                    'step' => 4,
                    'name' => 'Response Generation',
                    'description' => 'Generiere finale Antwort basierend auf Tool-Ergebnissen',
                    'timestamp' => now()->toIso8601String(),
                ];

                if ($executionResult->success) {
                    $simulation['final_response'] = [
                        'type' => 'success',
                        'message' => 'Tool erfolgreich ausgeführt. Ergebnis: ' . json_encode($executionResult->data, JSON_PRETTY_PRINT),
                        'data' => $executionResult->data,
                    ];
                } else {
                    $simulation['final_response'] = [
                        'type' => 'error',
                        'message' => 'Tool-Fehler: ' . ($executionResult->error['message'] ?? 'Unbekannter Fehler'),
                        'error' => $executionResult->error,
                    ];
                }
            } else {
                $simulation['final_response'] = [
                    'type' => 'no_tools',
                    'message' => 'Keine passenden Tools gefunden. AI würde direkt antworten.',
                ];
            }

            return response()->json([
                'success' => true,
                'simulation' => $simulation,
            ]);

        } catch (\Throwable $e) {
            // Erweitere Simulation mit Fehler-Info für Debug-Export
            // WICHTIG: Stelle sicher, dass alle Arrays initialisiert sind
            if (!isset($simulation['steps'])) {
                $simulation['steps'] = [];
            }
            if (!isset($simulation['tools_discovered'])) {
                $simulation['tools_discovered'] = [];
            }
            if (!isset($simulation['execution_flow'])) {
                $simulation['execution_flow'] = [];
            }
            if (!isset($simulation['final_response'])) {
                $simulation['final_response'] = [
                    'type' => 'error',
                    'message' => 'Simulation fehlgeschlagen: ' . $e->getMessage(),
                ];
            }
            
            // Füge vollständige Fehler-Info hinzu (sicher für JSON)
            $simulation['error'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", substr($e->getTraceAsString(), 0, 5000)), // Erste 5000 Zeichen als Array
                'class' => get_class($e),
            ];
            
            // WICHTIG: Stelle sicher, dass die Antwort immer valides JSON ist
            try {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'error_details' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'class' => get_class($e),
                        'trace' => explode("\n", substr($e->getTraceAsString(), 0, 5000)),
                    ],
                    'simulation' => $simulation,
                ], 500);
            } catch (\Throwable $jsonError) {
                // Fallback: Sehr einfache JSON-Antwort mit Fehler-Info
                // WICHTIG: Verwende json_encode direkt, falls response()->json() fehlschlägt
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Fehler beim Erstellen der Antwort: ' . $e->getMessage(),
                    'original_error' => [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                    'json_error' => $jsonError->getMessage(),
                    'simulation' => [
                        'timestamp' => now()->toIso8601String(),
                        'user_message' => $message ?? 'Unbekannt',
                        'steps' => [],
                        'tools_discovered' => [],
                        'execution_flow' => [],
                        'final_response' => [
                            'type' => 'error',
                            'message' => 'Kritischer Fehler: ' . $e->getMessage(),
                        ],
                    ],
                ], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE);
                exit;
            } finally {
                restore_error_handler();
            }
        }
    }

    /**
     * API-Endpoint für Tool-Tests (bestehend)
     */
    public function test(Request $request)
    {
        $request->validate([
            'tool' => 'required|string',
            'arguments' => 'nullable|array',
            'options' => 'nullable|array',
        ]);

        $toolName = $request->input('tool');
        $arguments = $request->input('arguments', []);
        $options = $request->input('options', []);
        
        $showPlan = $options['plan'] ?? false;
        $showDiscover = $options['discover'] ?? false;
        $useOrchestrator = $options['use_orchestrator'] ?? true;

        $debug = [];
        $result = null;

        try {
            // Services initialisieren
            $registry = app(ToolRegistry::class);
            $executor = new ToolExecutor($registry);
            $orchestrator = new ToolOrchestrator($executor, $registry);
            $planner = new ToolChainPlanner($registry);
            $discovery = new ToolDiscoveryService($registry);

            $debug['services'] = [
                'registry_tools' => count($registry->all()),
                'tool_names' => array_keys($registry->all()),
            ];

            // Tool prüfen
            $tool = $registry->get($toolName);
            if (!$tool) {
                return response()->json([
                    'success' => false,
                    'error' => "Tool '{$toolName}' nicht gefunden",
                    'available_tools' => array_keys($registry->all()),
                    'debug' => $debug
                ], 404);
            }

            $debug['tool'] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'has_dependencies' => $tool instanceof \Platform\Core\Contracts\ToolDependencyContract,
                'has_metadata' => $tool instanceof \Platform\Core\Contracts\ToolMetadataContract,
            ];

            // Discovery (optional)
            if ($showDiscover) {
                $intent = "Projekt erstellen";
                $found = $discovery->findByIntent($intent);
                $debug['discovery'] = [
                    'intent' => $intent,
                    'found_tools' => array_map(fn($t) => $t->getName(), $found),
                ];
            }

            // Chain-Plan (optional)
            if ($showPlan) {
                $context = ToolContext::fromAuth();
                $plan = $planner->planChain($toolName, $arguments, $context);
                $debug['chain_plan'] = $plan;
            }

            // Context erstellen
            $context = ToolContext::fromAuth();

            // Tool ausführen
            if ($useOrchestrator) {
                $debug['execution'] = [
                    'method' => 'orchestrator',
                    'tool' => $toolName,
                    'arguments' => $arguments,
                ];
                $result = $orchestrator->executeWithDependencies(
                    $toolName,
                    $arguments,
                    $context,
                    maxDepth: 5,
                    planFirst: $showPlan
                );
            } else {
                $debug['execution'] = [
                    'method' => 'direct',
                    'tool' => $toolName,
                    'arguments' => $arguments,
                ];
                $result = $executor->execute($toolName, $arguments, $context);
            }

            $debug['result'] = [
                'success' => $result->success,
                'has_data' => $result->data !== null,
                'has_error' => $result->error !== null,
            ];

            return response()->json([
                'success' => $result->success,
                'data' => $result->data,
                'error' => $result->error,
                'error_code' => $result->errorCode,
                'metadata' => $result->metadata,
                'debug' => $debug,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
                'debug' => $debug,
            ], 500);
        }
    }

    /**
     * Gibt alle verfügbaren Tools zurück
     */
    public function tools()
    {
        try {
            $registry = app(ToolRegistry::class);
            $allTools = $registry->all();
            
            $tools = [];
            foreach ($allTools as $tool) {
                $tools[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'schema' => $tool->getSchema(),
                    'has_dependencies' => $tool instanceof \Platform\Core\Contracts\ToolDependencyContract,
                    'has_metadata' => $tool instanceof \Platform\Core\Contracts\ToolMetadataContract,
                ];
            }

            return response()->json([
                'success' => true,
                'tools' => $tools,
                'count' => count($tools),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extrahiert Argumente aus User-Message (verbessert)
     * WICHTIG: Alle Regex-Patterns sind vereinfacht und sicher
     */
    private function extractArguments(string $message, $tool): array
    {
        $arguments = [];
        
        // WICHTIG: Verwende nur einfache, sichere Patterns
        // Keine komplexen Lookaheads oder Lookbehinds, die Probleme verursachen können
        
        try {
            // Beispiel: "Erstelle ein Projekt namens 'Test'" oder "namens Test"
            // Vereinfachtes Pattern ohne komplexe Gruppen
            $pattern1 = '/namens?\s+([a-zA-ZÄÖÜäöüß0-9\s]+?)(?:\s|$)/iu';
            if (@preg_match($pattern1, $message, $matches) === 1 && isset($matches[1])) {
                $arguments['name'] = trim($matches[1], " \t\n\r\0\x0B'\"");
            }
            // Alternative: "Projekt Test Projekt"
            elseif (@preg_match('/(?:projekt|project)\s+([a-zA-ZÄÖÜäöüß0-9\s]+?)(?:\s|$)/iu', $message, $matches) === 1) {
                $arguments['name'] = trim($matches[1]);
            }
            
            // Beispiel: "im Team 5" oder "Team-ID: 5" - sehr einfaches Pattern
            if (@preg_match('/team[-\s]?id?[:\s]+(\d+)/iu', $message, $matches) === 1) {
                $arguments['team_id'] = (int) $matches[1];
            }
            
            // Beispiel: "Beschreibung: ..." - vereinfacht
            if (@preg_match('/beschreibung[:\s]+(.+?)(?:\s|$)/iu', $message, $matches) === 1) {
                $arguments['description'] = trim($matches[1]);
            }
            
            // Beispiel: "Typ: customer" oder "Typ customer"
            if (@preg_match('/typ[:\s]+(internal|customer|event|cooking)/iu', $message, $matches) === 1) {
                $arguments['project_type'] = strtolower($matches[1]);
            }
        } catch (\Throwable $e) {
            // Bei Regex-Fehlern: leeres Array zurückgeben
            \Log::warning('[ToolPlayground] Argument-Extraktion fehlgeschlagen', [
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500)
            ]);
        }
        
        return $arguments;
    }

    /**
     * Tool Discovery mit Filtern (tools.list)
     */
    public function discovery(Request $request)
    {
        try {
            $request->validate([
                'filters' => 'nullable|array',
            ]);

            $filters = $request->input('filters', []);
            $registry = app(ToolRegistry::class);
            $discovery = new ToolDiscoveryService($registry);

            // Nutze findByCriteria für Filterung
            $filteredTools = $discovery->findByCriteria($filters);
            $allTools = $registry->all();

            // Konvertiere Tools zu Array-Format
            $tools = [];
            foreach ($filteredTools as $tool) {
                $metadata = $discovery->getToolMetadata($tool);
                $tools[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'module' => $this->extractModuleFromToolName($tool->getName()),
                    'metadata' => [
                        'category' => $metadata['category'] ?? null,
                        'tags' => $metadata['tags'] ?? [],
                        'read_only' => $metadata['read_only'] ?? false,
                    ],
                ];
            }

            return response()->json([
                'success' => true,
                'result' => [
                    'tools' => $tools,
                    'summary' => [
                        'total_tools' => count($allTools),
                        'filtered_tools' => count($tools),
                        'filters_applied' => !empty($filters),
                    ],
                    'filters' => $filters,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tool Request absenden (tools.request)
     */
    public function request(Request $request)
    {
        try {
            $request->validate([
                'description' => 'required|string',
                'use_case' => 'nullable|string',
                'suggested_name' => 'nullable|string',
                'category' => 'nullable|string|in:query,action,utility',
                'module' => 'nullable|string',
            ]);

            $registry = app(ToolRegistry::class);
            $discovery = new ToolDiscoveryService($registry);
            $requestTool = new \Platform\Core\Tools\RequestToolTool($registry, $discovery);

            $context = ToolContext::fromAuth();
            $arguments = [
                'description' => $request->input('description'),
                'use_case' => $request->input('use_case'),
                'suggested_name' => $request->input('suggested_name'),
                'category' => $request->input('category'),
                'module' => $request->input('module'),
            ];

            $result = $requestTool->execute($arguments, $context);

            return response()->json([
                'success' => $result->success,
                'result' => $result->data,
                'error' => $result->error,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tool Requests anzeigen
     */
    public function requests()
    {
        try {
            $requests = \Platform\Core\Models\ToolRequest::query()
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'description' => $request->description,
                        'use_case' => $request->use_case,
                        'suggested_name' => $request->suggested_name,
                        'category' => $request->category,
                        'module' => $request->module,
                        'status' => $request->status,
                        'created_at' => $request->created_at->toIso8601String(),
                        'user' => $request->user ? [
                            'id' => $request->user->id,
                            'name' => $request->user->name,
                        ] : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'requests' => $requests,
                'count' => $requests->count(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function extractModuleFromToolName(string $toolName): string
    {
        if (str_contains($toolName, '.')) {
            return explode('.', $toolName)[0];
        }
        return 'core';
    }
}
