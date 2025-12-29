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
     */
    public function simulate(Request $request)
    {
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

        try {
            // STEP 1: Tool Discovery
            $registry = app(ToolRegistry::class);
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
            $discoveredTools = $discovery->findByIntent($intent);
            
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
                $arguments = $this->extractArguments($message, $primaryTool);
                
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
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'simulation' => $simulation,
            ], 500);
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
     */
    private function extractArguments(string $message, $tool): array
    {
        $arguments = [];
        
        try {
            // Beispiel: "Erstelle ein Projekt namens 'Test'" oder "namens Test"
            // Pattern: namens/named/name + optional quotes + Wert
            // WICHTIG: Pattern muss valide sein - verwende einfachere Variante ohne komplexe Lookaheads
            $pattern = '/namens?\s+([^\s]+(?:\s+[^\s]+)*?)(?:\s|$)/iu';
            $result = @preg_match($pattern, $message, $matches);
            if ($result === 1 && isset($matches[1])) {
                $arguments['name'] = trim($matches[1], " \t\n\r\0\x0B'\"");
            }
            // Alternative: "Projekt Test Projekt" - einfacheres Pattern
            elseif (preg_match('/(?:projekt|project)\s+([A-ZÄÖÜa-zäöüß][a-zäöüß\s]+?)(?:\s|$)/iu', $message, $matches) === 1) {
                $arguments['name'] = trim($matches[1]);
            }
            
            // Beispiel: "im Team 5" oder "Team-ID: 5"
            if (preg_match('/team[-\s]?(?:id)?[:\s]+(\d+)/iu', $message, $matches) === 1) {
                $arguments['team_id'] = (int) $matches[1];
            }
            
            // Beispiel: "Beschreibung: ..."
            if (preg_match('/beschreibung[:\s]+(.+?)(?:\s+(?:im|für|mit)|$)/iu', $message, $matches) === 1) {
                $arguments['description'] = trim($matches[1]);
            }
            
            // Beispiel: "Typ: customer" oder "Typ customer"
            if (preg_match('/typ[:\s]+(internal|customer|event|cooking)/iu', $message, $matches) === 1) {
                $arguments['project_type'] = strtolower($matches[1]);
            }
        } catch (\Throwable $e) {
            // Bei Regex-Fehlern: leeres Array zurückgeben
            \Log::warning('[ToolPlayground] Argument-Extraktion fehlgeschlagen', [
                'message' => $message,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return $arguments;
    }
}
