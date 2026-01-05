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
                        . "Für Tool-Discovery nutze tools.GET (nicht core.modules.GET mit include_tools=true – das ist sehr groß und kostet viele Tokens).\n"
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
                // Keep a hard safety cap, but allow enough room for "batch" operations
                // like renaming/updating multiple tasks.
                $maxIterations = 12;
                $maxToolExecutions = 60;
                $toolExecutionCount = 0;
                $debugEventCount = 0;
                $previousResponseId = null;
                $messagesForApi = $messages; // iteration 1: full conversation input
                $toolResultCache = []; // per-request cache: canonical+args -> ToolResult payload
                $stableNormalize = function($v) use (&$stableNormalize) {
                    if (is_array($v)) {
                        // Sort associative keys for stable hashing (preserve list order)
                        $isAssoc = array_keys($v) !== range(0, count($v) - 1);
                        if ($isAssoc) { ksort($v); }
                        foreach ($v as $k => $vv) {
                            $v[$k] = $stableNormalize($vv);
                        }
                    }
                    return $v;
                };
                
                // Aggregate token usage across the whole request (multiple Responses API calls/iterations).
                $usageAggregate = [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'total_tokens' => 0,
                    'input_tokens_details' => ['cached_tokens' => 0],
                    'output_tokens_details' => ['reasoning_tokens' => 0],
                ];
                $seenUsageResponseIds = [];

                for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
                    $assistant = '';
                    $reasoning = '';
                    $thinking = '';
                    $toolCallsCollector = [];
                    $currentStreamingToolCall = null; // openai tool name
                    $currentStreamingToolId = null;   // item.id
                    $currentStreamingCallId = null;   // item.call_id
                    $currentResponseId = null;

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
                            $messagesForApi,
                            function(string $delta) use ($sendEvent, &$assistant) {
                                $assistant .= $delta;
                                $sendEvent('assistant.delta', ['delta' => $delta, 'content' => $assistant]);
                            },
                            $model,
                            [
                                'include_web_search' => true,
                                'max_tokens' => 2000,
                                'with_context' => false,
                                'previous_response_id' => $previousResponseId,
                                'reasoning' => [
                                    'effort' => 'medium',
                                ],
                                'on_debug' => function(?string $event, array $decoded) use (
                                    $sendEvent,
                                    &$debugEventCount,
                                    &$usageAggregate,
                                    &$seenUsageResponseIds,
                                    &$iteration,
                                    &$toolCallsCollector,
                                    &$currentStreamingToolCall,
                                    &$currentStreamingToolId,
                                    &$currentStreamingCallId,
                                    &$currentResponseId
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

                                    // Capture response id so we can continue via previous_response_id (best practice)
                                    if ($event === 'response.created') {
                                        $rid = $decoded['response']['id'] ?? null;
                                        if (is_string($rid) && $rid !== '') {
                                            $currentResponseId = $rid;
                                        }
                                    }

                                    // Aggregate token usage across the whole request.
                                    // Usage usually appears on response.completed (but we handle any event carrying it).
                                    $usage = $decoded['response']['usage'] ?? null;
                                    if (is_array($usage) && !empty($usage)) {
                                        $rid = $decoded['response']['id'] ?? $currentResponseId;
                                        if (!is_string($rid) || $rid === '') {
                                            $rid = 'unknown';
                                        }
                                        if (!isset($seenUsageResponseIds[$rid])) {
                                            $seenUsageResponseIds[$rid] = true;

                                            $in = (int) ($usage['input_tokens'] ?? 0);
                                            $out = (int) ($usage['output_tokens'] ?? 0);
                                            $tot = (int) ($usage['total_tokens'] ?? 0);
                                            $cached = (int) ($usage['input_tokens_details']['cached_tokens'] ?? 0);
                                            $reasonTok = (int) ($usage['output_tokens_details']['reasoning_tokens'] ?? 0);

                                            $usageAggregate['input_tokens'] += $in;
                                            $usageAggregate['output_tokens'] += $out;
                                            $usageAggregate['total_tokens'] += $tot;
                                            $usageAggregate['input_tokens_details']['cached_tokens'] += $cached;
                                            $usageAggregate['output_tokens_details']['reasoning_tokens'] += $reasonTok;

                                            $sendEvent('usage', [
                                                'model' => $decoded['response']['model'] ?? null,
                                                'iteration' => $iteration,
                                                'cumulative' => true,
                                                'usage' => $usageAggregate,
                                                'last_increment' => [
                                                    'input_tokens' => $in,
                                                    'output_tokens' => $out,
                                                    'total_tokens' => $tot,
                                                    'cached_tokens' => $cached,
                                                    'reasoning_tokens' => $reasonTok,
                                                ],
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
                    $toolOutputsForNextIteration = [];
                    foreach ($toolCallsCollector as $callId => $call) {
                        $toolExecutionCount++;
                        if ($toolExecutionCount > $maxToolExecutions) {
                            $sendEvent('complete', [
                                'assistant' => "⚠️ Abbruch: Zu viele Tool-Ausführungen in einem Request (>{$maxToolExecutions}). "
                                    . "Bitte die Aktion in kleinere Schritte teilen oder das Tool um Batch-Operationen erweitern.",
                                'reasoning' => $reasoning,
                                'thinking' => $thinking,
                                'chat_history' => $messages,
                            ]);
                            return;
                        }

                        $openAiToolName = $call['name'] ?? '';
                        $canonical = $nameMapper->toCanonical($openAiToolName);
                        $argsJson = $call['arguments'] ?? '';
                        $args = json_decode($argsJson, true);
                        if (!is_array($args)) { $args = []; }

                        $sendEvent('openai.event', [
                            'event' => 'server.tool.execute',
                            'preview' => ['tool' => $canonical],
                            'raw' => null,
                        ]);

                        try {
                            // Dedup/cache: if the model calls the same tool with the same args repeatedly
                            // (common in early iterations), reuse the previous result to avoid cost.
                            $isGet = (bool) preg_match('/\.GET$/', $canonical);
                            $isWrite = (bool) preg_match('/\.(POST|PUT|DELETE)$/', $canonical);
                            $isCacheable = $isGet; // cache only reads (GET)

                            // Stable cache key: normalize args (sort object keys recursively) so equivalent JSON
                            // with different key order/whitespace still hits the cache.
                            $normalizedArgs = $stableNormalize($args);
                            $normalizedArgsJson = json_encode($normalizedArgs, JSON_UNESCAPED_UNICODE);
                            if (!is_string($normalizedArgsJson)) { $normalizedArgsJson = ''; }
                            $argsHash = md5($normalizedArgsJson);
                            $cacheKey = $canonical . '|' . $argsHash;
                            $cached = $isCacheable && array_key_exists($cacheKey, $toolResultCache);

                            if ($cached) {
                                $cachedEntry = $toolResultCache[$cacheKey];
                                $toolResult = $cachedEntry['toolResult'];
                                $ms = 0;
                                $toolArray = $cachedEntry['toolArray'];
                                $retriesUsed = 0;
                            } else {
                                $retriesUsed = 0;
                                $attempts = ($isGet ? 3 : 1); // GET: up to 2 retries on transient EXECUTION_ERROR
                                $t0 = microtime(true);
                                do {
                                    $toolResult = $executor->execute($canonical, $args, $context);
                                    if ($toolResult->success) { break; }
                                    if (!$isGet) { break; }
                                    // Retry only on transient execution errors
                                    if (($toolResult->errorCode ?? null) !== 'EXECUTION_ERROR') { break; }
                                    $retriesUsed++;
                                    usleep(150_000 * $retriesUsed); // 150ms, 300ms
                                } while ($retriesUsed < ($attempts - 1));
                                $ms = (int) round((microtime(true) - $t0) * 1000);
                                $toolArray = $toolResult->toArray();

                                // Cache only successful GET results (never cache failures)
                                if ($isCacheable && $toolResult->success) {
                                    $toolResultCache[$cacheKey] = [
                                        'toolResult' => $toolResult,
                                        'toolArray' => $toolArray,
                                    ];
                                }
                            }
                            
                            $argsPreview = $normalizedArgsJson;
                            if (is_string($argsPreview) && strlen($argsPreview) > 180) {
                                $argsPreview = substr($argsPreview, 0, 180) . '…';
                            }

                            $sendEvent('tool.executed', [
                                'tool' => $canonical,
                                'call_id' => $callId,
                                // ToolResult->toArray uses 'ok' not 'success' – use the canonical truth.
                                'success' => (bool) $toolResult->success,
                                'ms' => $ms,
                                'error_code' => $toolResult->success ? null : ($toolResult->errorCode ?? null),
                                'error' => $toolResult->success ? null : ($toolResult->error ?? ($toolArray['error']['message'] ?? null)),
                                'cached' => $cached,
                                'retries' => $retriesUsed,
                                'args_hash' => $argsHash,
                                'args_preview' => $argsPreview,
                            ]);

                            // After successful writes, invalidate cached GETs so follow-up reads are fresh.
                            if ($isWrite && $toolResult->success) {
                                $toolResultCache = [];
                            }

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

                            $outputItem = [
                                'type' => 'function_call_output',
                                'call_id' => $callId,
                                'output' => json_encode($toolArray, JSON_UNESCAPED_UNICODE),
                            ];
                            // Keep for debug/history
                            $messages[] = $outputItem;
                            // Next iteration input: ONLY tool outputs (no synthetic function_call items)
                            $toolOutputsForNextIteration[] = $outputItem;
                        } catch (\Throwable $e) {
                            $outputItem = [
                                'type' => 'function_call_output',
                                'call_id' => $callId,
                                'output' => json_encode([
                                    'success' => false,
                                    'error' => $e->getMessage(),
                                ], JSON_UNESCAPED_UNICODE),
                            ];
                            $messages[] = $outputItem;
                            $toolOutputsForNextIteration[] = $outputItem;
                            $sendEvent('tool.executed', [
                                'tool' => $canonical,
                                'call_id' => $callId,
                                'success' => false,
                                'ms' => null,
                                'error' => $e->getMessage(),
                                'cached' => false,
                            ]);
                        }
                    }

                    // Continue the tool loop using Responses API best practice:
                    // Provide tool outputs as the ONLY next input, and chain via previous_response_id.
                    $previousResponseId = $currentResponseId;
                    $messagesForApi = $toolOutputsForNextIteration;
                }

                // Safety: do not end with a hard error in the chat UI; provide a graceful completion.
                $sendEvent('complete', [
                    'assistant' => "⚠️ Abbruch: Maximale Iterationen erreicht ({$maxIterations}). "
                        . "Ich habe bereits einige Schritte ausgeführt, aber brauche einen weiteren Send, um fortzufahren "
                        . "(oder wir erhöhen/optimieren die Limits/Batches).",
                    'reasoning' => null,
                    'thinking' => null,
                    'chat_history' => $messages,
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

