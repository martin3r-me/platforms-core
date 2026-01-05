<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Services\ToolNameMapper;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Minimaler Tool Controller
 * 
 * Einfacher Flow ohne Over-Engineering:
 * 1. User Message empfangen
 * 2. Tools laden
 * 3. LLM aufrufen
 * 4. Tool-Calls ausführen
 * 5. Wiederholen bis fertig (max. 10 Iterationen)
 */
class SimpleToolController extends Controller
{
    private const MAX_ITERATIONS = 10;

    /**
     * NOTE: Dieser Controller wird gerade auf "Chat only" vereinfacht.
     * Tools bleiben bewusst aus, bis Streaming/Reasoning sauber steht.
     */
    public function handle(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'chat_history' => 'nullable|array', // Conversation-Historie
        ]);

        $userMessage = $request->input('message');
        $chatHistory = $request->input('chat_history', []);
        
        // Services
        $registry = app(ToolRegistry::class);
        $executor = app(ToolExecutor::class);
        $openAiService = app(OpenAiService::class);
        $context = ToolContext::fromAuth();

        // Initialisiere Messages mit Historie
        $messages = [];
        
        // Füge Chat-Historie hinzu (falls vorhanden)
        if (!empty($chatHistory) && is_array($chatHistory)) {
            foreach ($chatHistory as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content'],
                    ];
                }
            }
        }
        
        // Füge neue User-Message hinzu
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        // Lade alle Tools
        $allTools = $registry->all();
        $availableTools = $openAiService->getAvailableTools();

        $toolResults = [];
        $iteration = 0;

        // Multi-Step Loop
        while ($iteration < self::MAX_ITERATIONS) {
            $iteration++;

            try {
                // LLM aufrufen
                // Simple Playground: fixed model for now (explicit user request)
                $response = $openAiService->chat($messages, 'gpt-5.2', [
                    'max_tokens' => 2000,
                    'temperature' => 0.7,
                    'tools' => false, // Tools komplett aus
                    'with_context' => false,
                ]);

                $content = $response['content'] ?? '';
                $toolCalls = $response['tool_calls'] ?? [];

                // Wenn keine Tool-Calls: Fertig
                if (empty($toolCalls)) {
                    // Füge Assistant-Response zu Messages hinzu
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $content,
                    ];
                    
                    return response()->json([
                        'success' => true,
                        'message' => $content,
                        'iterations' => $iteration,
                        'tool_results' => $toolResults,
                        'chat_history' => $messages, // Sende aktualisierte Historie zurück
                    ]);
                }

                // Tools sind aktuell deaktiviert → wenn das Model trotzdem Tool-Calls liefert, brechen wir ab
                return response()->json([
                    'success' => false,
                    'error' => 'Tool-Calls sind im Simple Playground aktuell deaktiviert.',
                    'iterations' => $iteration,
                    'tool_results' => $toolResults,
                    'chat_history' => $messages,
                ], 400);

            } catch (\Throwable $e) {
                Log::error('[SimpleToolController] Error in iteration', [
                    'iteration' => $iteration,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'iterations' => $iteration,
                    'tool_results' => $toolResults,
                ], 500);
            }
        }

        // Max Iterations erreicht
        return response()->json([
            'success' => false,
            'error' => 'Maximale Anzahl von Iterationen erreicht',
            'iterations' => $iteration,
            'tool_results' => $toolResults,
            'last_message' => $messages[count($messages) - 1]['content'] ?? null,
            'chat_history' => $messages, // Sende aktualisierte Historie zurück
        ]);
    }

    /**
     * List available OpenAI models (for UI selection).
     * NOTE: This reflects the account/API key capabilities.
     */
    public function models(Request $request): JsonResponse
    {
        // Simple Playground: fixed model for now (explicit user request)
        $ids = ['gpt-5.2'];

        return response()->json([
            'success' => true,
            'models' => $ids,
            'count' => count($ids),
            'fallback' => true,
        ]);
    }

    /**
     * Normalisiert Tool-Namen von OpenAI Format zu Internal Format
     * z.B. "planner_projects_GET" → "planner.projects.GET"
     */
    private function denormalizeToolName(string $openAiName): string
    {
        // Ersetze Unterstriche durch Punkte
        $name = str_replace('_', '.', $openAiName);
        
        // Wenn letzter Teil GET/POST/PUT/DELETE ist, großschreiben
        $parts = explode('.', $name);
        if (count($parts) > 1) {
            $lastPart = strtoupper($parts[count($parts) - 1]);
            if (in_array($lastPart, ['GET', 'POST', 'PUT', 'DELETE'])) {
                $parts[count($parts) - 1] = $lastPart;
                $name = implode('.', $parts);
            }
        }
        
        return $name;
    }

    /**
     * Formatiert Tool-Result für LLM
     */
    private function formatToolResult(string $toolName, ToolResult $result, ?string $toolCallId = null): string
    {
        $text = "Tool-Result: {$toolName}\n";
        if ($toolCallId) {
            $text .= "Call-ID: {$toolCallId}\n";
        }
        $text .= "Status: " . ($result->success ? "Erfolgreich" : "Fehler") . "\n\n";

        if (!$result->success) {
            $error = is_string($result->error) ? $result->error : ($result->errorCode ?? 'Unbekannter Fehler');
            $text .= "Fehler: {$error}\n";
            return $text;
        }

        $data = $result->data ?? [];
        if (empty($data)) {
            $text .= "Keine Daten zurückgegeben.\n";
            return $text;
        }

        // Formatiere Daten lesbar
        if (is_array($data)) {
            // Versuche strukturierte Ausgabe
            if (isset($data['message'])) {
                $text .= $data['message'] . "\n\n";
            }

            // Zeige wichtige Felder
            $importantFields = ['id', 'name', 'title', 'count', 'total', 'items', 'data'];
            foreach ($importantFields as $field) {
                if (isset($data[$field])) {
                    if (is_array($data[$field]) && count($data[$field]) > 0) {
                        $text .= ucfirst($field) . ": " . count($data[$field]) . " Einträge\n";
                    } elseif (!is_array($data[$field])) {
                        $text .= ucfirst($field) . ": " . $data[$field] . "\n";
                    }
                }
            }

            // Vollständige Daten als JSON
            $text .= "\nVollständige Daten (JSON):\n";
            $text .= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $text .= "Daten: " . (string)$data . "\n";
        }

        return $text;
    }

    /**
     * SSE-Streaming Version
     */
    public function stream(Request $request): StreamedResponse
    {
        return new StreamedResponse(function() use ($request) {
            // Output Buffering deaktivieren
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $sendEvent = function(string $event, array $data) {
                echo "event: {$event}\n";
                echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            };

            try {
                // Debug: Eingehende Header/Body (hilft bei 400)
                $sendEvent('debug.request', [
                    'content_type' => $request->header('Content-Type'),
                    'accept' => $request->header('Accept'),
                ]);

                try {
                    $request->validate([
                        'message' => 'required|string',
                        'chat_history' => 'nullable|array', // Conversation-Historie
                        'model' => 'nullable|string',
                    ]);
                } catch (\Throwable $e) {
                    $sendEvent('error', [
                        'error' => 'Validation failed: ' . $e->getMessage(),
                    ]);
                    return;
                }

                $userMessage = $request->input('message');
                $chatHistory = $request->input('chat_history', []);
                // Simple Playground: fixed model for now (explicit user request)
                $model = 'gpt-5.2';

                // (Request model is ignored)
                
                // Services
                $openAiService = app(OpenAiService::class);
                $executor = app(ToolExecutor::class);
                $nameMapper = app(ToolNameMapper::class);
                $context = ToolContext::fromAuth();
                $openAiService->resetDynamicallyLoadedTools();

                // Initialisiere Messages mit Historie (nur role+content)
                $messages = [];

                // Minimal system prompt: no app-route leakage, but enables tool usage + German output
                $messages[] = [
                    'role' => 'system',
                    'content' => "Du bist ein Assistent innerhalb einer Plattform und hast Zugriff auf Tools (Function Calling).\n"
                        . "Antworte immer auf Deutsch.\n"
                        . "Wenn der Nutzer Daten aus der Plattform will, nutze Tools statt zu sagen, du hättest keinen Zugriff.\n"
                        . "Du siehst anfangs nur Discovery-Tools (z.B. tools.GET, core.teams.GET). Nutze tools.GET mit module/search, um weitere Tools zu entdecken.\n"
                        . "Wenn es um Teams in der Plattform geht, nutze core.teams.GET.\n"
                        . "Wenn Kontext nötig ist, rufe core.context.GET auf.\n",
                ];
                
                // Füge Chat-Historie hinzu (falls vorhanden)
                if (!empty($chatHistory) && is_array($chatHistory)) {
                    foreach ($chatHistory as $msg) {
                        if (isset($msg['role']) && isset($msg['content'])) {
                            $messages[] = [
                                'role' => $msg['role'],
                                'content' => $msg['content'],
                            ];
                        }
                    }
                }
                
                // Füge neue User-Message hinzu
                $messages[] = [
                    'role' => 'user',
                    'content' => $userMessage,
                ];

                $sendEvent('start', ['message' => '🚀 Starte...']);

                // Best-practice tool loop (stream per iteration; execute tools server-side; continue until no tool calls)
                $maxIterations = 6;
                $usageSent = false;
                $debugEventCount = 0;

                for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
                    $assistant = '';
                    $reasoning = '';
                    $thinking = '';
                    $toolCallsCollector = [];
                    $currentStreamingToolCall = null; // openai tool name
                    $currentStreamingToolId = null;   // item.id
                    $currentStreamingCallId = null;   // item.call_id

                    $sendEvent('assistant.reset', []);
                    $sendEvent('reasoning.reset', []);
                    $sendEvent('thinking.reset', []);
                    $sendEvent('openai.event', [
                        'event' => 'server.iteration.start',
                        'preview' => ['iteration' => $iteration],
                        'raw' => null,
                    ]);

                    // Debug: show which tools the model can see in THIS iteration
                    try {
                        $container = app();
                        $isBound = $container->bound(\Platform\Core\Tools\ToolRegistry::class);
                        $registryCount = null;
                        $registryNamesSample = [];
                        try {
                            if ($isBound) {
                                $reg = $container->make(\Platform\Core\Tools\ToolRegistry::class);
                                $registryCount = count($reg->all());
                                $registryNamesSample = array_slice($reg->names(), 0, 30);
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }

                        $reflection = new \ReflectionClass($openAiService);
                        $getToolsMethod = $reflection->getMethod('getAvailableTools');
                        $getToolsMethod->setAccessible(true);
                        $availableTools = $getToolsMethod->invoke($openAiService); // internal tool defs

                        $normalizeMethod = $reflection->getMethod('normalizeToolsForResponses');
                        $normalizeMethod->setAccessible(true);
                        $normalized = $normalizeMethod->invoke($openAiService, $availableTools); // responses tool format

                        $names = [];
                        foreach ($normalized as $t) {
                            $type = $t['type'] ?? null;
                            $name = $t['name'] ?? null;
                            if ($type && $name) {
                                $names[] = $type . ':' . $name;
                            } elseif ($name) {
                                $names[] = (string)$name;
                            } elseif ($type) {
                                $names[] = (string)$type;
                            }
                        }
                        sort($names);

                        $sendEvent('debug.tools', [
                            'iteration' => $iteration,
                            'include_web_search' => true,
                            'registry_bound' => $isBound,
                            'registry_count' => $registryCount,
                            'registry_names_sample' => $registryNamesSample,
                            'tools_count' => count($names) + 1, // +web_search
                            'tools' => array_merge(['web_search'], $names),
                            'dynamically_loaded' => $openAiService->getDynamicallyLoadedTools(),
                        ]);
                    } catch (\Throwable $e) {
                        $sendEvent('debug.tools', [
                            'iteration' => $iteration,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    try {
                        $openAiService->streamChat(
                            $messages,
                            function(string $delta) use ($sendEvent, &$assistant) {
                                $assistant .= $delta;
                                $sendEvent('assistant.delta', ['delta' => $delta, 'content' => $assistant]);
                            },
                            $model,
                            [
                                'include_web_search' => true,
                                'max_tokens' => 2000,
                                'with_context' => false,
                                'reasoning' => [
                                    'effort' => 'medium',
                                ],
                                'on_debug' => function(?string $event, array $decoded) use (
                                    $sendEvent,
                                    &$debugEventCount,
                                    &$usageSent,
                                    &$toolCallsCollector,
                                    &$currentStreamingToolCall,
                                    &$currentStreamingToolId,
                                    &$currentStreamingCallId
                                ) {
                                    $event = $event ?? '';
                                    if ($debugEventCount < 2000) {
                                        $debugEventCount++;
                                        $preview = [
                                            'keys' => array_keys($decoded),
                                            'type' => $decoded['type'] ?? ($decoded['item']['type'] ?? null),
                                            'id' => $decoded['id'] ?? ($decoded['item']['id'] ?? ($decoded['item_id'] ?? null)),
                                            'name' => $decoded['name'] ?? ($decoded['item']['name'] ?? null),
                                            'status' => $decoded['status'] ?? null,
                                            'query' => $decoded['query'] ?? ($decoded['input']['query'] ?? ($decoded['item']['query'] ?? null)),
                                        ];
                                        $raw = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                                        if (is_string($raw) && strlen($raw) > 2000) {
                                            $raw = substr($raw, 0, 2000) . '…';
                                        }
                                        $sendEvent('openai.event', [
                                            'event' => $event,
                                            'preview' => array_filter($preview, fn($v) => $v !== null && $v !== ''),
                                            'raw' => $raw,
                                        ]);
                                    }

                                    // Emit token usage once it appears (usually on response.completed)
                                    if (!$usageSent) {
                                        $usage = $decoded['response']['usage'] ?? null;
                                        if (is_array($usage) && !empty($usage)) {
                                            $usageSent = true;
                                            $sendEvent('usage', [
                                                'model' => $decoded['response']['model'] ?? null,
                                                'usage' => $usage,
                                            ]);
                                        }
                                    }

                                    // Collect function calls (best practice: execute after stream, then re-call model with tool results)
                                    if ($event === 'response.output_item.added' && isset($decoded['item']['type']) && $decoded['item']['type'] === 'function_call') {
                                        $openAiToolName = $decoded['item']['name'] ?? null;
                                        $itemId = $decoded['item']['id'] ?? null;
                                        $callId = $decoded['item']['call_id'] ?? null;
                                        if (is_string($openAiToolName) && $openAiToolName !== '' && is_string($callId) && $callId !== '') {
                                            $currentStreamingToolCall = $openAiToolName;
                                            $currentStreamingToolId = is_string($itemId) ? $itemId : null;
                                            $currentStreamingCallId = $callId;
                                            $toolCallsCollector[$callId] = [
                                                'id' => $currentStreamingToolId,
                                                'call_id' => $callId,
                                                'name' => $openAiToolName,
                                                'arguments' => '',
                                            ];
                                        }
                                    } elseif ($event === 'response.function_call_arguments.delta') {
                                        $delta = $decoded['delta'] ?? '';
                                        if ($currentStreamingCallId && is_string($delta) && isset($toolCallsCollector[$currentStreamingCallId])) {
                                            $toolCallsCollector[$currentStreamingCallId]['arguments'] .= $delta;
                                        }
                                    } elseif ($event === 'response.function_call_arguments.done') {
                                        $args = $decoded['arguments'] ?? '';
                                        if ($currentStreamingCallId && is_string($args) && isset($toolCallsCollector[$currentStreamingCallId])) {
                                            $toolCallsCollector[$currentStreamingCallId]['arguments'] = $args;
                                        }
                                        $currentStreamingToolCall = null;
                                        $currentStreamingToolId = null;
                                        $currentStreamingCallId = null;
                                    }
                                },
                                'on_reasoning_delta' => function(string $delta) use ($sendEvent, &$reasoning) {
                                    $reasoning .= $delta;
                                    $sendEvent('reasoning.delta', ['delta' => $delta, 'content' => $reasoning]);
                                },
                                'on_thinking_delta' => function(string $delta) use ($sendEvent, &$thinking) {
                                    $thinking .= $delta;
                                    $sendEvent('thinking.delta', ['delta' => $delta, 'content' => $thinking]);
                                },
                            ]
                        );
                    } catch (\Throwable $e) {
                        $sendEvent('error', [
                            'error' => 'OpenAI stream failed: ' . $e->getMessage(),
                        ]);
                        return;
                    }

                    // No tool calls → final answer
                    if (empty($toolCallsCollector)) {
                        $messages[] = ['role' => 'assistant', 'content' => $assistant];
                        $sendEvent('complete', [
                            'assistant' => $assistant,
                            'reasoning' => $reasoning,
                            'thinking' => $thinking,
                            'chat_history' => $messages,
                        ]);
                        return;
                    }

                    // Execute tool calls
                    foreach ($toolCallsCollector as $callId => $call) {
                        $openAiToolName = $call['name'] ?? '';
                        $canonical = $nameMapper->toCanonical($openAiToolName);
                        $argsJson = $call['arguments'] ?? '';
                        $args = json_decode($argsJson, true);
                        if (!is_array($args)) { $args = []; }

                        // Add the tool call item back into the input (best practice for Responses API)
                        $messages[] = [
                            'type' => 'function_call',
                            'id' => $call['id'] ?? null,
                            'call_id' => $callId,
                            'name' => $openAiToolName,
                            'arguments' => $argsJson,
                        ];

                        $sendEvent('openai.event', [
                            'event' => 'server.tool.execute',
                            'preview' => ['tool' => $canonical],
                            'raw' => null,
                        ]);

                        try {
                            $t0 = microtime(true);
                            $toolResult = $executor->execute($canonical, $args, $context);
                            $ms = (int) round((microtime(true) - $t0) * 1000);
                            $toolArray = $toolResult->toArray();
                            
                            $sendEvent('tool.executed', [
                                'tool' => $canonical,
                                'call_id' => $callId,
                                'success' => (bool) ($toolArray['success'] ?? false),
                                'ms' => $ms,
                            ]);

                            // Special case: tools.GET with explicit filters → load tools dynamically for next iteration
                            if ($canonical === 'tools.GET') {
                                $module = $args['module'] ?? null;
                                $search = $args['search'] ?? null;
                                $hasExplicitRequest = !empty($module) || !empty($search);
                                if ($hasExplicitRequest) {
                                    $toolsData = $toolArray['data']['tools'] ?? $toolArray['tools'] ?? [];
                                    $requestedTools = [];
                                    if (is_array($toolsData)) {
                                        foreach ($toolsData as $t) {
                                            $n = $t['name'] ?? null;
                                            if (is_string($n) && $n !== '') { $requestedTools[] = $n; }
                                        }
                                    }
                                    if (!empty($requestedTools)) {
                                        $openAiService->loadToolsDynamically($requestedTools);
                                        $sendEvent('openai.event', [
                                            'event' => 'server.tools.loaded',
                                            'preview' => ['count' => count($requestedTools)],
                                            'raw' => json_encode(['tools' => $requestedTools], JSON_UNESCAPED_UNICODE),
                                        ]);
                                    }
                                }
                            }

                            $messages[] = [
                                'type' => 'function_call_output',
                                'call_id' => $callId,
                                'output' => json_encode($toolArray, JSON_UNESCAPED_UNICODE),
                            ];
                        } catch (\Throwable $e) {
                            $messages[] = [
                                'type' => 'function_call_output',
                                'call_id' => $callId,
                                'output' => json_encode([
                                    'success' => false,
                                    'error' => $e->getMessage(),
                                ], JSON_UNESCAPED_UNICODE),
                            ];
                            $sendEvent('tool.executed', [
                                'tool' => $canonical,
                                'call_id' => $callId,
                                'success' => false,
                                'ms' => null,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $sendEvent('error', [
                    'error' => 'Max iterations reached while executing tools.',
                ]);
                return;

            } catch (\Throwable $e) {
                $sendEvent('error', ['error' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

