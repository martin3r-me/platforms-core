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
use Illuminate\Support\Facades\Log;

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
                'chat_history' => 'nullable|array', // Chat-Historie für Konversation
                'session_id' => 'nullable|string', // Session-ID für Chat-Historie
            ]);

            $message = $request->input('message');
            $options = $request->input('options', []);
            $step = $request->input('step', 0); // 0 = initial, 1+ = Folge-Schritte
            $previousResult = $request->input('previous_result', []);
            $userInput = $request->input('user_input');
            $chatHistory = $request->input('chat_history', []); // Chat-Historie vom Frontend
            $sessionId = $request->input('session_id', session()->getId()); // Session-ID
            
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
                
                // MCP-PATTERN: On-Demand Tool Injection
                // Starte immer mit Discovery-Tools nur (core.teams.GET, tools.GET, tools.request)
                // LLM ruft tools.GET auf, wenn sie Tools braucht → Tools werden SOFORT nachgeladen und verfügbar gemacht
                // Bei neuer User-Anfrage: Tools werden zurückgesetzt → LLM kann sie bei Bedarf wieder anfordern
                // KEINE Session-Persistenz: Jede User-Anfrage startet frisch, LLM entscheidet selbst, welche Tools sie braucht
                $openAiService->resetDynamicallyLoadedTools();
                
                // Erstelle Messages-Array (wie im Terminal)
                // WICHTIG: Nutze Chat-Historie, wenn vorhanden, sonst starte neu
                $messages = [];
                
                // Lade Chat-Historie aus Session (falls vorhanden)
                $sessionHistory = session()->get("playground_chat_history_{$sessionId}", []);
                
                // Merge: Session-Historie + Frontend-Historie (Frontend hat Priorität)
                if (!empty($chatHistory)) {
                    $messages = $chatHistory;
                } elseif (!empty($sessionHistory)) {
                    $messages = $sessionHistory;
                }
                
                // Füge neue User-Message hinzu
                $messages[] = [
                    'role' => 'user',
                    'content' => $message,
                ];
                
                // Speichere aktualisierte Historie in Session
                session()->put("playground_chat_history_{$sessionId}", $messages);
                
                // Multi-Step-Chat: Führe so lange aus, bis LLM keine Tools mehr aufruft
                $maxIterations = 5; // Verhindere Endlosschleifen (reduziert von 10 auf 5)
                $iteration = 0;
                $allToolResults = [];
                $allResponses = [];
                
                // Loop-Detection: Verhindere wiederholte Aufrufe des gleichen Tools
                $toolCallHistory = []; // Speichert: tool_name => [count, last_call_iteration]
                
                // Generiere Trace-ID für diese Session (wird für Versionierung verwendet)
                $traceId = bin2hex(random_bytes(8));
                $simulation['trace_id'] = $traceId;
                
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
                        
                        // DEBUG: Hole Tools, die an OpenAI gesendet werden
                        $reflection = new \ReflectionClass($openAiService);
                        $getToolsMethod = $reflection->getMethod('getAvailableTools');
                        $getToolsMethod->setAccessible(true);
                        $availableTools = $getToolsMethod->invoke($openAiService);
                        
                        $normalizeMethod = $reflection->getMethod('normalizeToolsForResponses');
                        $normalizeMethod->setAccessible(true);
                        $normalizedTools = $normalizeMethod->invoke($openAiService, $availableTools);
                        
                        // Extrahiere Tool-Namen (vor und nach Normalisierung)
                        $toolNamesBefore = array_map(function($t) {
                            if (isset($t['function']['name'])) {
                                return $t['function']['name'];
                            }
                            return $t['name'] ?? 'unknown';
                        }, $availableTools);
                        
                        $toolNamesAfter = array_map(function($t) {
                            return $t['name'] ?? ($t['function']['name'] ?? 'unknown');
                        }, $normalizedTools);
                        
                        // Prüfe, ob dynamisch geladene Tools vorhanden sind
                        $reflection = new \ReflectionClass($openAiService);
                        $dynamicallyLoadedProperty = $reflection->getProperty('dynamicallyLoadedTools');
                        $dynamicallyLoadedProperty->setAccessible(true);
                        $dynamicallyLoadedTools = $dynamicallyLoadedProperty->getValue($openAiService);
                        
                        $simulation['debug']['tools_sent_to_openai_' . $iteration] = [
                            'available_tools_count' => count($availableTools),
                            'normalized_tools_count' => count($normalizedTools),
                            'tool_names_before_normalization' => $toolNamesBefore,
                            'tool_names_after_normalization' => $toolNamesAfter,
                            'has_planner_projects_get' => in_array('planner.projects.GET', $toolNamesBefore) || in_array('planner_projects_GET', $toolNamesAfter),
                            'has_core_teams_get' => in_array('core.teams.GET', $toolNamesBefore) || in_array('core_teams_GET', $toolNamesAfter),
                            'dynamically_loaded_tools_count' => count($dynamicallyLoadedTools),
                            'dynamically_loaded_tool_names' => array_keys($dynamicallyLoadedTools),
                            'normalized_tools' => $normalizedTools, // Vollständige normalisierte Tools für Debugging
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
                            'content_preview' => !empty($response['content']) ? substr($response['content'], 0, 100) : null,
                            'content_length' => !empty($response['content']) ? strlen($response['content']) : 0,
                            'has_tool_calls' => !empty($response['tool_calls']),
                            'tool_calls_count' => count($response['tool_calls'] ?? []),
                            'tool_calls' => $response['tool_calls'] ?? [], // Zeige alle Tool-Calls für Debugging
                            'finish_reason' => $response['finish_reason'] ?? null,
                            'response_keys' => array_keys($response), // Zeige alle Keys für Debugging
                            'full_response' => $response, // Vollständige Response für Debugging
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
                            // Zeige auch Tool-Aktionen im Chat (als Info)
                            $toolActionsText = '';
                            if (count($response['tool_calls']) > 0) {
                                $toolActionsText = "\n\n**🔧 Ausgeführte Aktionen:**\n";
                                foreach ($response['tool_calls'] as $toolCall) {
                                    $toolName = $toolCall['function']['name'] ?? 'Unbekannt';
                                    $internalToolName = $this->denormalizeToolNameFromOpenAi($toolName);
                                    $toolActionsText .= "- {$internalToolName}\n";
                                }
                            }
                            
                            $messages[] = [
                                'role' => 'assistant',
                                'content' => ($response['content'] ?? '') . $toolActionsText,
                                'tool_calls' => $response['tool_calls'],
                            ];
                            
                            // Führe echte Tool-Execution durch (wie in CoreAiStreamController)
                            // WICHTIG: Mehrere Tool-Calls in einer Runde werden unterstützt - alle werden sequenziell ausgeführt
                            // Alle Tool-Results werden gesammelt und in der nächsten Iteration der LLM präsentiert
                            $toolsWereLoaded = false; // Flag: Wurden Tools nach tools.GET nachgeladen?
                            $injectedTools = []; // Liste der nachgeladenen Tools (für Debugging)
                            foreach ($response['tool_calls'] as $toolCall) {
                                $toolCallId = $toolCall['id'] ?? null;
                                $toolName = $toolCall['function']['name'] ?? null;
                                $toolArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                                
                                if (!$toolName) continue;
                                
                                // Tool-Name zurückmappen (von OpenAI-Format zu internem Format)
                                $internalToolName = $this->denormalizeToolNameFromOpenAi($toolName);
                                
                                // Markiere Tool als verwendet (für Cleanup von nicht genutzten Tools)
                                $openAiService->markToolAsUsed($internalToolName);
                                
                                // PRE-FLIGHT INTENTION VERIFICATION: Prüfe BEVOR Tool ausgeführt wird
                                $enablePreFlight = config('tools.pre_flight_verification.enabled', true);
                                $preFlightResult = null;
                                if ($enablePreFlight) {
                                    try {
                                        $preFlightService = app(\Platform\Core\Services\PreFlightIntentionService::class);
                                        $preFlightResult = $preFlightService->verify(
                                            $message, // Original User-Request
                                            $internalToolName,
                                            $toolArguments,
                                            $allToolResults
                                        );
                                        
                                        // Self-Reflection: Immer einen Hinweis geben (auch wenn keine Issues)
                                        // Die LLM soll selbst reflektieren, bevor sie ein Tool aufruft
                                        $reflectionText = $preFlightResult->getIssuesText();
                                        
                                        // Immer Self-Reflection-Prompt anzeigen (auch bei OK)
                                        // Die LLM soll sich immer fragen: "Ist das Tool das richtige?"
                                        if ($reflectionText) {
                                            $simulation['steps'][] = [
                                                'step' => 4 + $iteration,
                                                'name' => 'Pre-Flight Self-Reflection',
                                                'description' => 'Self-Reflection: LLM prüft selbst, ob das Tool passt',
                                                'timestamp' => now()->toIso8601String(),
                                                'pre_flight_issues' => $reflectionText,
                                                'is_issue' => $preFlightResult->hasIssues(),
                                            ];
                                            
                                            // Füge Self-Reflection-Prompt zu Messages hinzu
                                            // Die LLM kann dann selbst entscheiden, ob sie das Tool aufruft
                                            $selfReflectionPrompt = "\n\n" . $reflectionText;
                                            
                                            $messages[] = [
                                                'role' => 'system',
                                                'content' => $selfReflectionPrompt,
                                            ];
                                            
                                            // Tool wird TROTZDEM ausgeführt (LOOSE) - aber LLM hat Self-Reflection gemacht
                                            // Die LLM kann dann in der nächsten Iteration korrigieren oder tools.GET nutzen
                                        }
                                    } catch (\Throwable $e) {
                                        // Silent fail - Pre-Flight optional
                                        \Log::debug('[CoreToolPlayground] Pre-Flight-Verification konnte nicht durchgeführt werden', [
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }
                                
                                // Loop-Detection: Prüfe, ob dieses Tool bereits mehrfach aufgerufen wurde
                                if (!isset($toolCallHistory[$internalToolName])) {
                                    $toolCallHistory[$internalToolName] = ['count' => 0, 'last_iteration' => 0, 'arguments' => []];
                                }
                                $toolCallHistory[$internalToolName]['count']++;
                                $toolCallHistory[$internalToolName]['last_iteration'] = $iteration;
                                
                                // Loop-Detection: Markiere für spätere Integration ins Tool-Result
                                // Warnungen werden NICHT als separate system-Messages hinzugefügt,
                                // sondern direkt in das Tool-Result integriert (siehe formatToolResultForLLM)
                                if ($toolCallHistory[$internalToolName]['count'] >= 2) {
                                    $simulation['steps'][] = [
                                        'step' => 4 + $iteration,
                                        'name' => 'Loop-Detection Warning',
                                        'description' => "Tool '{$internalToolName}' wurde bereits {$toolCallHistory[$internalToolName]['count']} mal aufgerufen",
                                        'timestamp' => now()->toIso8601String(),
                                    ];
                                }
                                
                                $simulation['steps'][] = [
                                    'step' => 4 + $iteration,
                                    'name' => 'Tool Execution',
                                    'description' => "Führe Tool aus: {$internalToolName}",
                                    'timestamp' => now()->toIso8601String(),
                                    'tool' => $internalToolName,
                                    'arguments' => $toolArguments,
                                    'tool_call_id' => $toolCallId,
                                ];
                                
                                // Prüfe, ob Tool existiert (BEVOR wir es ausführen)
                                $registry = app(\Platform\Core\Tools\ToolRegistry::class);
                                if (!$registry->has($internalToolName)) {
                                    // Tool nicht gefunden - LOOSE: Suche ähnliche Tools und gib LLM alle Infos
                                    $allTools = array_keys($registry->all());
                                    $similarTools = [];
                                    
                                    // Finde ähnliche Tool-Namen (einfache String-Ähnlichkeit)
                                    foreach ($allTools as $toolName) {
                                        similar_text(strtolower($internalToolName), strtolower($toolName), $percent);
                                        if ($percent > 60) { // Mindestens 60% Ähnlichkeit
                                            $similarTools[] = $toolName;
                                        }
                                    }
                                    
                                    $errorMessage = "Tool '{$internalToolName}' nicht gefunden.";
                                    if (!empty($similarTools)) {
                                        $errorMessage .= " Ähnliche Tools: " . implode(', ', array_slice($similarTools, 0, 5));
                                    } else {
                                        $errorMessage .= " Verfügbare Tools: " . implode(', ', array_slice($allTools, 0, 10)) . '...';
                                    }
                                    
                                    // Tool nicht gefunden - füge klare Fehlermeldung hinzu
                                    $errorResult = [
                                        'ok' => false,
                                        'error' => [
                                            'code' => 'TOOL_NOT_FOUND',
                                            'message' => $errorMessage
                                        ]
                                    ];
                                    $loopCount = $toolCallHistory[$internalToolName]['count'] ?? 0;
                                    $toolResultText = $this->formatToolResultForLLM($internalToolName, $errorResult, $toolCallId, $loopCount);
                                    $messages[] = [
                                        'role' => 'user',
                                        'content' => $toolResultText,
                                    ];
                                    
                                    $allToolResults[] = [
                                        'iteration' => $iteration,
                                        'tool_call_id' => $toolCallId,
                                        'tool' => $internalToolName,
                                        'success' => false,
                                        'data' => null,
                                        'error' => "Tool '{$internalToolName}' nicht gefunden",
                                        'execution_time_ms' => 0,
                                    ];
                                    
                                    $simulation['execution_flow'][] = [
                                        'iteration' => $iteration,
                                        'tool' => $internalToolName,
                                        'arguments' => $toolArguments,
                                        'result' => [
                                            'success' => false,
                                            'data' => null,
                                            'error' => "Tool '{$internalToolName}' nicht gefunden",
                                        ],
                                        'execution_time_ms' => 0,
                                    ];
                                    
                                    // WICHTIG: Bei TOOL_NOT_FOUND lassen wir LLM reagieren
                                    // LLM kann dann das richtige Tool verwenden oder eine Fehlermeldung geben
                                    continue; // Weiter mit nächstem Tool-Call
                                }
                                
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
                                    
                                    // ====================================================================
                                    // TOOL INJECTION: Strategischer Ablauf mit umfassendem Debugging
                                    // ====================================================================
                                    if ($internalToolName === 'tools.GET') {
                                        $injectionDebug = [
                                            'step' => 'TOOL_INJECTION_START',
                                            'iteration' => $iteration,
                                            'tool_call_id' => $toolCallId,
                                            'arguments' => $toolArguments,
                                            'success' => $toolResult->success,
                                        ];
                                        
                                        // STEP 1: Tracken was tools.GET zurückgegeben hat
                                        $toolsData = [];
                                        $requestedTools = [];
                                        
                                        if ($toolResult->success) {
                                            $injectionDebug['step_1'] = 'TOOLS_GET_SUCCESS';
                                            $injectionDebug['step_1_result'] = [
                                                'has_data' => isset($resultArray['data']),
                                                'has_data_tools' => isset($resultArray['data']['tools']),
                                                'has_tools' => isset($resultArray['tools']),
                                                'result_keys' => array_keys($resultArray),
                                                'data_keys' => isset($resultArray['data']) ? array_keys($resultArray['data']) : [],
                                            ];
                                            
                                            // Extrahiere Tools aus Result (verschiedene Strukturen möglich)
                                            $toolsData = $resultArray['data']['tools'] ?? $resultArray['tools'] ?? [];
                                            $injectionDebug['step_1_tools_data'] = [
                                                'count' => is_array($toolsData) ? count($toolsData) : 0,
                                                'type' => gettype($toolsData),
                                                'is_array' => is_array($toolsData),
                                                'is_empty' => empty($toolsData),
                                            ];
                                            
                                            // STEP 2: Tools extrahieren
                                            if (!empty($toolsData) && is_array($toolsData)) {
                                                $injectionDebug['step_2'] = 'EXTRACT_TOOLS_FROM_RESULT';
                                                foreach ($toolsData as $toolInfo) {
                                                    $toolName = $toolInfo['name'] ?? null;
                                                    if ($toolName && is_string($toolName)) {
                                                        $requestedTools[] = $toolName;
                                                    }
                                                }
                                                $injectionDebug['step_2_extracted'] = [
                                                    'count' => count($requestedTools),
                                                    'tools' => $requestedTools,
                                                ];
                                            } else {
                                                $injectionDebug['step_2'] = 'NO_TOOLS_IN_RESULT';
                                                $injectionDebug['step_2_reason'] = 'tools.GET erfolgreich, aber keine Tools gefunden (möglicherweise search-Parameter ohne Treffer)';
                                            }
                                        } else {
                                            $injectionDebug['step_1'] = 'TOOLS_GET_FAILED';
                                            $injectionDebug['step_1_error'] = $toolResult->error ?? 'Unknown error';
                                        }
                                        
                                        // STEP 3: Fallback-Logik wenn keine Tools extrahiert wurden
                                        if (empty($requestedTools)) {
                                            $injectionDebug['step_3'] = 'FALLBACK_LOGIC';
                                            $module = $toolArguments['module'] ?? null;
                                            $readOnly = $toolArguments['read_only'] ?? null;
                                            
                                            $injectionDebug['step_3_params'] = [
                                                'module' => $module,
                                                'read_only' => $readOnly,
                                                'has_module' => !empty($module),
                                            ];
                                            
                                            if ($module) {
                                                try {
                                                    $registry = app(ToolRegistry::class);
                                                    $allTools = $registry->all();
                                                    $injectionDebug['step_3_registry'] = [
                                                        'total_tools_in_registry' => count($allTools),
                                                    ];
                                                    
                                                    foreach ($allTools as $tool) {
                                                        $toolName = $tool->getName();
                                                        
                                                        if (str_starts_with($toolName, $module . '.')) {
                                                            if ($readOnly !== null) {
                                                                $isReadOnly = str_ends_with($toolName, '.GET');
                                                                if (($readOnly && $isReadOnly) || (!$readOnly && !$isReadOnly)) {
                                                                    $requestedTools[] = $toolName;
                                                                }
                                                            } else {
                                                                $requestedTools[] = $toolName;
                                                            }
                                                        }
                                                    }
                                                    
                                                    $injectionDebug['step_3_fallback_result'] = [
                                                        'count' => count($requestedTools),
                                                        'tools' => $requestedTools,
                                                        'reason' => $toolResult->success ? 'tools.GET erfolgreich, aber keine Tools gefunden' : 'tools.GET fehlgeschlagen',
                                                    ];
                                                } catch (\Throwable $e) {
                                                    $injectionDebug['step_3_error'] = $e->getMessage();
                                                }
                                            } else {
                                                $injectionDebug['step_3_skipped'] = 'Kein module-Parameter vorhanden';
                                            }
                                        } else {
                                            $injectionDebug['step_3'] = 'SKIPPED';
                                            $injectionDebug['step_3_reason'] = 'Tools bereits aus Result extrahiert';
                                        }
                                        
                                        // STEP 4: Tools nachladen
                                        if (!empty($requestedTools)) {
                                            $injectionDebug['step_4'] = 'LOAD_TOOLS';
                                            $injectionDebug['step_4_before'] = [
                                                'tools_to_load' => $requestedTools,
                                                'count' => count($requestedTools),
                                            ];
                                            
                                            // Prüfe Tools VOR dem Nachladen
                                            $reflection = new \ReflectionClass($openAiService);
                                            $dynamicallyLoadedProperty = $reflection->getProperty('dynamicallyLoadedTools');
                                            $dynamicallyLoadedProperty->setAccessible(true);
                                            $toolsBeforeLoad = $dynamicallyLoadedProperty->getValue($openAiService);
                                            $injectionDebug['step_4_before_loaded'] = [
                                                'count' => count($toolsBeforeLoad),
                                                'tools' => array_keys($toolsBeforeLoad),
                                            ];
                                            
                                            // Nachladen
                                            $openAiService->loadToolsDynamically($requestedTools);
                                            $toolsWereLoaded = true;
                                            $injectedTools = $requestedTools; // Speichere für Debugging außerhalb des if-Blocks
                                            
                                            // Prüfe Tools NACH dem Nachladen
                                            $toolsAfterLoad = $dynamicallyLoadedProperty->getValue($openAiService);
                                            $injectionDebug['step_4_after'] = [
                                                'count' => count($toolsAfterLoad),
                                                'tools' => array_keys($toolsAfterLoad),
                                                'newly_loaded' => array_diff(array_keys($toolsAfterLoad), array_keys($toolsBeforeLoad)),
                                            ];
                                            
                                            // STEP 5: Verfügbarkeit prüfen
                                            $injectionDebug['step_5'] = 'VERIFY_AVAILABILITY';
                                            $getToolsMethod = $reflection->getMethod('getAvailableTools');
                                            $getToolsMethod->setAccessible(true);
                                            $availableTools = $getToolsMethod->invoke($openAiService);
                                            
                                            $normalizeMethod = $reflection->getMethod('normalizeToolsForResponses');
                                            $normalizeMethod->setAccessible(true);
                                            $normalizedTools = $normalizeMethod->invoke($openAiService, $availableTools);
                                            
                                            $availableToolNames = [];
                                            foreach ($availableTools as $tool) {
                                                $name = $tool['function']['name'] ?? $tool['name'] ?? null;
                                                if ($name) {
                                                    $availableToolNames[] = $name;
                                                }
                                            }
                                            
                                            $normalizedToolNames = array_map(function($t) {
                                                return $t['name'] ?? ($t['function']['name'] ?? 'unknown');
                                            }, $normalizedTools);
                                            
                                            // Prüfe ob nachgeladene Tools wirklich verfügbar sind
                                            $injectionDebug['step_5_verification'] = [];
                                            foreach ($requestedTools as $requestedTool) {
                                                // Normalisiere Tool-Name für OpenAI (planner.projects.GET -> planner_projects_GET)
                                                try {
                                                    $nameMapper = app(\Platform\Core\Services\ToolNameMapper::class);
                                                    $normalizedRequested = $nameMapper->toProvider($requestedTool);
                                                } catch (\Throwable $e) {
                                                    $normalizedRequested = str_replace('.', '_', $requestedTool);
                                                }
                                                
                                                // Prüfe in verschiedenen Formaten
                                                $foundInAvailable = in_array($requestedTool, $availableToolNames);
                                                $foundInNormalized = in_array($normalizedRequested, $normalizedToolNames);
                                                $isAvailable = $foundInAvailable || $foundInNormalized;
                                                
                                                $injectionDebug['step_5_verification'][] = [
                                                    'requested' => $requestedTool,
                                                    'normalized' => $normalizedRequested,
                                                    'is_available' => $isAvailable,
                                                    'found_in_available' => $foundInAvailable,
                                                    'found_in_normalized' => $foundInNormalized,
                                                ];
                                            }
                                            
                                            $injectionDebug['step_5_summary'] = [
                                                'total_available_tools' => count($availableTools),
                                                'total_normalized_tools' => count($normalizedTools),
                                                'available_tool_names' => $availableToolNames,
                                                'normalized_tool_names' => $normalizedToolNames,
                                                'all_requested_available' => count(array_filter($injectionDebug['step_5_verification'], fn($v) => $v['is_available'])) === count($requestedTools),
                                            ];
                                            
                                            $injectionDebug['step_6'] = 'INJECTION_COMPLETE';
                                            $injectionDebug['step_6_next'] = 'Sofortige OpenAI-Anfrage wird gemacht, damit Tools verfügbar sind';
                                            
                                            // Speichere Debug-Info
                                            $simulation['debug']['tool_injection_' . $iteration] = $injectionDebug;
                                            
                                            Log::info('[CoreToolPlayground] Tool-Injection abgeschlossen', [
                                                'iteration' => $iteration,
                                                'tools_requested' => count($requestedTools),
                                                'tools_loaded' => count($toolsAfterLoad),
                                                'tools_available' => count($availableTools),
                                                'all_available' => $injectionDebug['step_5_summary']['all_requested_available'],
                                            ]);
                                        } else {
                                            $injectionDebug['step_4'] = 'SKIPPED';
                                            $injectionDebug['step_4_reason'] = 'Keine Tools zum Nachladen';
                                            $injectionDebug['step_5'] = 'SKIPPED';
                                            $injectionDebug['step_6'] = 'INJECTION_FAILED';
                                            
                                            $simulation['debug']['tool_injection_' . $iteration] = $injectionDebug;
                                            
                                            Log::warning('[CoreToolPlayground] Tool-Injection fehlgeschlagen - keine Tools gefunden', [
                                                'iteration' => $iteration,
                                                'arguments' => $toolArguments,
                                                'result_structure' => $toolResult->success ? [
                                                    'has_data' => isset($resultArray['data']),
                                                    'has_data_tools' => isset($resultArray['data']['tools']),
                                                    'has_tools' => isset($resultArray['tools']),
                                                ] : null,
                                            ]);
                                        }
                                    }
                                    
                                    // Füge Tool-Result zu Messages hinzu (für Multi-Step)
                                    // WICHTIG: Responses API unterstützt 'tool' role nicht direkt
                                    // Format: Strukturiert und lesbar, damit LLM die Informationen erkennt
                                    // Integriere Loop-Detection-Warnungen direkt ins Tool-Result
                                    $loopCount = $toolCallHistory[$internalToolName]['count'] ?? 0;
                                    $toolResultText = $this->formatToolResultForLLM($internalToolName, $resultArray, $toolCallId, $loopCount);
                                    $messages[] = [
                                        'role' => 'user', // Responses API Format
                                        'content' => $toolResultText,
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
                                    // WICHTIG: Responses API unterstützt 'tool' role nicht direkt
                                    $errorResult = [
                                        'ok' => false,
                                        'error' => [
                                            'code' => 'EXECUTION_ERROR',
                                            'message' => $e->getMessage()
                                        ]
                                    ];
                                    
                                    $loopCount = $toolCallHistory[$internalToolName]['count'] ?? 0;
                                    $errorResultText = $this->formatToolResultForLLM($internalToolName, $errorResult, $toolCallId, $loopCount);
                                    $messages[] = [
                                        'role' => 'user', // Responses API Format
                                        'content' => $errorResultText,
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
                            
                            // Prüfe Intention-Verification NACH jedem Tool-Result, aber nur wenn:
                            // 1. Es bereits mehrere Iterationen gibt (> 2), ODER
                            // 2. Ein Loop erkannt wurde (gleiches Tool mehrfach)
                            // Dies verhindert, dass wir zu früh warnen, wenn die LLM noch Zwischenschritte macht
                            $enableVerification = config('tools.intention_verification.enabled', true);
                            $shouldVerify = false;
                            
                            // Prüfe ob ein Loop erkannt wurde (gleiches Tool mehrfach)
                            $toolCounts = [];
                            foreach ($allToolResults as $result) {
                                $tool = $result['tool'] ?? '';
                                if ($tool) {
                                    $toolCounts[$tool] = ($toolCounts[$tool] ?? 0) + 1;
                                }
                            }
                            $hasLoop = false;
                            foreach ($toolCounts as $tool => $count) {
                                if ($count > 2) {
                                    $hasLoop = true;
                                    break;
                                }
                            }
                            
                            // Verifikation nur wenn:
                            // - Es bereits mehr als 2 Iterationen gibt, ODER
                            // - Ein Loop erkannt wurde
                            if ($enableVerification && count($allToolResults) > 0 && (count($allToolResults) > 2 || $hasLoop)) {
                                $shouldVerify = true;
                            }
                            
                            if ($shouldVerify) {
                                try {
                                    $verificationService = app(\Platform\Core\Services\IntentionVerificationService::class);
                                    
                                    // Prüfe ob wir bereits ein Action Summary haben (wird später erstellt)
                                    $actionSummary = $simulation['action_summary'] ?? [];
                                    
                                    // Für READ-Operationen können wir auch ohne Action Summary prüfen
                                    $verification = $verificationService->verify(
                                        $message, // Original User-Request
                                        $allToolResults,
                                        $actionSummary
                                    );
                                    
                                    if ($verification->hasIssues()) {
                                        $verificationText = "\n\n⚠️ **Verifikation (Zwischenprüfung):**\n";
                                        $verificationText .= $verification->getIssuesText();
                                        $verificationText .= "\n\nPrüfe die Tool-Results und rufe das RICHTIGE Tool auf!";
                                        
                                        // Füge Verifikations-Hinweis zu Messages hinzu (für LLM-Korrektur)
                                        $messages[] = [
                                            'role' => 'system',
                                            'content' => $verificationText,
                                        ];
                                        
                                        $simulation['steps'][] = [
                                            'step' => 4 + $iteration,
                                            'name' => 'Intention-Verification (Zwischenprüfung)',
                                            'description' => 'Verifikation hat Probleme gefunden - LLM kann korrigieren',
                                            'timestamp' => now()->toIso8601String(),
                                            'verification_issues' => $verification->getIssuesText(),
                                        ];

                                        /**
                                         * MCP ROBUSTNESS: Auto-Injection im laufenden Run
                                         *
                                         * Wenn wir in einem Tool-Loop stecken und das erwartete Tool (z.B. planner.projects.GET)
                                         * nicht in den verfügbaren Tools ist, dann laden wir on-demand die Tools des erwarteten
                                         * Moduls per internem tools.GET nach (ohne dass die LLM erst tools.GET wählen muss).
                                         *
                                         * Ziel: "Injection im laufenden RUN" robuster machen.
                                         */
                                        $enableAutoInjection = config('tools.mcp.auto_injection_on_loop', true);
                                        if ($enableAutoInjection && $hasLoop && !$toolsWereLoaded) {
                                            try {
                                                $expectedTool = $verificationService->expectedToolFor($message);
                                                if ($expectedTool) {
                                                    // Prüfe ob expectedTool bereits verfügbar ist
                                                    $reflection = new \ReflectionClass($openAiService);
                                                    $getToolsMethod = $reflection->getMethod('getAvailableTools');
                                                    $getToolsMethod->setAccessible(true);
                                                    $availableToolsNow = $getToolsMethod->invoke($openAiService);

                                                    $availableToolNamesNow = [];
                                                    foreach ($availableToolsNow as $tool) {
                                                        $name = $tool['function']['name'] ?? $tool['name'] ?? null;
                                                        if ($name) {
                                                            $availableToolNamesNow[] = $name;
                                                        }
                                                    }

                                                    $expectedModule = explode('.', $expectedTool)[0] ?? null;
                                                    $expectedIsAvailable = in_array($expectedTool, $availableToolNamesNow, true);

                                                    if ($expectedModule && !$expectedIsAvailable) {
                                                        $autoArgs = [
                                                            'module' => $expectedModule,
                                                            // READ-only Tools für READ-Intent (wir injizieren minimal)
                                                            'read_only' => str_ends_with($expectedTool, '.GET'),
                                                            'search' => '',
                                                        ];

                                                        $simulation['steps'][] = [
                                                            'step' => 4 + $iteration,
                                                            'name' => 'MCP Auto-Injection (Loop Recovery)',
                                                            'description' => "Auto-Injection aktiviert: Lade Tools für Modul '{$expectedModule}' nach, weil '{$expectedTool}' nicht verfügbar ist",
                                                            'timestamp' => now()->toIso8601String(),
                                                            'expected_tool' => $expectedTool,
                                                            'auto_args' => $autoArgs,
                                                        ];

                                                        $context = \Platform\Core\Tools\ToolContext::fromAuth();
                                                        $autoToolResult = $orchestrator->executeWithDependencies(
                                                            'tools.GET',
                                                            $autoArgs,
                                                            $context,
                                                            maxDepth: 3,
                                                            planFirst: true
                                                        );

                                                        $autoResultArray = [
                                                            'ok' => (bool)($autoToolResult->success ?? false),
                                                            'data' => $autoToolResult->data ?? null,
                                                            'error' => $autoToolResult->error ?? null,
                                                        ];

                                                        $autoTools = [];
                                                        $autoData = $autoResultArray['data'] ?? [];
                                                        if (is_array($autoData) && isset($autoData['tools']) && is_array($autoData['tools'])) {
                                                            foreach ($autoData['tools'] as $t) {
                                                                $name = $t['name'] ?? null;
                                                                if ($name) {
                                                                    $autoTools[] = $name;
                                                                }
                                                            }
                                                        }

                                                        $simulation['debug']['tool_auto_injection_' . $iteration] = [
                                                            'status' => $autoResultArray['ok'] ? 'success' : 'error',
                                                            'expected_tool' => $expectedTool,
                                                            'module' => $expectedModule,
                                                            'auto_args' => $autoArgs,
                                                            'tools_found' => $autoTools,
                                                            'tools_found_count' => count($autoTools),
                                                            'note' => 'Auto-Injection nur bei Loop + expected tool missing; on-demand MCP-Hardening',
                                                        ];

                                                        if (!empty($autoTools)) {
                                                            $openAiService->loadToolsDynamically($autoTools);
                                                            $toolsWereLoaded = true;
                                                            $injectedTools = $autoTools;
                                                        }
                                                    }
                                                }
                                            } catch (\Throwable $e) {
                                                \Log::debug('[CoreToolPlayground] MCP Auto-Injection fehlgeschlagen', [
                                                    'error' => $e->getMessage(),
                                                ]);
                                                $simulation['debug']['tool_auto_injection_' . $iteration] = [
                                                    'status' => 'error',
                                                    'error' => $e->getMessage(),
                                                ];
                                            }
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    // Silent fail - Verifikation optional
                                    \Log::debug('[CoreToolPlayground] Zwischen-Verifikation konnte nicht durchgeführt werden', [
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                            
                            // Cleanup: Entferne nicht genutzte Tools (nach 3 Iterationen ohne Nutzung)
                            $openAiService->cleanupUnusedTools(3);
                            
                            // Aktualisiere Session-Historie nach Tool-Results (für nächste User-Message)
                            session()->put("playground_chat_history_{$sessionId}", $messages);
                            
                            // WICHTIG: Wenn tools.GET aufgerufen wurde und Tools nachgeladen wurden,
                            // müssen wir SOFORT eine neue OpenAI-Anfrage machen, damit die Tools verfügbar sind!
                            // Sonst springen wir zur nächsten Iteration und die Tools sind erst dann verfügbar.
                            if ($toolsWereLoaded) {
                                $simulation['steps'][] = [
                                    'step' => 3 + $iteration,
                                    'name' => "Tools nachgeladen - sofortige OpenAI-Anfrage (Runde {$iteration})",
                                    'description' => 'Tools wurden nach tools.GET nachgeladen - mache sofort neue OpenAI-Anfrage damit Tools verfügbar sind',
                                    'timestamp' => now()->toIso8601String(),
                                ];
                                
                                // Mache SOFORT eine neue OpenAI-Anfrage in der GLEICHEN Iteration
                                // Die Tools sind jetzt verfügbar!
                                // KEIN continue - wir machen direkt die neue Anfrage
                                
                                // Hole aktualisierte Tools (inkl. nachgeladene)
                                $reflection = new \ReflectionClass($openAiService);
                                $getToolsMethod = $reflection->getMethod('getAvailableTools');
                                $getToolsMethod->setAccessible(true);
                                $availableTools = $getToolsMethod->invoke($openAiService);
                                
                                $normalizeMethod = $reflection->getMethod('normalizeToolsForResponses');
                                $normalizeMethod->setAccessible(true);
                                $normalizedTools = $normalizeMethod->invoke($openAiService, $availableTools);
                                
                                $dynamicallyLoadedProperty = $reflection->getProperty('dynamicallyLoadedTools');
                                $dynamicallyLoadedProperty->setAccessible(true);
                                $dynamicallyLoadedTools = $dynamicallyLoadedProperty->getValue($openAiService);
                                
                                $toolNamesAfter = array_map(function($t) {
                                    return $t['name'] ?? ($t['function']['name'] ?? 'unknown');
                                }, $normalizedTools);
                                
                                // STEP 6: Debug-Info für sofortige OpenAI-Anfrage
                                $availableToolNamesForDebug = [];
                                foreach ($availableTools as $tool) {
                                    $name = $tool['function']['name'] ?? $tool['name'] ?? null;
                                    if ($name) {
                                        $availableToolNamesForDebug[] = $name;
                                    }
                                }
                                
                                $simulation['debug']['tools_sent_to_openai_after_load_' . $iteration] = [
                                    'step' => 'IMMEDIATE_OPENAI_REQUEST_AFTER_INJECTION',
                                    'available_tools_count' => count($availableTools),
                                    'normalized_tools_count' => count($normalizedTools),
                                    'tool_names_before_normalization' => $availableToolNamesForDebug,
                                    'tool_names_after_normalization' => $toolNamesAfter,
                                    'dynamically_loaded_tools_count' => count($dynamicallyLoadedTools),
                                    'dynamically_loaded_tool_names' => array_keys($dynamicallyLoadedTools),
                                    'verification' => [
                                        'all_injected_tools_available' => !empty($injectedTools) ? count(array_intersect($injectedTools, $availableToolNamesForDebug)) === count($injectedTools) : false,
                                        'injected_tools' => $injectedTools,
                                        'available_tools' => $availableToolNamesForDebug,
                                        'missing_tools' => !empty($injectedTools) ? array_diff($injectedTools, $availableToolNamesForDebug) : [],
                                    ],
                                ];
                                
                                Log::info('[CoreToolPlayground] Sofortige OpenAI-Anfrage nach Tool-Injection', [
                                    'iteration' => $iteration,
                                    'dynamically_loaded_tools' => array_keys($dynamicallyLoadedTools),
                                    'total_tools_available' => count($availableTools),
                                    'injected_tools_count' => count($injectedTools),
                                    'all_available' => !empty($injectedTools) ? count(array_intersect($injectedTools, $availableToolNamesForDebug)) === count($injectedTools) : false,
                                ]);
                                
                                // Mache SOFORT neue OpenAI-Anfrage - Tools sind jetzt verfügbar!
                                // WICHTIG: Füge System-Message hinzu, damit LLM weiß, dass Tools jetzt verfügbar sind
                                $toolsAvailableMessage = "\n\n✅ **TOOLS NACHGELADEN:**\n";
                                $toolsAvailableMessage .= "Die folgenden Tools wurden soeben nachgeladen und sind JETZT verfügbar:\n";
                                foreach ($injectedTools as $tool) {
                                    $toolsAvailableMessage .= "- {$tool}\n";
                                }
                                $toolsAvailableMessage .= "\n💡 **WICHTIG:** Du kannst diese Tools JETZT verwenden! ";
                                $toolsAvailableMessage .= "Rufe das passende Tool auf, um die User-Anfrage zu erfüllen.\n";
                                
                                // Füge System-Message hinzu (vor der sofortigen Anfrage)
                                $messagesForImmediateRequest = $messages;
                                $messagesForImmediateRequest[] = [
                                    'role' => 'system',
                                    'content' => $toolsAvailableMessage,
                                ];
                                
                                // Debug: Zeige welche Messages gesendet werden
                                $simulation['debug']['messages_before_immediate_request_' . $iteration] = [
                                    'total_messages' => count($messagesForImmediateRequest),
                                    'last_3_messages' => array_slice($messagesForImmediateRequest, -3),
                                    'has_tools_available_message' => true,
                                    'injected_tools' => $injectedTools,
                                ];
                                
                                // WICHTIG: Error-Handling - auch bei Fehler bleiben die Tools für nächste Iteration verfügbar
                                try {
                                    $response = $openAiService->chat($messagesForImmediateRequest, 'gpt-4o-mini', [
                                        'max_tokens' => 2000,
                                        'temperature' => 0.7,
                                        'tools' => null, // null = Tools aktivieren (OpenAiService ruft getAvailableTools() auf)
                                    ]);
                                    
                                    $allResponses[] = $response;
                                    
                                    $simulation['debug']['openai_response_after_load_' . $iteration] = [
                                        'status' => 'success',
                                        'has_content' => !empty($response['content']),
                                        'content_preview' => !empty($response['content']) ? substr($response['content'], 0, 200) : null,
                                        'has_tool_calls' => !empty($response['tool_calls']),
                                        'tool_calls_count' => count($response['tool_calls'] ?? []),
                                        'tool_calls' => $response['tool_calls'] ?? [],
                                        'finish_reason' => $response['finish_reason'] ?? null,
                                        'note' => 'Wenn has_tool_calls=false, hat LLM nur Text generiert statt Tool aufzurufen',
                                    ];
                                    
                                    Log::info('[CoreToolPlayground] Sofortige OpenAI-Anfrage nach Tool-Injection erfolgreich', [
                                        'iteration' => $iteration,
                                        'has_tool_calls' => !empty($response['tool_calls']),
                                        'tool_calls_count' => count($response['tool_calls'] ?? []),
                                    ]);
                                } catch (\Throwable $e) {
                                    // WICHTIG: Auch bei Fehler bleiben die Tools geladen für die nächste Iteration!
                                    // Die Tools wurden erfolgreich injiziert, nur die OpenAI-Anfrage ist fehlgeschlagen
                                    $simulation['debug']['openai_response_after_load_' . $iteration] = [
                                        'status' => 'error',
                                        'error' => $e->getMessage(),
                                        'error_class' => get_class($e),
                                        'note' => 'Tools wurden erfolgreich injiziert, aber OpenAI-Anfrage fehlgeschlagen. Tools bleiben für nächste Iteration verfügbar.',
                                        'injected_tools' => $injectedTools,
                                        'tools_still_available' => true,
                                    ];
                                    
                                    Log::warning('[CoreToolPlayground] Sofortige OpenAI-Anfrage nach Tool-Injection fehlgeschlagen', [
                                        'iteration' => $iteration,
                                        'error' => $e->getMessage(),
                                        'error_class' => get_class($e),
                                        'injected_tools' => $injectedTools,
                                        'note' => 'Tools bleiben für nächste Iteration verfügbar',
                                    ]);
                                    
                                    // Setze response auf null, damit wir zur nächsten Iteration springen
                                    // Die Tools sind geladen und werden in der nächsten Iteration verfügbar sein
                                    $response = null;
                                }
                                
                                // Prüfe ob neue Tool-Calls gemacht wurden (nur wenn response nicht null ist)
                                if ($response !== null && !empty($response['tool_calls'])) {
                                    // Neue Tool-Calls - verarbeite sie (gehe zurück zum Anfang der Tool-Execution)
                                    // Füge Assistant-Message hinzu
                                    $toolActionsText = '';
                                    if (count($response['tool_calls']) > 0) {
                                        $toolActionsText = "\n\n**🔧 Ausgeführte Aktionen:**\n";
                                        foreach ($response['tool_calls'] as $toolCall) {
                                            $toolName = $toolCall['function']['name'] ?? 'Unbekannt';
                                            $internalToolName = $this->denormalizeToolNameFromOpenAi($toolName);
                                            $toolActionsText .= "- {$internalToolName}\n";
                                        }
                                    }
                                    
                                    $messages[] = [
                                        'role' => 'assistant',
                                        'content' => ($response['content'] ?? '') . $toolActionsText,
                                        'tool_calls' => $response['tool_calls'],
                                    ];
                                    
                                    // WICHTIG: Verarbeite die neuen Tool-Calls direkt (ohne zur nächsten Iteration zu springen)
                                    // Die Tools sind jetzt verfügbar, also können wir die Tool-Calls direkt ausführen
                                    // Setze toolsWereLoaded zurück, damit wir nicht in eine Endlosschleife geraten
                                    $toolsWereLoaded = false;
                                } else if ($response === null) {
                                    // OpenAI-Anfrage fehlgeschlagen, aber Tools sind geladen
                                    // Springe zur nächsten Iteration, damit die Tools in der nächsten Runde verfügbar sind
                                    $toolsWereLoaded = false; // Reset flag, damit wir nicht in Endlosschleife geraten
                                    continue; // Springe zur nächsten Iteration
                                    
                                    // Verarbeite die neuen Tool-Calls (die gleiche Logik wie oben)
                                    // Wir sind bereits im Tool-Execution-Block, also müssen wir die Tool-Calls verarbeiten
                                    // ABER: Wir sind bereits im foreach-Loop für die vorherigen Tool-Calls
                                    // Lösung: Setze $response['tool_calls'] neu und lasse die while-Schleife die Tool-Calls verarbeiten
                                    // Oder: Verarbeite die Tool-Calls direkt hier
                                    
                                    // Einfachste Lösung: Verarbeite die Tool-Calls direkt hier, ohne zur nächsten Iteration zu springen
                                    // Wir wiederholen die Tool-Execution-Logik für die neuen Tool-Calls
                                    foreach ($response['tool_calls'] as $toolCall) {
                                        $toolCallId = $toolCall['id'] ?? null;
                                        $toolName = $toolCall['function']['name'] ?? null;
                                        $toolArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                                        
                                        if (!$toolName) continue;
                                        
                                        // Tool-Name zurückmappen
                                        $internalToolName = $this->denormalizeToolNameFromOpenAi($toolName);
                                        
                                        // Markiere Tool als verwendet
                                        $openAiService->markToolAsUsed($internalToolName);
                                        
                                        // PRE-FLIGHT (optional)
                                        $enablePreFlight = config('tools.pre_flight_verification.enabled', true);
                                        if ($enablePreFlight) {
                                            try {
                                                $preFlightService = app(\Platform\Core\Services\PreFlightIntentionService::class);
                                                $preFlightResult = $preFlightService->verify(
                                                    $message,
                                                    $internalToolName,
                                                    $toolArguments,
                                                    $allToolResults
                                                );
                                                
                                                if ($preFlightResult->hasIssues()) {
                                                    $preFlightWarning = "\n\n🚨 **PRE-FLIGHT VERIFICATION:**\n";
                                                    $preFlightWarning .= $preFlightResult->getIssuesText();
                                                    $preFlightWarning .= "\n\n⚠️ WICHTIG: Prüfe nochmal, ob das Tool wirklich das richtige ist!";
                                                    
                                                    $messages[] = [
                                                        'role' => 'system',
                                                        'content' => $preFlightWarning,
                                                    ];
                                                }
                                            } catch (\Throwable $e) {
                                                // Silent fail
                                            }
                                        }
                                        
                                        // Loop-Detection
                                        if (!isset($toolCallHistory[$internalToolName])) {
                                            $toolCallHistory[$internalToolName] = ['count' => 0, 'last_iteration' => 0, 'arguments' => []];
                                        }
                                        $toolCallHistory[$internalToolName]['count']++;
                                        $toolCallHistory[$internalToolName]['last_iteration'] = $iteration;
                                        
                                        // Loop-Detection Warning
                                        if ($toolCallHistory[$internalToolName]['count'] >= 2) {
                                            $simulation['steps'][] = [
                                                'step' => 4 + $iteration,
                                                'name' => 'Loop-Detection Warning',
                                                'description' => "Tool '{$internalToolName}' wurde bereits {$toolCallHistory[$internalToolName]['count']} mal aufgerufen",
                                                'timestamp' => now()->toIso8601String(),
                                            ];
                                        }
                                        
                                        // Tool Execution
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
                                            $resultArray = $toolResult->toArray();
                                            
                                            // Tool-Result zu Messages hinzufügen
                                            $loopCount = $toolCallHistory[$internalToolName]['count'] ?? 0;
                                            $toolResultText = $this->formatToolResultForLLM($internalToolName, $resultArray, $toolCallId, $loopCount);
                                            $messages[] = [
                                                'role' => 'user',
                                                'content' => $toolResultText,
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
                                        } catch (\Throwable $e) {
                                            $executionTime = (microtime(true) - $startTime) * 1000;
                                            $errorResult = [
                                                'ok' => false,
                                                'error' => [
                                                    'code' => 'EXECUTION_ERROR',
                                                    'message' => $e->getMessage()
                                                ]
                                            ];
                                            
                                            $loopCount = $toolCallHistory[$internalToolName]['count'] ?? 0;
                                            $errorResultText = $this->formatToolResultForLLM($internalToolName, $errorResult, $toolCallId, $loopCount);
                                            $messages[] = [
                                                'role' => 'user',
                                                'content' => $errorResultText,
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
                                    
                                    // Nach Verarbeitung der Tool-Calls: Weiter mit nächster Iteration
                                    // (die Tool-Results sind jetzt in $messages, LLM kann sie in der nächsten Iteration sehen)
                                    continue;
                                } else {
                                    // LLM hat direkt geantwortet - beende
                                    $llmContent = $response['content'] ?? 'Keine Antwort';
                                    $simulation['final_response'] = [
                                        'type' => 'direct_answer',
                                        'message' => $llmContent,
                                        'content' => $llmContent,
                                        'iterations' => $iteration,
                                        'tool_results' => $allToolResults,
                                    ];
                                    break; // Beende while-Schleife
                                }
                            } else {
                                // Weiter mit nächster Iteration (LLM bekommt Tool-Results und kann weiterarbeiten)
                                continue;
                            }
                            
                        } else {
                            // LLM hat direkt geantwortet (keine Tools mehr) - Multi-Step beendet
                            $simulation['steps'][] = [
                                'step' => 3 + $iteration,
                                'name' => "Finale Antwort (Runde {$iteration})",
                                'description' => 'LLM hat finale Antwort gegeben - keine Tools mehr',
                                'timestamp' => now()->toIso8601String(),
                            ];
                            
                            // Zeige die ECHTE LLM-Antwort (nicht nur "würde antworten")
                            $llmContent = $response['content'] ?? 'Keine Antwort';
                            
                            // Erstelle Zusammenfassung am Ende (wenn Services verfügbar)
                            $actionSummaryText = '';
                            try {
                                $actionSummaryService = app(\Platform\Core\Services\ActionSummaryService::class);
                                $summary = $actionSummaryService->createSummary(
                                    $traceId,
                                    null, // chain_id (wird später von ToolOrchestrator gesetzt)
                                    $message,
                                    $context
                                );
                                $simulation['action_summary'] = [
                                    'summary' => $summary->summary,
                                    'tools_executed' => $summary->tools_executed,
                                    'models_created' => $summary->models_created,
                                    'models_updated' => $summary->models_updated,
                                    'models_deleted' => $summary->models_deleted,
                                    'created_models' => $summary->created_models,
                                    'updated_models' => $summary->updated_models,
                                    'deleted_models' => $summary->deleted_models,
                                    'actions' => $summary->actions,
                                ];
                                
                                // Erstelle Executive Summary Text für Chat
                                if ($summary->tools_executed > 0 || $summary->models_created > 0 || $summary->models_updated > 0 || $summary->models_deleted > 0) {
                                    $actionSummaryText = "\n\n---\n**Zusammenfassung der Aktionen:**\n";
                                    $actionSummaryText .= $summary->summary . "\n\n";
                                    
                                    if ($summary->models_created > 0) {
                                        $actionSummaryText .= "✅ **Erstellt:** {$summary->models_created}\n";
                                        foreach ($summary->created_models as $model) {
                                            $actionSummaryText .= "  - {$model['model_type']} (ID: {$model['model_id']})\n";
                                        }
                                    }
                                    if ($summary->models_updated > 0) {
                                        $actionSummaryText .= "🔄 **Aktualisiert:** {$summary->models_updated}\n";
                                        foreach ($summary->updated_models as $model) {
                                            $actionSummaryText .= "  - {$model['model_type']} (ID: {$model['model_id']})\n";
                                        }
                                    }
                                    if ($summary->models_deleted > 0) {
                                        $actionSummaryText .= "🗑️ **Gelöscht:** {$summary->models_deleted}\n";
                                        foreach ($summary->deleted_models as $model) {
                                            $actionSummaryText .= "  - {$model['model_type']} (ID: {$model['model_id']})\n";
                                        }
                                    }
                                }
                                
                                // Hole auch Audit Trail
                                try {
                                    $auditTrailService = app(\Platform\Core\Services\AuditTrailService::class);
                                    $auditTrail = $auditTrailService->getAuditTrail($traceId);
                                    $simulation['audit_trail'] = $auditTrail;
                                } catch (\Throwable $e) {
                                    // Silent fail - Audit Trail optional
                                }
                            } catch (\Throwable $e) {
                                // Silent fail - Zusammenfassung optional
                                \Log::debug('[CoreToolPlayground] Zusammenfassung konnte nicht erstellt werden', [
                                    'error' => $e->getMessage(),
                                ]);
                            }
                            
                            // Intention-Verifikation (optional, konfigurierbar)
                            $verificationText = '';
                            $enableVerification = config('tools.intention_verification.enabled', true);
                            $maxCorrectionIterations = config('tools.intention_verification.max_correction_iterations', 2);
                            $verificationIteration = null;
                            
                            if ($enableVerification && count($allToolResults) > 0 && !empty($simulation['action_summary'])) {
                                try {
                                    $verificationService = app(\Platform\Core\Services\IntentionVerificationService::class);
                                    $verification = $verificationService->verify(
                                        $message, // Original User-Request
                                        $allToolResults,
                                        $simulation['action_summary']
                                    );
                                    
                                    if ($verification->hasIssues()) {
                                        $verificationText = "\n\n⚠️ **Verifikation:**\n";
                                        $verificationText .= $verification->getIssuesText();
                                        $verificationText .= "\n\nBitte prüfe die Ergebnisse und korrigiere falls nötig.";
                                        
                                        // Füge Verifikations-Hinweis zu Messages hinzu (für LLM-Korrektur)
                                        // Aber nur wenn wir noch nicht zu viele Iterationen haben
                                        $maxIterationsForCorrection = $maxIterations - $maxCorrectionIterations;
                                        if ($iteration < $maxIterationsForCorrection) {
                                            $messages[] = [
                                                'role' => 'system',
                                                'content' => $verificationText
                                            ];
                                            
                                            // Setze Flag für Verifikations-Iteration
                                            $verificationIteration = $iteration;
                                            
                                            // Weiter mit nächster Iteration (LLM kann korrigieren)
                                            $simulation['steps'][] = [
                                                'step' => 3 + $iteration,
                                                'name' => "Verifikation (Runde {$iteration})",
                                                'description' => 'Verifikation hat Probleme gefunden - LLM kann korrigieren',
                                                'timestamp' => now()->toIso8601String(),
                                                'verification_issues' => $verification->getIssuesText(),
                                            ];
                                            
                                            continue; // Weiter mit nächster Iteration
                                        } else {
                                            // Zu viele Iterationen - füge Verifikations-Hinweis zur finalen Antwort hinzu
                                            $verificationText = "\n\n⚠️ **Hinweis:** " . $verification->getIssuesText();
                                        }
                                    }
                                    
                                    $simulation['verification'] = [
                                        'is_ok' => $verification->isOk(),
                                        'has_issues' => $verification->hasIssues(),
                                        'issues_text' => $verification->hasIssues() ? $verification->getIssuesText() : null,
                                    ];
                                } catch (\Throwable $e) {
                                    // Silent fail - Verifikation optional
                                    \Log::debug('[CoreToolPlayground] Verifikation konnte nicht durchgeführt werden', [
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                            
                            // Füge Action Summary und Verifikation zur LLM-Antwort hinzu
                            $finalContent = $llmContent . $actionSummaryText . $verificationText;
                            
                            $simulation['final_response'] = [
                                'type' => 'direct_answer',
                                'message' => $finalContent, // ECHTE Antwort der LLM + Summary + Verifikation
                                'content' => $finalContent, // ECHTE Antwort der LLM + Summary + Verifikation
                                'iterations' => $iteration,
                                'tool_results' => $allToolResults,
                                'raw_response' => $response, // Vollständige Response für Debugging
                            ];
                            
                            // Füge finale Assistant-Message zu Messages hinzu (für Chat-Historie)
                            $messages[] = [
                                'role' => 'assistant',
                                'content' => $finalContent, // Mit Action Summary + Verifikation
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
                // Fallback: Nur wenn final_response noch nicht gesetzt wurde
                // (Der neue Multi-Step-Code setzt final_response bereits)
                if (!isset($simulation['final_response'])) {
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
            }

            // Füge Chat-Historie zur Response hinzu (für Frontend)
            $simulation['chat_history'] = $messages; // Aktualisierte Historie mit Tool-Results
            $simulation['session_id'] = $sessionId;
            
            return response()->json([
                'success' => true,
                'simulation' => $simulation,
                'chat_history' => $messages, // Aktualisierte Historie
                'session_id' => $sessionId,
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
     * Chat-Historie löschen (neuer Thread starten)
     */
    public function clear(Request $request)
    {
        try {
            $request->validate([
                'session_id' => 'nullable|string',
            ]);

            $sessionId = $request->input('session_id');
            
            // Wenn Session-ID angegeben, lösche nur diese
            if ($sessionId) {
                session()->forget("playground_chat_history_{$sessionId}");
            } else {
                // Wenn keine Session-ID, lösche alle Playground-Sessions
                $allKeys = array_keys(session()->all());
                foreach ($allKeys as $key) {
                    if (str_starts_with($key, 'playground_chat_history_')) {
                        session()->forget($key);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Chat-Historie gelöscht. Neuer Thread startet.',
                'session_id' => $sessionId,
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
        // 
        // ❌ KEINE Pattern-basierte Kategorisierung!
        // ❌ KEINE Intent-Erkennung!
        // ❌ KEINE automatischen Entscheidungen!
        // 
        // Die LLM entscheidet selbst, ob sie Tools braucht oder nicht.
        // Diese Methode gibt nur Info zurück für Debug-Zwecke.
        
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
            'intent_type' => 'unclear', // Immer unclear - LLM entscheidet selbst
            'can_solve_independently' => null, // LLM entscheidet selbst
            'reason' => 'LLM sieht alle Tools und entscheidet selbst, ob sie welche braucht (MCP Best Practice)',
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
        // Wir müssen das rückgängig machen - nutze ToolNameMapper für intelligente Suche
        try {
            $nameMapper = app(\Platform\Core\Services\ToolNameMapper::class);
            return $nameMapper->toCanonical($openAiName);
        } catch (\Throwable $e) {
            // Fallback: Einfaches Mapping (für Backwards-Kompatibilität)
            \Log::warning("[CoreToolPlaygroundController] ToolNameMapper nicht verfügbar, verwende Fallback", [
                'openai_name' => $openAiName,
                'error' => $e->getMessage()
            ]);
            return str_replace('_', '.', $openAiName);
        }
    }
    
    /**
     * Formatiert Tool-Results für die LLM - strukturiert und lesbar
     * 
     * Die LLM sollte aus der REST-Syntax selbst darauf kommen, welches Tool als nächstes aufgerufen werden muss.
     * Diese Methode formatiert die Results so, dass die LLM die Informationen klar erkennt.
     */
    private function formatToolResultForLLM(string $toolName, array $resultArray, ?string $toolCallId = null, int $loopCount = 0): string
    {
        $success = $resultArray['ok'] ?? ($resultArray['success'] ?? false);
        $data = $resultArray['data'] ?? $resultArray;
        $error = $resultArray['error'] ?? null;
        
        // Basis-Format: Tool-Name und Status
        $text = "Tool-Result: {$toolName}\n";
        if ($toolCallId) {
            $text .= "Call-ID: {$toolCallId}\n";
        }
        $text .= "Status: " . ($success ? "✅ Erfolgreich" : "❌ Fehler") . "\n\n";
        
        // Bei Fehler: Zeige Fehler-Informationen
        if (!$success && $error) {
            $errorMessage = is_array($error) ? ($error['message'] ?? json_encode($error)) : $error;
            $text .= "Fehler: {$errorMessage}\n";
            return $text;
        }
        
        // Bei Erfolg: Formatiere Daten strukturiert
        if ($success && is_array($data)) {
            // Spezielle Formatierung für bekannte Tools
            if ($toolName === 'core.teams.GET' && isset($data['teams'])) {
                $text .= "Teams gefunden: " . ($data['count'] ?? count($data['teams'])) . "\n";
                if (isset($data['current_team_id'])) {
                    $teamName = $data['current_team_name'] ?? 'Unbekannt';
                    $text .= "Aktuelles Team: ID {$data['current_team_id']} ({$teamName})\n";
                }
                if (!empty($data['teams']) && is_array($data['teams'])) {
                    $text .= "\nTeams:\n";
                    foreach (array_slice($data['teams'], 0, 10) as $team) {
                        $text .= "- ID {$team['id']}: {$team['name']}" . (isset($team['is_current']) && $team['is_current'] ? ' (aktuell)' : '') . "\n";
                    }
                }
                
                // WICHTIG: Wenn der User nach Projekten/Companies/Contacts fragt, rufe direkt das entsprechende Tool auf
                // Die Team-ID ist bereits bekannt - du musst core.teams.GET NICHT nochmal aufrufen!
                if ($loopCount === 0) {
                    // Erstes Mal: Normale Info
                    $text .= "\n\n💡 HINWEIS: Wenn der User nach Projekten, Companies oder Contacts fragt, rufe DIREKT 'planner.projects.GET', 'crm.companies.GET' oder 'crm.contacts.GET' auf. ";
                    $text .= "Diese Tools verwenden automatisch das aktuelle Team (ID {$data['current_team_id']}) wenn du team_id weglässt.";
                } else {
                    // Loop erkannt: Stärkere Warnung
                    $text .= "\n\n🚨 WICHTIG - LOOP ERKANNT: Du hast 'core.teams.GET' bereits {$loopCount} mal aufgerufen! ";
                    $text .= "Du hast die Team-Informationen bereits - rufe JETZT das nächste Tool auf!\n\n";
                    $text .= "⚠️ **KRITISCH:** Das Tool 'planner.projects.GET' ist möglicherweise NICHT in deiner Tool-Liste verfügbar!\n";
                    $text .= "📋 **LÖSUNG:** Rufe ZUERST 'tools.GET' mit module='planner' auf, um die benötigten Tools zu laden!\n\n";
                    $text .= "✅ RICHTIG (Schritt 1): Rufe 'tools.GET' auf mit: {\"module\": \"planner\", \"read_only\": true}\n";
                    $text .= "✅ RICHTIG (Schritt 2): Nach dem Nachladen rufe 'planner.projects.GET' auf (ohne team_id Parameter - verwendet automatisch Team ID {$data['current_team_id']})\n";
                    $text .= "❌ FALSCH: Rufe 'core.teams.GET' nochmal auf\n\n";
                    $text .= "Die benötigten Informationen sind bereits in den vorherigen Tool-Results vorhanden. ";
                    $text .= "Prüfe die Tool-Results und rufe das RICHTIGE Tool auf!";
                }
            } elseif ($toolName === 'planner.projects.GET' && isset($data['projects'])) {
                $text .= "Projekte gefunden: " . ($data['count'] ?? count($data['projects'])) . "\n";
                if (isset($data['team_id'])) {
                    $text .= "Team-ID: {$data['team_id']}\n";
                }
            } elseif ($toolName === 'tools.GET' && isset($data['tools'])) {
                $text .= "Tools gefunden: " . ($data['summary']['filtered_tools'] ?? count($data['tools'])) . "\n";
            } else {
                // Generische Formatierung: Zeige wichtige Felder
                $text .= "Daten:\n";
                foreach ($data as $key => $value) {
                    if (is_array($value) && count($value) > 5) {
                        $text .= "- {$key}: " . count($value) . " Einträge\n";
                    } elseif (!is_array($value) && !is_object($value)) {
                        $text .= "- {$key}: {$value}\n";
                    }
                }
            }
            
            // Zeige vollständige Daten als JSON (für komplexe Strukturen)
            $text .= "\nVollständige Daten (JSON):\n" . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            // Fallback: Zeige rohe Daten
            $text .= "Daten: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        
        return $text;
    }
    
}
