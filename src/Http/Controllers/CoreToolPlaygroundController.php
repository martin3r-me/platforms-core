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
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Services\ToolCacheService;
use Platform\Core\Services\ToolTimeoutService;
use Platform\Core\Services\ToolValidationService;
use Platform\Core\Services\ToolCircuitBreaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;

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
                'step' => 'nullable|integer', // Multi-Step: Schritt-Nummer (0 = initial, 1+ = Folge-Schritte)
                'previous_result' => 'nullable|array', // Vorheriges Ergebnis für Folge-Schritte
                'user_input' => 'nullable|string', // User-Input für Folge-Schritte (z.B. Team-Auswahl)
            ]);

            $message = $request->input('message');
            $options = $request->input('options', []);
            $step = $request->input('step', 0); // 0 = initial, 1+ = Folge-Schritte
            $previousResult = $request->input('previous_result', []);
            $userInput = $request->input('user_input');
            
            $simulation = [
                'timestamp' => now()->toIso8601String(),
                'user_message' => $message,
                'step' => $step,
                'is_multi_step' => $step > 0,
                'previous_result' => $previousResult,
                'user_input' => $userInput,
                'steps' => [],
                'tools_used' => [],
                'tools_discovered' => [],
                'chain_plan' => null,
                'execution_flow' => [],
                'final_response' => null,
                'feature_status' => [], // Für neue Feature-Infos
                'requires_user_input' => false, // Wird gesetzt, wenn User-Input benötigt wird
                'user_input_prompt' => null, // Prompt für User-Input
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
            
            // WICHTIG: Stelle sicher, dass alle Tools geladen sind (auch core.teams.list)
            // Prüfe ob wichtige Tools fehlen und lade sie nach
            $allToolsBefore = $registry->all();
            if (count($allToolsBefore) === 0 || !$registry->has('core.teams.list') || !$registry->has('tools.list')) {
                // Trigger Auto-Discovery manuell
                try {
                    $coreTools = \Platform\Core\Tools\ToolLoader::loadCoreTools();
                    foreach ($coreTools as $tool) {
                        try {
                            if (!$registry->has($tool->getName())) {
                                $registry->register($tool);
                            }
                        } catch (\Throwable $e) {
                            // Silent fail
                        }
                    }
                    
                    // Module-Tools laden
                    $modulesPath = realpath(__DIR__ . '/../../../../modules');
                    if ($modulesPath && is_dir($modulesPath)) {
                        $moduleTools = \Platform\Core\Tools\ToolLoader::loadFromAllModules($modulesPath);
                        foreach ($moduleTools as $tool) {
                            try {
                                if (!$registry->has($tool->getName())) {
                                    $registry->register($tool);
                                }
                            } catch (\Throwable $e) {
                                // Silent fail
                            }
                        }
                    }
                    
                    // Fallback: Manuelle Registrierung für wichtige Tools
                    if (!$registry->has('core.teams.list')) {
                        try {
                            $registry->register(app(\Platform\Core\Tools\ListTeamsTool::class));
                        } catch (\Throwable $e) {
                            // Silent fail
                        }
                    }
                    if (!$registry->has('tools.list')) {
                        try {
                            $registry->register(app(\Platform\Core\Tools\ListToolsTool::class));
                        } catch (\Throwable $e) {
                            // Silent fail
                        }
                    }
                    if (!$registry->has('tools.request')) {
                        try {
                            $registry->register(app(\Platform\Core\Tools\RequestToolTool::class));
                        } catch (\Throwable $e) {
                            // Silent fail
                        }
                    }
                } catch (\Throwable $e) {
                    // Silent fail
                }
            }
            
            $discovery = new ToolDiscoveryService($registry);
            
            // Debug: Prüfe ob Tools registriert sind
            $allRegisteredTools = $registry->all();
            $simulation['debug'] = [
                'total_tools_registered' => count($allRegisteredTools),
                'registered_tool_names' => array_map(fn($t) => $t->getName(), $allRegisteredTools),
                'has_core_teams_list' => $registry->has('core.teams.list'),
            ];
            
            $intent = $message;
            $intentLower = strtolower(trim($intent));
            
            // STEP 0: SEMANTISCHE INTENT-ANALYSE (immer an erster Stelle!)
            $simulation['steps'][] = [
                'step' => 0,
                'name' => 'Semantische Intent-Analyse',
                'description' => 'Analysiere: Kann ich das selbstständig auflösen? Frage oder Aufgabe?',
                'timestamp' => now()->toIso8601String(),
            ];
            
            // Semantische Analyse durchführen
            $semanticAnalysis = $this->analyzeIntent($intent, $registry);
            
            $simulation['semantic_analysis'] = $semanticAnalysis;
            $simulation['steps'][] = [
                'step' => 0,
                'result' => $semanticAnalysis['can_solve_independently'] === null
                    ? '🤔 LLM entscheidet selbst'
                    : ($semanticAnalysis['can_solve_independently'] 
                        ? '✅ Kann selbstständig auflösen' 
                        : '❌ Benötigt Hilfe'),
                'analysis' => $semanticAnalysis,
            ];
            
            // WICHTIG: Kosten-Optimierung!
            // Wir zeigen NICHT proaktiv alle Tools - das wäre teuer!
            // Stattdessen: LLM entscheidet erst, ob sie Tools braucht
            // Nur wenn LLM entscheidet, dass sie Tools braucht, zeigen wir alle Tools
            
            // STEP 1: MCP BEST PRACTICE - Zeige IMMER alle Tools
            // Die LLM entscheidet selbst, ob sie Tools braucht oder nicht
            // Wir filtern oder entscheiden NICHT vorab!
            
            $simulation['steps'][] = [
                'step' => 1,
                'name' => 'Tool Discovery',
                'description' => 'Zeige alle verfügbaren Tools (MCP Best Practice)',
                'timestamp' => now()->toIso8601String(),
                'llm_decision' => 'LLM sieht alle Tools und entscheidet selbst, ob sie welche braucht',
            ];
            
            // STEP 2: Zeige IMMER alle Tools (MCP Best Practice)
            $discoveredTools = []; // Initialisiere vor dem Block
            {
                // MCP BEST PRACTICE: Zeige IMMER alle Tools
                // Die LLM entscheidet selbst, welches Tool sie braucht
                // KEINE Filterung, KEINE Pattern-basierte Entscheidungen!
                try {
                    // findByIntent gibt ALLE Tools zurück (MCP-Pattern)
                    // Das LLM sieht alle Tools und entscheidet selbst, welches es braucht
                    $allTools = $discovery->findByIntent($intent);
                
                    // FÜR DIE SIMULATION: Zeige alle Tools
                    // In der echten AI-Integration würde das LLM alle Tools sehen und selbst entscheiden
                    $discoveredTools = $allTools;
                
                $simulation['debug']['mcp_pattern'] = true;
                $simulation['debug']['total_tools_available'] = count($allTools);
                $simulation['debug']['note'] = 'LLM sieht alle Tools und entscheidet selbst, welches sie braucht (MCP Best Practice)';
                } catch (\Throwable $e) {
                    // Bei Fehlern: leeres Array verwenden
                    $discoveredTools = [];
                    $simulation['debug']['discovery_error'] = $e->getMessage();
                }
                
                $simulation['tools_discovered'] = array_map(function($tool) {
                    return [
                        'name' => $tool->getName(),
                        'description' => $tool->getDescription(),
                        'has_dependencies' => $tool instanceof \Platform\Core\Contracts\ToolDependencyContract,
                    ];
                }, $discoveredTools);

                $simulation['steps'][] = [
                    'step' => 2,
                    'result' => count($discoveredTools) . ' Tools verfügbar',
                    'tools' => array_map(fn($t) => $t->getName(), $discoveredTools),
                    'note' => 'LLM sieht alle Tools und entscheidet selbst, welches sie braucht (MCP Best Practice)',
                ];
            }

            // STEP 2: LLM-Entscheidung basierend auf semantischer Analyse
            // WICHTIG: In der echten AI-Integration würde das LLM jetzt entscheiden:
            // - Kann ich selbstständig auflösen? → Direkte Antwort
            // - Benötige ich Tools? → Tool auswählen und Chain planen
            // - Kann ich User helfen? → Helper-Tools verwenden
            // - Keine Tools verfügbar? → tools.request aufrufen
            
            // SEMANTISCHE ENTSCHEIDUNG: Die LLM entscheidet selbst!
            // Die semantische Analyse gibt nur Info (intent_type), keine Entscheidung!
            // Die LLM sieht alle Tools und entscheidet selbst, ob sie welche braucht
            $needsTool = null; // LLM entscheidet selbst - keine Vorentscheidung!
            $canSolveIndependently = null; // LLM entscheidet selbst - keine Vorentscheidung!
            
            $simulation['debug']['llm_decision'] = 'LLM sieht alle Tools und entscheidet selbst, ob sie welche braucht';
            $simulation['debug']['reason'] = $semanticAnalysis['reason'];
            
            // WICHTIG: KEINE automatischen Tool-Requests!
            // Die LLM entscheidet selbst, ob sie tools.request aufruft
            // Wir erstellen keine automatischen Requests basierend auf Pattern-Matching
            
            // Multi-Step: Wenn User-Input vorhanden ist, verwende es für das nächste Tool
            if ($step > 0 && !empty($userInput) && !empty($previousResult)) {
                // Folge-Schritt: User hat Input gegeben (z.B. Team-ID ausgewählt)
                $nextTool = $previousResult['next_tool'] ?? null;
                $nextToolArgs = $previousResult['next_tool_args'] ?? [];
                
                if ($nextTool) {
                    // Merge User-Input in Tool-Arguments
                    // Beispiel: User wählt Team-ID 5 → füge team_id zu Arguments hinzu
                    if (is_numeric($userInput)) {
                        $nextToolArgs['team_id'] = (int)$userInput;
                    } else {
                        // Versuche User-Input zu parsen (z.B. JSON)
                        $parsed = json_decode($userInput, true);
                        if (is_array($parsed)) {
                            $nextToolArgs = array_merge($nextToolArgs, $parsed);
                        } else {
                            $nextToolArgs['user_input'] = $userInput;
                        }
                    }
                    
                    $toolName = $nextTool;
                    $arguments = $nextToolArgs;
                    $primaryTool = $registry->get($toolName);
                    
                    if (!$primaryTool) {
                        throw new \RuntimeException("Tool '{$toolName}' nicht gefunden");
                    }
                    
                    $simulation['steps'][] = [
                        'step' => 2,
                        'name' => 'Multi-Step: User-Input verarbeitet',
                        'description' => "User-Input wurde verarbeitet und in Tool-Arguments übernommen",
                        'timestamp' => now()->toIso8601String(),
                        'user_input' => $userInput,
                        'merged_arguments' => $arguments,
                    ];
                } else {
                    throw new \RuntimeException("Kein next_tool im previous_result gefunden");
                }
            } else {
                // WICHTIG: Nutze ECHTE Services statt Simulation!
                // Der Playground ist die echte Test-Umgebung
                
                $simulation['steps'][] = [
                    'step' => 2,
                    'name' => 'OpenAI Service aufrufen',
                    'description' => 'Nutze echten OpenAiService.chat() - LLM entscheidet selbst',
                    'timestamp' => now()->toIso8601String(),
                ];
                
                // Nutze echten OpenAiService (wie im Terminal/CoreAiStreamController)
                $openAiService = app(OpenAiService::class);
                $executor = app(ToolExecutor::class);
                $orchestrator = app(ToolOrchestrator::class);
                
                // Erstelle Messages-Array (wie im Terminal)
                $messages = [
                    [
                        'role' => 'user',
                        'content' => $message,
                    ],
                ];
                
                // Multi-Step-Chat: Führe so lange aus, bis LLM keine Tools mehr aufruft
                $maxIterations = 10; // Verhindere Endlosschleifen
                $iteration = 0;
                $allToolResults = [];
                $allResponses = [];
                
                $simulation['steps'][] = [
                    'step' => 3,
                    'name' => 'Multi-Step-Chat',
                    'description' => 'LLM sieht alle Tools und entscheidet selbst - Multi-Step bis finale Antwort',
                    'timestamp' => now()->toIso8601String(),
                ];
                
                try {
                    while ($iteration < $maxIterations) {
                        $iteration++;
                        
                        $simulation['steps'][] = [
                            'step' => 3 + $iteration,
                            'name' => "Chat-Runde {$iteration}",
                            'description' => 'Rufe OpenAI auf - LLM entscheidet selbst',
                            'timestamp' => now()->toIso8601String(),
                        ];
                        
                        // Rufe echten OpenAiService auf (zeigt automatisch alle Tools)
                        $response = $openAiService->chat($messages, 'gpt-4o-mini', [
                            'max_tokens' => 2000,
                            'temperature' => 0.7,
                            'tools' => null, // null = Tools aktivieren (OpenAiService ruft getAvailableTools() auf)
                        ]);
                        
                        $allResponses[] = $response;
                        
                        $simulation['debug']['openai_response_' . $iteration] = [
                            'has_content' => !empty($response['content']),
                            'has_tool_calls' => !empty($response['tool_calls']),
                            'tool_calls_count' => count($response['tool_calls'] ?? []),
                            'finish_reason' => $response['finish_reason'] ?? null,
                        ];
                        
                        // Wenn LLM Tool-Calls gemacht hat
                        if (!empty($response['tool_calls'])) {
                            $simulation['steps'][] = [
                                'step' => 3 + $iteration,
                                'name' => "Tool-Calls erkannt (Runde {$iteration})",
                                'description' => 'LLM hat entschieden, Tools aufzurufen',
                                'timestamp' => now()->toIso8601String(),
                                'tool_calls' => $response['tool_calls'],
                            ];
                            
                            // Füge Assistant-Message mit Tool-Calls zu Messages hinzu
                            $messages[] = [
                                'role' => 'assistant',
                                'content' => $response['content'] ?? null,
                                'tool_calls' => $response['tool_calls'],
                            ];
                            
                            // Führe echte Tool-Execution durch (wie in CoreAiStreamController)
                            foreach ($response['tool_calls'] as $toolCall) {
                                $toolCallId = $toolCall['id'] ?? null;
                                $toolName = $toolCall['function']['name'] ?? null;
                                $toolArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                                
                                if (!$toolName) continue;
                                
                                // Tool-Name zurückmappen (von OpenAI-Format zu internem Format)
                                $internalToolName = $this->denormalizeToolNameFromOpenAi($toolName);
                                
                                $simulation['steps'][] = [
                                    'step' => 4 + $iteration,
                                    'name' => 'Tool Execution',
                                    'description' => "Führe Tool aus: {$internalToolName}",
                                    'timestamp' => now()->toIso8601String(),
                                    'tool' => $internalToolName,
                                    'arguments' => $toolArguments,
                                    'tool_call_id' => $toolCallId,
                                ];
                                
                                // Nutze echten ToolOrchestrator (wie in CoreAiStreamController)
                                $context = ToolContext::fromAuth();
                                $startTime = microtime(true);
                                
                                try {
                                    $toolResult = $orchestrator->executeWithDependencies(
                                        $internalToolName,
                                        $toolArguments,
                                        $context,
                                        maxDepth: 5,
                                        planFirst: true
                                    );
                                    
                                    $executionTime = (microtime(true) - $startTime) * 1000;
                                    
                                    // Konvertiere ToolResult zu OpenAI-Format
                                    $resultArray = $toolResult->toArray();
                                    
                                    // Füge Tool-Result zu Messages hinzu (für Multi-Step)
                                    $messages[] = [
                                        'role' => 'tool',
                                        'tool_call_id' => $toolCallId,
                                        'content' => json_encode($resultArray, JSON_UNESCAPED_UNICODE),
                                    ];
                                    
                                    $allToolResults[] = [
                                        'iteration' => $iteration,
                                        'tool_call_id' => $toolCallId,
                                        'tool' => $internalToolName,
                                        'success' => $toolResult->success,
                                        'data' => $toolResult->data,
                                        'error' => $toolResult->error,
                                        'execution_time_ms' => round($executionTime, 2),
                                    ];
                                    
                                    $simulation['execution_flow'][] = [
                                        'iteration' => $iteration,
                                        'tool' => $internalToolName,
                                        'arguments' => $toolArguments,
                                        'result' => [
                                            'success' => $toolResult->success,
                                            'data' => $toolResult->data,
                                            'error' => $toolResult->error,
                                        ],
                                        'execution_time_ms' => round($executionTime, 2),
                                    ];
                                    
                                } catch (\Throwable $e) {
                                    $executionTime = (microtime(true) - $startTime) * 1000;
                                    
                                    // Fehler-Result zu Messages hinzufügen
                                    $errorResult = [
                                        'ok' => false,
                                        'error' => [
                                            'code' => 'EXECUTION_ERROR',
                                            'message' => $e->getMessage()
                                        ]
                                    ];
                                    
                                    $messages[] = [
                                        'role' => 'tool',
                                        'tool_call_id' => $toolCallId,
                                        'content' => json_encode($errorResult, JSON_UNESCAPED_UNICODE),
                                    ];
                                    
                                    $allToolResults[] = [
                                        'iteration' => $iteration,
                                        'tool_call_id' => $toolCallId,
                                        'tool' => $internalToolName,
                                        'success' => false,
                                        'error' => $e->getMessage(),
                                        'execution_time_ms' => round($executionTime, 2),
                                    ];
                                }
                            }
                            
                            // Weiter mit nächster Iteration (LLM bekommt Tool-Results und kann weiterarbeiten)
                            continue;
                            
                        } else {
                            // LLM hat direkt geantwortet (keine Tools mehr) - Multi-Step beendet
                            $simulation['steps'][] = [
                                'step' => 3 + $iteration,
                                'name' => "Finale Antwort (Runde {$iteration})",
                                'description' => 'LLM hat finale Antwort gegeben - keine Tools mehr',
                                'timestamp' => now()->toIso8601String(),
                            ];
                            
                            $simulation['final_response'] = [
                                'type' => 'direct_answer',
                                'message' => $response['content'] ?? 'Keine Antwort',
                                'content' => $response['content'],
                                'iterations' => $iteration,
                                'tool_results' => $allToolResults,
                            ];
                            
                            // Beende Multi-Step-Loop
                            break;
                        }
                    }
                    
                    // Falls maxIterations erreicht wurde
                    if ($iteration >= $maxIterations) {
                        $simulation['final_response'] = [
                            'type' => 'warning',
                            'message' => "Maximale Iterationen ({$maxIterations}) erreicht",
                            'content' => $response['content'] ?? 'Keine finale Antwort',
                            'iterations' => $iteration,
                            'tool_results' => $allToolResults,
                        ];
                    }
                    
                } catch (\Throwable $e) {
                    // Fehler beim OpenAI-Aufruf - füge Details hinzu
                    $simulation['final_response'] = [
                        'type' => 'error',
                        'message' => 'Fehler beim Aufruf von OpenAiService: ' . $e->getMessage(),
                        'error' => $e->getMessage(),
                        'error_details' => [
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'class' => get_class($e),
                            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
                        ],
                    ];
                    $simulation['debug']['openai_error'] = [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'class' => get_class($e),
                    ];
                }
                
                // Keine weitere Tool-Execution nötig - wir haben bereits die echten Services genutzt
                $primaryTool = null;
                $toolName = null;
            }
            
            // Wenn Tool gefunden (Multi-Step), führe Chain Planning und Execution aus
            if ($primaryTool && $toolName) {
                $simulation['steps'][] = [
                    'step' => 2,
                    'name' => 'Chain Planning',
                    'description' => 'Plane Tool-Execution-Chain mit Dependencies',
                    'timestamp' => now()->toIso8601String(),
                ];

                $planner = new ToolChainPlanner($registry);
                $context = ToolContext::fromAuth();
                
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

                // Sammle Event-Informationen
                $eventData = [
                    'tool_executed' => null,
                    'tool_failed' => null,
                ];

                // Tracke Dependency-Executions separat (BEVOR Haupt-Tool-Listener)
                $dependencyExecutions = [];
                $dependencyEventData = [];
                
                // Event-Listener für Dependencies (wird zuerst registriert, um alle Events zu fangen)
                $dependencyListener = Event::listen(\Platform\Core\Events\ToolExecuted::class, function ($event) use (&$dependencyExecutions, &$dependencyEventData, $toolName) {
                    // Nur Dependencies tracken (nicht das Haupt-Tool)
                    if ($event->toolName !== $toolName) {
                        // Sammle vollständige Fehler-Info
                        $errorInfo = null;
                        if (!$event->result->success) {
                            $errorInfo = [
                                'error' => $event->result->error,
                                'message' => is_array($event->result->error) 
                                    ? ($event->result->error['message'] ?? $event->result->error) 
                                    : ($event->result->error ?? 'EXECUTION_ERROR'),
                                'code' => is_array($event->result->error) 
                                    ? ($event->result->error['code'] ?? 'EXECUTION_ERROR') 
                                    : 'EXECUTION_ERROR',
                            ];
                        }
                        
                        $dependencyExecutions[] = [
                            'tool' => $event->toolName,
                            'success' => $event->result->success,
                            'duration' => $event->duration,
                            'memory_usage' => $event->memoryUsage,
                            'trace_id' => $event->traceId,
                            'result_data' => $event->result->data,
                            'error_info' => $errorInfo, // Vollständige Fehler-Info
                        ];
                        $dependencyEventData[$event->toolName] = [
                            'tool_executed' => [
                                'tool' => $event->toolName,
                                'duration' => $event->duration,
                                'memory_usage' => $event->memoryUsage,
                                'trace_id' => $event->traceId,
                                'success' => $event->result->success,
                            ],
                        ];
                    }
                });

                // Event-Listener für Haupt-Tool (wird nach Dependency-Listener registriert)
                $executedListener = Event::listen(\Platform\Core\Events\ToolExecuted::class, function ($event) use (&$eventData, $toolName) {
                    // Nur Haupt-Tool tracken
                    if ($event->toolName === $toolName) {
                        $eventData['tool_executed'] = [
                            'tool' => $event->toolName,
                            'duration' => $event->duration,
                            'memory_usage' => $event->memoryUsage,
                            'trace_id' => $event->traceId,
                            'success' => $event->result->success,
                        ];
                    }
                });

                $failedListener = Event::listen(\Platform\Core\Events\ToolFailed::class, function ($event) use (&$eventData, $toolName) {
                    // Nur Haupt-Tool tracken
                    if ($event->toolName === $toolName) {
                        $eventData['tool_failed'] = [
                            'tool' => $event->toolName,
                            'error_message' => $event->errorMessage,
                            'error_code' => $event->errorCode,
                            'duration' => $event->duration,
                            'trace_id' => $event->traceId,
                        ];
                    }
                });

                $executor = app(ToolExecutor::class);
                $orchestrator = app(ToolOrchestrator::class);
                
                // Sammle Feature-Informationen
                $featureInfo = [
                    'cache' => null,
                    'timeout' => null,
                    'validation' => null,
                    'circuit_breaker' => null,
                ];

                // Cache-Status
                try {
                    $cacheService = app(ToolCacheService::class);
                    $cacheKey = 'tool_result:' . md5(json_encode(['tool' => $toolName, 'args' => $arguments]));
                    $cached = Cache::get($cacheKey);
                    $featureInfo['cache'] = [
                        'enabled' => config('tools.cache.enabled', true),
                        'cached' => $cached !== null,
                        'cache_key' => $cacheKey,
                    ];
                } catch (\Throwable $e) {
                    $featureInfo['cache'] = ['enabled' => false, 'error' => $e->getMessage()];
                }

                // Timeout-Info
                try {
                    $timeoutService = app(ToolTimeoutService::class);
                    $featureInfo['timeout'] = [
                        'enabled' => $timeoutService->isEnabled(),
                        'timeout_seconds' => $timeoutService->getTimeoutForTool($toolName),
                    ];
                } catch (\Throwable $e) {
                    $featureInfo['timeout'] = ['enabled' => false, 'error' => $e->getMessage()];
                }

                // Validation-Info
                try {
                    $validationService = app(ToolValidationService::class);
                    $validationResult = $validationService->validate($primaryTool, $arguments);
                    $featureInfo['validation'] = [
                        'valid' => $validationResult['valid'],
                        'errors' => $validationResult['errors'],
                        'validated_data' => $validationResult['data'],
                    ];
                } catch (\Throwable $e) {
                    $featureInfo['validation'] = ['error' => $e->getMessage()];
                }

                // Circuit Breaker Status
                try {
                    $circuitBreaker = app(ToolCircuitBreaker::class);
                    // Prüfe für externe Services (z.B. OpenAI)
                    $featureInfo['circuit_breaker'] = [
                        'enabled' => config('tools.circuit_breaker.enabled', true),
                        'openai_status' => $circuitBreaker->isOpen('openai') ? 'open' : 'closed',
                    ];
                } catch (\Throwable $e) {
                    $featureInfo['circuit_breaker'] = ['error' => $e->getMessage()];
                }

                // Sammle PHP-Fehler während der Ausführung
                $phpErrors = [];
                $previousErrorHandler = set_error_handler(function($severity, $message, $file, $line) use (&$phpErrors) {
                    $phpErrors[] = [
                        'severity' => $severity,
                        'message' => $message,
                        'file' => $file,
                        'line' => $line,
                    ];
                    // Weiterleiten an Standard-Error-Handler
                    return false;
                });
                
                $startTime = microtime(true);
                try {
                    $executionResult = $orchestrator->executeWithDependencies(
                        $toolName,
                        $arguments,
                        $context,
                        maxDepth: 5,
                        planFirst: true
                    );
                } catch (\Throwable $execError) {
                    // Fehler während Tool-Execution
                    $phpErrors[] = [
                        'severity' => E_ERROR,
                        'message' => $execError->getMessage(),
                        'file' => $execError->getFile(),
                        'line' => $execError->getLine(),
                        'type' => 'exception',
                        'class' => get_class($execError),
                        'trace' => array_slice(explode("\n", $execError->getTraceAsString()), 0, 10),
                    ];
                    // Erstelle Error-Result
                    $executionResult = ToolResult::error(
                        $execError->getMessage(),
                        'EXECUTION_ERROR',
                        ['exception' => get_class($execError)]
                    );
                } finally {
                    restore_error_handler();
                    if ($previousErrorHandler) {
                        set_error_handler($previousErrorHandler);
                    }
                }
                $executionTime = (microtime(true) - $startTime) * 1000;
                
                // Füge PHP-Fehler zu Feature-Info hinzu
                if (!empty($phpErrors)) {
                    $featureInfo['php_errors'] = $phpErrors;
                    $simulation['debug']['php_errors_during_execution'] = $phpErrors;
                }
                
                // Event-Listener entfernen
                Event::forget(\Platform\Core\Events\ToolExecuted::class);
                Event::forget(\Platform\Core\Events\ToolFailed::class);

                // Füge Dependency-Executions hinzu
                foreach ($dependencyExecutions as $depExec) {
                    // Versuche vollständige Fehler-Details zu holen
                    $errorDetails = null;
                    if (!$depExec['success'] && isset($depExec['result_data'])) {
                        // Wenn result_data ein Error-Objekt ist, extrahiere Details
                        if (is_array($depExec['result_data']) && isset($depExec['result_data']['error'])) {
                            $errorDetails = $depExec['result_data'];
                        }
                    }
                    
                    $simulation['execution_flow'][] = [
                        'tool' => $depExec['tool'],
                        'arguments' => [], // Dependency-Arguments werden nicht getrackt
                        'result' => [
                            'success' => $depExec['success'],
                            'has_data' => $depExec['result_data'] !== null,
                            'has_error' => !$depExec['success'],
                            'data' => $depExec['success'] ? $depExec['result_data'] : null,
                            'error' => !$depExec['success'] ? ($errorDetails['error'] ?? 'EXECUTION_ERROR') : null,
                        ],
                        'timestamp' => now()->toIso8601String(),
                        'execution_time_ms' => round($depExec['duration'] * 1000, 2),
                        'events' => $dependencyEventData[$depExec['tool']] ?? null,
                        'is_dependency' => true,
                        'error_details' => $errorDetails,
                    ];
                }

                // Haupt-Tool-Execution
                $executionFlowEntry = [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'result' => [
                        'success' => $executionResult->success,
                        'has_data' => $executionResult->data !== null,
                        'has_error' => $executionResult->error !== null,
                        'error' => $executionResult->error,
                        'data' => $executionResult->data,
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'execution_time_ms' => round($executionTime, 2),
                    'events' => $eventData,
                    'features' => $featureInfo,
                ];
                
                // Füge PHP-Fehler hinzu, falls vorhanden
                if (!empty($phpErrors)) {
                    $executionFlowEntry['php_errors'] = $phpErrors;
                    $executionFlowEntry['result']['has_php_errors'] = true;
                    $executionFlowEntry['result']['php_errors'] = $phpErrors;
                }
                
                $simulation['execution_flow'][] = $executionFlowEntry;

                $simulation['tools_used'][] = [
                    'name' => $toolName,
                    'executed_at' => now()->toIso8601String(),
                    'success' => $executionResult->success,
                    'execution_time_ms' => round($executionTime, 2),
                ];

                $simulation['steps'][] = [
                    'step' => 3,
                    'result' => $executionResult->success ? 'Tool erfolgreich ausgeführt' : 'Tool-Fehler',
                    'execution_time_ms' => round($executionTime, 2),
                    'events' => $eventData,
                    'features' => $featureInfo,
                ];

                // STEP 4: Response Generation (simuliert)
                $simulation['steps'][] = [
                    'step' => 4,
                    'name' => 'Response Generation',
                    'description' => 'Generiere finale Antwort basierend auf Tool-Ergebnissen',
                    'timestamp' => now()->toIso8601String(),
                ];

                // Prüfe ob User-Input benötigt wird
                if ($executionResult->success && isset($executionResult->data['requires_user_input']) && $executionResult->data['requires_user_input'] === true) {
                    // Tool benötigt User-Input (z.B. Team-Auswahl)
                    $simulation['requires_user_input'] = true;
                    $simulation['user_input_prompt'] = $executionResult->data['message'] ?? 'Bitte wähle aus der Liste aus.';
                    $simulation['user_input_data'] = $executionResult->data['dependency_tool_result'] ?? $executionResult->data;
                    $simulation['next_tool'] = $executionResult->data['next_tool'] ?? $toolName;
                    $simulation['next_tool_args'] = $executionResult->data['next_tool_args'] ?? $arguments;
                    
                    $simulation['final_response'] = [
                        'type' => 'user_input_required',
                        'message' => $executionResult->data['message'] ?? 'Bitte wähle aus der Liste aus.',
                        'data' => $executionResult->data['dependency_tool_result'] ?? $executionResult->data,
                        'next_tool' => $executionResult->data['next_tool'] ?? $toolName,
                        'next_tool_args' => $executionResult->data['next_tool_args'] ?? $arguments,
                    ];
                } elseif ($executionResult->success) {
                    $simulation['final_response'] = [
                        'type' => 'success',
                        'message' => 'Tool erfolgreich ausgeführt.',
                        'data' => $executionResult->data,
                        'metadata' => $executionResult->metadata,
                    ];
                } else {
                    // Fehler-Details extrahieren
                    $errorMessage = is_array($executionResult->error) 
                        ? ($executionResult->error['message'] ?? $executionResult->error) 
                        : ($executionResult->error ?? 'Unbekannter Fehler');
                    
                    // Prüfe ob es eine Exception-Message in den Metadaten gibt
                    if (isset($executionResult->metadata['exception_message'])) {
                        $errorMessage = $executionResult->metadata['exception_message'];
                    }
                    
                    $errorResponse = [
                        'type' => 'error',
                        'message' => 'Tool-Fehler: ' . $errorMessage,
                        'error' => [
                            'message' => $errorMessage,
                            'code' => $executionResult->errorCode ?? 'EXECUTION_ERROR',
                            'metadata' => $executionResult->metadata,
                        ],
                        'error_details' => [
                            'error_code' => $executionResult->errorCode ?? 'EXECUTION_ERROR',
                            'error_message' => $errorMessage,
                            'full_error' => $executionResult->error,
                            'metadata' => $executionResult->metadata,
                        ],
                    ];
                    
                    // Füge PHP-Fehler hinzu, falls vorhanden
                    if (!empty($phpErrors)) {
                        $errorResponse['php_errors'] = $phpErrors;
                        $errorResponse['error_details']['php_errors'] = $phpErrors;
                        $errorResponse['message'] .= ' (Zusätzlich wurden PHP-Fehler während der Ausführung erkannt)';
                    }
                    
                    $simulation['final_response'] = $errorResponse;
                }
            } else {
                // LLM würde direkt antworten (kein Tool benötigt)
                $simulation['final_response'] = [
                    'type' => 'direct_answer',
                    'message' => 'LLM würde direkt antworten - kein Tool benötigt',
                    'reason' => $simulation['debug']['llm_would_answer_directly'] ?? false
                        ? ($simulation['debug']['reason'] ?? 'Einfache Frage/Begrüßung')
                        : 'LLM hat entschieden, dass kein Tool benötigt wird',
                    'note' => 'In der echten AI-Integration würde das LLM alle Tools sehen, aber selbst entscheiden, dass es keine braucht',
                ];
            }

            return response()->json([
                'success' => true,
                'simulation' => $simulation,
            ]);

        } catch (\Throwable $e) {
            // Erweitere Simulation mit Fehler-Info für Debug-Export
            // WICHTIG: Stelle sicher, dass $simulation initialisiert ist
            if (!isset($simulation)) {
                $userMessage = '';
                try {
                    $userMessage = $request->input('message', '');
                } catch (\Throwable $reqError) {
                    // Request nicht verfügbar
                    $userMessage = 'Fehler beim Zugriff auf Request';
                }
                
                $simulation = [
                    'timestamp' => now()->toIso8601String(),
                    'user_message' => $userMessage,
                    'steps' => [],
                    'tools_discovered' => [],
                    'execution_flow' => [],
                    'final_response' => null,
                ];
            }
            
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
            
            // Füge error_details für Frontend hinzu
            $simulation['final_response']['error_details'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'class' => get_class($e),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 20), // Erste 20 Zeilen
            ];
            
            // Error Handler wiederherstellen
            restore_error_handler();
            
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

    /**
     * Semantische Intent-Analyse (LOOSE & GENERISCH)
     * 
     * Erste Frage: Kann ich das selbstständig auflösen?
     * - Frage → kann ich mit Wissen antworten?
     * - Aufgabe → kann ich mit Tools lösen?
     * - Benötigt Hilfe → kann ich mit Tools helfen ODER User-Hilfe geben?
     */
    /**
     * Semantische Intent-Analyse (NUR für Debug/Info)
     * 
     * WICHTIG: Diese Methode entscheidet NICHTS!
     * Sie gibt nur Info zurück für Debug-Zwecke.
     * 
     * ❌ KEINE Tool-Auswahl! (Das bleibt beim LLM)
     * ❌ KEINE Pattern-basierte Entscheidungen! (LLM entscheidet selbst)
     * ❌ KEINE automatischen Tool-Requests! (LLM entscheidet selbst)
     */
    private function analyzeIntent(string $intent, ToolRegistry $registry): array
    {
        // WICHTIG: Nur für Debug/Info - KEINE Entscheidungen!
        // Die LLM sieht alle Tools und entscheidet selbst!
        
        // Einfache Kategorisierung NUR für Debug/Info (nicht für Entscheidungen!)
        $intentLower = strtolower(trim($intent));
        $intentType = 'unclear';
        
        // Begrüßungen erkennen (nur für Info)
        $greetingPatterns = ['/^(moin|hallo|hi|hey|guten (tag|morgen|abend)|servus|grü(ß|ss)(e|i))/i'];
        foreach ($greetingPatterns as $pattern) {
            if (preg_match($pattern, $intentLower)) {
                $intentType = 'greeting';
                break;
            }
        }
        
        // Tools verfügbar? (nur für Info)
        $discovery = new ToolDiscoveryService($registry);
        $relevantTools = [];
        try {
            $relevantTools = $discovery->findByIntent($intent); // Gibt ALLE Tools zurück (MCP Best Practice)
        } catch (\Throwable $e) {
            $relevantTools = [];
        }
        
        // KEINE Pattern-basierte Entscheidungen!
        // Die LLM entscheidet selbst, ob sie Tools braucht oder nicht
        
        return [
            'intent_type' => $intentType, // Nur für Info/Debug
            'can_solve_independently' => null, // LLM entscheidet selbst
            'reason' => $intentType === 'greeting' 
                ? 'Begrüßung erkannt - LLM entscheidet selbst, ob Tools benötigt werden'
                : 'LLM sieht alle Tools und entscheidet selbst, ob sie welche braucht (MCP Best Practice)',
            'needs_tools' => null, // LLM entscheidet selbst
            'can_help_with_tools' => count($relevantTools) > 0, // Nur Info: Tools sind verfügbar
            'relevant_tools_count' => count($relevantTools),
            'can_help_user' => false, // LLM entscheidet selbst
            'helper_tools' => [],
            'needs_tool_request' => false, // LLM entscheidet selbst, ob sie tools.request aufruft
            'recommended_action' => 'LLM sieht alle Tools und entscheidet selbst, ob sie welche braucht',
        ];
    }
        
    /**
     * Gibt empfohlene Aktion basierend auf semantischer Analyse zurück
     * WICHTIG: Die LLM entscheidet selbst - diese Methode gibt nur eine generische Empfehlung
     */
    private function getRecommendedAction(?bool $canSolve, bool $canHelpWithTools, bool $canHelpUser, bool $needsRequest): string
    {
        // Wenn LLM selbst entscheidet (null), gibt generische Empfehlung
        if ($canSolve === null) {
            return 'LLM sieht alle Tools und entscheidet selbst, ob sie welche braucht';
        }
        
        if ($canSolve) {
            return 'Direkt mit generischem Wissen antworten';
        }
        
        if ($canHelpWithTools) {
            return 'Tools verwenden um Aufgabe zu lösen';
        }
        
        if ($canHelpUser) {
            return 'Helper-Tools verwenden um User bei der Antwort zu helfen';
        }
        
        if ($needsRequest) {
            return 'Automatisch tools.request aufrufen - keine passenden Tools verfügbar';
        }
        
        return 'Unklar - weitere Analyse nötig';
    }

    private function extractModuleFromToolName(string $toolName): string
    {
        if (str_contains($toolName, '.')) {
            return explode('.', $toolName)[0];
        }
        return 'core';
    }
    
    /**
     * Denormalisiert Tool-Namen von OpenAI-Format zu internem Format
     * OpenAI: "planner_projects_create" -> Intern: "planner.projects.create"
     */
    private function denormalizeToolNameFromOpenAi(string $openAiName): string
    {
        // OpenAI normalisiert Tool-Namen: "planner.projects.create" -> "planner_projects_create"
        // Wir müssen das rückgängig machen
        return str_replace('_', '.', $openAiName);
    }
    
}
