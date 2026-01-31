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
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;

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
                // max_output_tokens:
                // - kann pro Request überschrieben werden (max_output_tokens oder max_tokens legacy)
                // - Default kommt aus core_ai_models.max_output_tokens (Model Settings)
                // - Fallback: 2000 (bisheriges Verhalten)
                $requestedMax = $request->input('max_output_tokens', $request->input('max_tokens', null));
                $requestedMax = is_numeric($requestedMax) ? (int) $requestedMax : null;

                $modelRow = \Platform\Core\Models\CoreAiModel::query()
                    ->where('model_id', 'gpt-5.2')
                    ->where('is_active', true)
                    ->where('is_deprecated', false)
                    ->first();
                $modelCap = $modelRow?->max_output_tokens ? (int) $modelRow->max_output_tokens : null;

                $defaultMax = $modelCap ?: 2000;
                $maxOutputTokens = $requestedMax ?: $defaultMax;
                if ($modelCap) {
                    $maxOutputTokens = min($maxOutputTokens, $modelCap);
                }
                // Guardrails against accidental extremes / invalid values
                if ($maxOutputTokens < 64) { $maxOutputTokens = 64; }
                if ($maxOutputTokens > 200000) { $maxOutputTokens = 200000; }

                // LLM aufrufen
                // Simple Playground: fixed model for now (explicit user request)
                $response = $openAiService->chat($messages, 'gpt-5.2', [
                    'max_tokens' => $maxOutputTokens,
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
        // Source of truth: core_ai_models (manually editable in the Playground "Model settings" tab).
        // We default to provider=openai for now; later this can be extended to other providers.
        $providerKey = (string) ($request->query('provider') ?? 'openai');

        // Prefer models for the requested provider. If the provider doesn't exist yet, fall back to all active models.
        $models = CoreAiModel::query()
            ->with(['provider', 'provider.defaultModel'])
            ->where('is_active', true)
            ->where('is_deprecated', false)
            ->when($providerKey !== '', function ($q) use ($providerKey) {
                $q->whereHas('provider', function ($qq) use ($providerKey) {
                    $qq->where('key', $providerKey)->where('is_active', true);
                });
            })
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get();

        if ($models->count() === 0) {
            $models = CoreAiModel::query()
                ->with(['provider', 'provider.defaultModel'])
                ->where('is_active', true)
                ->where('is_deprecated', false)
                ->orderBy('provider_id')
                ->orderBy('model_id')
                ->get();
        }

        $ids = $models->pluck('model_id')->values()->all();
        $provider = $models->first()?->provider;
        $defaultModel = $provider?->defaultModel?->model_id ?? ($ids[0] ?? null);

        return response()->json([
            'success' => true,
            'models' => $ids,
            'count' => count($ids),
            'default_model' => $defaultModel,
            // Detailed info for future UI (prices, limits, etc.).
            'models_detailed' => $models->map(function (CoreAiModel $m) {
                return [
                    'provider' => $m->provider?->key,
                    'model_id' => $m->model_id,
                    'name' => $m->name,
                    'category' => $m->category,
                    'context_window' => $m->context_window,
                    'max_output_tokens' => $m->max_output_tokens,
                    'knowledge_cutoff_date' => $m->knowledge_cutoff_date?->format('Y-m-d'),
                    'pricing_currency' => $m->pricing_currency,
                    'price_input_per_1m' => $m->price_input_per_1m,
                    'price_cached_input_per_1m' => $m->price_cached_input_per_1m,
                    'price_output_per_1m' => $m->price_output_per_1m,
                    'is_active' => (bool) $m->is_active,
                    'is_deprecated' => (bool) $m->is_deprecated,
                ];
            })->values()->all(),
            'source' => 'core_ai_models',
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
            // SSE hardening: prevent timeouts and buffering issues that surface as browser "Load failed".
            // For SSE: if the browser aborts (Stop button), we want to stop server-side work too.
            try { @ignore_user_abort(false); } catch (\Throwable $e) {}
            try { @set_time_limit(0); } catch (\Throwable $e) {}
            try { @ini_set('max_execution_time', '0'); } catch (\Throwable $e) {}
            try { @ini_set('zlib.output_compression', '0'); } catch (\Throwable $e) {}
            try { @ini_set('output_buffering', '0'); } catch (\Throwable $e) {}

            // If a fatal error happens mid-stream, the browser will just see a network error.
            // So we log shutdown info to make root cause visible in server logs.
            try {
                $rid = bin2hex(random_bytes(6));
            } catch (\Throwable $e) {
                $rid = (string) (microtime(true));
            }
            Log::info('[SimpleToolController] SSE stream start', [
                'rid' => $rid,
                'path' => $request->path(),
                'user_id' => optional($request->user())->id,
            ]);
            register_shutdown_function(function() use ($rid) {
                $err = error_get_last();
                if ($err) {
                    Log::error('[SimpleToolController] SSE stream shutdown (fatal?)', [
                        'rid' => $rid,
                        'error' => $err,
                    ]);
                } else {
                    Log::info('[SimpleToolController] SSE stream shutdown', ['rid' => $rid]);
                }
            });

            // Output Buffering deaktivieren
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $sendEvent = function(string $event, array $data) {
                if (connection_aborted()) {
                    throw new \RuntimeException('__CLIENT_ABORTED__');
                }
                echo "event: {$event}\n";
                echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
                if (connection_aborted()) {
                    throw new \RuntimeException('__CLIENT_ABORTED__');
                }
            };

            // Send an initial SSE comment to open the stream early (helps with proxies/buffering).
            echo ": connected\n\n";
            @flush();

            try {
                // Debug: Eingehende Header/Body (hilft bei 400)
                $sendEvent('debug.request', [
                    'content_type' => $request->header('Content-Type'),
                    'accept' => $request->header('Accept'),
                ]);

                try {
                    $request->validate([
                        // message can be empty when continuing an interrupted tool-loop
                        'message' => 'nullable|string',
                        'chat_history' => 'nullable|array', // Conversation-Historie
                        'thread_id' => 'nullable|integer|exists:core_chat_threads,id',
                        'model' => 'nullable|string',
                        // Allow higher iteration counts, but keep a safety cap server-side.
                        'max_iterations' => 'nullable|integer|min:1|max:200',
                        'context' => 'nullable|array',
                        'context.source_route' => 'nullable|string',
                        'context.source_module' => 'nullable|string',
                        'context.source_url' => 'nullable|string',
                        'continuation' => 'nullable|array',
                        'continuation.previous_response_id' => 'nullable|string',
                        'continuation.next_input' => 'nullable|array',
                        // File attachments (array of context_file IDs)
                        'attachments' => 'nullable|array',
                        'attachments.*' => 'integer|exists:context_files,id',
                    ]);
                } catch (\Throwable $e) {
                    $sendEvent('error', [
                        'error' => 'Validation failed: ' . $e->getMessage(),
                    ]);
                    return;
                }

                $userMessage = (string) ($request->input('message') ?? '');
                $threadId = $request->input('thread_id');
                $chatHistory = $request->input('chat_history', []);
                $attachmentIds = $request->input('attachments', []);
                $attachmentIds = is_array($attachmentIds) ? array_filter($attachmentIds, 'is_numeric') : [];
                $createdUserMessageId = null;
                $assistantSavedToDb = false;

                // Load thread and messages from DB if thread_id provided
                $thread = null;
                if ($threadId) {
                    $thread = CoreChatThread::with('chat')->find($threadId);
                    if ($thread && $thread->chat && $thread->chat->user_id === $request->user()?->id) {
                        // Load messages from DB
                        $dbMessages = $thread->messages()->orderBy('created_at')->get();
                        $chatHistory = [];
                        foreach ($dbMessages as $msg) {
                            if ($msg->role !== 'system') { // Skip system messages from DB
                                $chatHistory[] = [
                                    'role' => $msg->role,
                                    'content' => $msg->content,
                                ];
                            }
                        }
                    } else {
                        $thread = null; // Invalid thread, ignore
                    }
                }

                // Model selection: accept client-selected model, but validate against core_ai_models (provider=openai).
                $model = (string) ($request->input('model') ?? '');
                $providerKey = 'openai';
                $provider = CoreAiProvider::where('key', $providerKey)->where('is_active', true)->with('defaultModel')->first();
                $fallback = $provider?->defaultModel?->model_id ?: 'gpt-5.2';

                if ($model === '') {
                    $model = $fallback;
                } else {
                    $ok = CoreAiModel::query()
                        ->whereHas('provider', fn($q) => $q->where('key', $providerKey)->where('is_active', true))
                        ->where('model_id', $model)
                        ->where('is_active', true)
                        ->where('is_deprecated', false)
                        ->exists();
                    if (!$ok) {
                        $model = $fallback;
                    }
                }

                $continuation = $request->input('continuation', null);

                // Terminal-Kontext (wird vom UI mitgeschickt, z.B. source_route/source_module/source_url)
                $clientContext = $request->input('context', null);
                $clientContext = is_array($clientContext) ? $clientContext : null;

                $routeName = $request->route()?->getName();
                $routeModule = (is_string($routeName) && str_contains($routeName, '.')) ? strstr($routeName, '.', true) : null;
                $sourceRoute = is_string($clientContext['source_route'] ?? null) ? $clientContext['source_route'] : $routeName;
                $sourceModule = is_string($clientContext['source_module'] ?? null) ? $clientContext['source_module'] : $routeModule;
                $sourceUrl = is_string($clientContext['source_url'] ?? null) ? $clientContext['source_url'] : $request->fullUrl();
                $sendEvent('debug.context', [
                    'source_route' => $sourceRoute,
                    'source_module' => $sourceModule,
                    'source_url' => $sourceUrl,
                ]);
                
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
                if (trim($userMessage) !== '') {
                    // Build user message content with attachment context
                    $userContent = $userMessage;

                    // If there are attachments, add context about them
                    if (!empty($attachmentIds)) {
                        $attachmentContext = [];
                        $contextFiles = \Platform\Core\Models\ContextFile::whereIn('id', $attachmentIds)->get();
                        foreach ($contextFiles as $file) {
                            $attachmentContext[] = sprintf(
                                '- %s (%s, %s)',
                                $file->original_name,
                                $file->mime_type,
                                $file->file_size > 1024*1024
                                    ? round($file->file_size / (1024*1024), 2) . ' MB'
                                    : round($file->file_size / 1024, 2) . ' KB'
                            );
                        }
                        if (!empty($attachmentContext)) {
                            $userContent .= "\n\n[Angehängte Dateien:\n" . implode("\n", $attachmentContext) . "]";
                        }
                    }

                    $messages[] = [
                        'role' => 'user',
                        'content' => $userContent,
                    ];
                    
                    // Save user message to DB if thread exists
                    // Note: User messages don't have token costs (they're input), but we store the structure for consistency
                    if ($thread) {
                        $messageData = [
                            'core_chat_id' => $thread->core_chat_id,
                            'thread_id' => $thread->id,
                            'role' => 'user',
                            'content' => $userMessage,
                            'tokens_in' => 0, // User messages are part of input, counted in assistant response
                            'tokens_out' => 0,
                            'tokens_cached' => 0,
                            'tokens_reasoning' => 0,
                            'cost' => 0.0,
                            'pricing_currency' => 'USD',
                            'model_id' => $model ?? null,
                        ];

                        // Add attachments to meta if present
                        if (!empty($attachmentIds)) {
                            $messageData['meta'] = ['attachments' => array_map('intval', $attachmentIds)];
                        }

                        $created = CoreChatMessage::create($messageData);
                        $createdUserMessageId = $created?->id;
                    }
                }

                $sendEvent('start', ['message' => '🚀 Starte...']);

                // Best-practice tool loop (stream per iteration; execute tools server-side; continue until no tool calls)
                // Keep a hard safety cap, but allow enough room for "batch" operations
                // like renaming/updating multiple tasks.
                $maxIterations = (int) ($request->input('max_iterations') ?? 200);
                if ($maxIterations < 1) { $maxIterations = 1; }
                if ($maxIterations > 200) { $maxIterations = 200; }

                // Safety cap for tool executions across all iterations (prevents infinite loops).
                $maxToolExecutions = max(60, min(2000, $maxIterations * 50));
                $toolExecutionCount = 0;
                $debugEventCount = 0;
                // Continuation support (for "max iterations reached" recovery):
                // If the previous request ended mid tool-loop, we can continue from the last response id
                // and the prepared next input (tool outputs).
                $previousResponseId = null;
                $messagesForApi = $messages; // iteration 1 default: full conversation input
                $continuationPrev = is_array($continuation) ? ($continuation['previous_response_id'] ?? null) : null;
                $continuationNext = is_array($continuation) ? ($continuation['next_input'] ?? null) : null;
                if (is_string($continuationPrev) && $continuationPrev !== '') {
                    $previousResponseId = $continuationPrev;
                    if (is_array($continuationNext) && !empty($continuationNext)) {
                        $messagesForApi = $continuationNext;
                    }
                }
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
                    $incompleteReason = null;

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

                        // Build a mapping so debug output does not look like "two different languages":
                        // internal tool names use dots (okr.cycles.GET), OpenAI function names use underscores (okr_cycles_GET).
                        $toolNameMap = [];
                        try {
                            $nameNormalize = $reflection->getMethod('normalizeToolNameForOpenAi');
                            $nameNormalize->setAccessible(true);
                            foreach ($availableTools as $t) {
                                $fn = $t['function'] ?? null;
                                $internalName = is_array($fn) ? ($fn['name'] ?? null) : null;
                                if (is_string($internalName) && $internalName !== '') {
                                    $toolNameMap[$internalName] = $nameNormalize->invoke($openAiService, $internalName);
                                }
                            }
                            ksort($toolNameMap);
                        } catch (\Throwable $e) {
                            // ignore
                        }

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
                        // Auto-Continuation (max_output_tokens):
                        // Wenn ein Response wegen max_output_tokens abgeschnitten wird und keine Tool-Calls anstehen,
                        // dann streamen wir automatisch weiter via previous_response_id, ohne UI-Reset.
                        $maxOutputContinuations = 12;
                        $continuationPass = 0;
                        $passPreviousResponseId = $previousResponseId;
                        $passMessagesForApi = $messagesForApi;

                        while (true) {
                            $continuationPass++;
                            if ($continuationPass > $maxOutputContinuations) {
                                break;
                            }

                            $incompleteReason = null;
                            $currentResponseId = null;

                            // Tool-call args can be interleaved; we need a stable map item_id -> call_id across events.
                            $toolCallIdByItemId = [];

                            $openAiService->streamChat(
                                $passMessagesForApi,
                                function(string $delta) use ($sendEvent, &$assistant) {
                                    $assistant .= $delta;
                                    $sendEvent('assistant.delta', ['delta' => $delta, 'content' => $assistant]);
                                },
                                $model,
                                [
                                    'include_web_search' => true,
                                    // Allow OpenAiService to abort even when no deltas/events arrive.
                                    'should_abort' => fn () => connection_aborted(),
                                    // max_output_tokens kommt aus core_ai_models.max_output_tokens (Model Settings),
                                    // kann aber pro Request via max_output_tokens überschrieben werden.
                                    'max_tokens' => (function () use ($request, $model) {
                                        $requestedMax = $request->input('max_output_tokens', $request->input('max_tokens', null));
                                        $requestedMax = is_numeric($requestedMax) ? (int) $requestedMax : null;

                                        $modelRow = \Platform\Core\Models\CoreAiModel::query()
                                            ->where('model_id', $model)
                                            ->where('is_active', true)
                                            ->where('is_deprecated', false)
                                            ->first();
                                        $modelCap = $modelRow?->max_output_tokens ? (int) $modelRow->max_output_tokens : null;

                                        $defaultMax = $modelCap ?: 2000;
                                        $maxOutputTokens = $requestedMax ?: $defaultMax;
                                        if ($modelCap) {
                                            $maxOutputTokens = min($maxOutputTokens, $modelCap);
                                        }
                                        if ($maxOutputTokens < 64) { $maxOutputTokens = 64; }
                                        if ($maxOutputTokens > 200000) { $maxOutputTokens = 200000; }
                                        return $maxOutputTokens;
                                    })(),
                                    'with_context' => false,
                                        // Parität zum Terminal: Kontext wird mitgeführt (ohne Auto-Injection).
                                        'source_route' => $sourceRoute,
                                        'source_module' => $sourceModule,
                                    'previous_response_id' => $passPreviousResponseId,
                                    'reasoning' => [
                                        'effort' => 'medium',
                                    ],
                                    'on_debug' => function(?string $event, array $decoded) use (
                                        $sendEvent,
                                        $thread,
                                        $model,
                                        &$debugEventCount,
                                        &$usageAggregate,
                                        &$seenUsageResponseIds,
                                        &$iteration,
                                        &$toolCallsCollector,
                                        &$toolCallIdByItemId,
                                        &$currentStreamingToolCall,
                                        &$currentStreamingToolId,
                                        &$currentStreamingCallId,
                                        &$currentResponseId,
                                        &$incompleteReason
                                    ) {
                                        $event = $event ?? '';
                                        if ($debugEventCount < 2000) {
                                            $debugEventCount++;
                                            $preview = [
                                                'keys' => array_keys($decoded),
                                                'type' => $decoded['type'] ?? ($decoded['item']['type'] ?? null),
                                                'id' => $decoded['id'] ?? ($decoded['item']['id'] ?? ($decoded['item_id'] ?? null)),
                                                'item_id' => $decoded['item_id'] ?? null,
                                                'call_id' => $decoded['call_id'] ?? ($decoded['item']['call_id'] ?? null),
                                                'name' => $decoded['name'] ?? ($decoded['item']['name'] ?? null),
                                                'status' => $decoded['status'] ?? null,
                                                'sequence_number' => $decoded['sequence_number'] ?? null,
                                                'output_index' => $decoded['output_index'] ?? null,
                                                'response_id' => $decoded['response']['id'] ?? null,
                                                'model' => $decoded['response']['model'] ?? ($decoded['model'] ?? null),
                                                'query' => $decoded['query'] ?? ($decoded['input']['query'] ?? ($decoded['item']['query'] ?? null)),
                                            ];
                                            $raw = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                                            if (is_string($raw) && strlen($raw) > 4000) {
                                                $raw = substr($raw, 0, 4000) . '…';
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

                                        // Detect truncation (max_output_tokens) so we can auto-continue.
                                        if ($event === 'response.incomplete') {
                                            $reason = $decoded['response']['incomplete_details']['reason'] ?? null;
                                            if (is_string($reason) && $reason !== '') {
                                                $incompleteReason = $reason;
                                            }
                                        }

                                    // Aggregate token usage across the whole request.
                                    // Usage usually appears on response.completed, but we handle any event carrying it.
                                    // NOTE: Depending on provider/event shape, usage might appear either under response.usage or top-level usage.
                                    $usage = $decoded['response']['usage'] ?? ($decoded['usage'] ?? null);
                                    if (is_array($usage) && !empty($usage)) {
                                        $rid = $decoded['response']['id'] ?? $currentResponseId;
                                        if (!is_string($rid) || $rid === '') {
                                            $rid = 'unknown';
                                        }
                                        // Some streams reuse the same response id; use sequence_number to dedupe instead.
                                        $seq = $decoded['sequence_number'] ?? null;
                                        $seenKey = $rid . '|' . (is_scalar($seq) ? (string)$seq : '');
                                        if (!isset($seenUsageResponseIds[$seenKey])) {
                                            $seenUsageResponseIds[$seenKey] = true;

                                            $in = (int) ($usage['input_tokens'] ?? 0);
                                            $out = (int) ($usage['output_tokens'] ?? 0);
                                            $tot = (int) ($usage['total_tokens'] ?? 0);
                                            $cached = (int) (($usage['input_tokens_details']['cached_tokens'] ?? null) ?? 0);
                                            $reasonTok = (int) (($usage['output_tokens_details']['reasoning_tokens'] ?? null) ?? 0);

                                            $usageAggregate['input_tokens'] += $in;
                                            $usageAggregate['output_tokens'] += $out;
                                            $usageAggregate['total_tokens'] += $tot;
                                            $usageAggregate['input_tokens_details']['cached_tokens'] += $cached;
                                            $usageAggregate['output_tokens_details']['reasoning_tokens'] += $reasonTok;

                                            // Include thread totals if thread exists
                                            $threadTotals = null;
                                            if ($thread) {
                                                $thread->refresh(); // Reload from DB to get updated totals
                                                $threadTotals = [
                                                    'total_tokens_in' => $thread->total_tokens_in,
                                                    'total_tokens_out' => $thread->total_tokens_out,
                                                    'total_tokens_cached' => $thread->total_tokens_cached,
                                                    'total_tokens_reasoning' => $thread->total_tokens_reasoning,
                                                    'total_cost' => $thread->total_cost,
                                                    'pricing_currency' => $thread->pricing_currency,
                                                ];
                                            }
                                            
                                            // Calculate request-level cost for this increment
                                            $requestCost = 0.0;
                                            $requestCurrency = 'USD';
                                            $modelForPricing = $decoded['response']['model'] ?? ($decoded['model'] ?? ($model ?? null));
                                            if ($modelForPricing) {
                                                $modelRecord = CoreAiModel::where('model_id', $modelForPricing)
                                                    ->whereHas('provider', fn($q) => $q->where('key', 'openai'))
                                                    ->first();
                                                if ($modelRecord) {
                                                    $priceInput = (float)($modelRecord->price_input_per_1m ?? 0);
                                                    $priceCached = (float)($modelRecord->price_cached_input_per_1m ?? 0);
                                                    $priceOutput = (float)($modelRecord->price_output_per_1m ?? 0);
                                                    $requestCurrency = (string)($modelRecord->pricing_currency ?? 'USD');
                                                    
                                                    $inputTokensForIncrement = $in - $cached; // Non-cached input tokens
                                                    $requestCost = ($inputTokensForIncrement / 1_000_000 * $priceInput)
                                                        + ($cached / 1_000_000 * $priceCached)
                                                        + ($out / 1_000_000 * $priceOutput);
                                                }
                                            }
                                            
                                            $sendEvent('usage', [
                                                'model' => $modelForPricing,
                                                'iteration' => $iteration,
                                                'cumulative' => true,
                                                'usage' => $usageAggregate,
                                                'last_increment' => [
                                                    'input_tokens' => $in,
                                                    'output_tokens' => $out,
                                                    'total_tokens' => $tot,
                                                    'cached_tokens' => $cached,
                                                    'reasoning_tokens' => $reasonTok,
                                                    'cost' => $requestCost,
                                                    'currency' => $requestCurrency,
                                                ],
                                                'thread_totals' => $threadTotals,
                                            ]);
                                        }
                                    }

                                    // Collect function calls robustly (tool args can be interleaved across multiple calls).
                                    // We key tool calls by call_id, but argument deltas reference item_id -> map item_id => call_id.
                                    if ($event === 'response.output_item.added' && isset($decoded['item']['type']) && $decoded['item']['type'] === 'function_call') {
                                        $openAiToolName = $decoded['item']['name'] ?? null;
                                        $itemId = $decoded['item']['id'] ?? null;
                                        $callId = $decoded['item']['call_id'] ?? null;

                                        if (is_string($itemId) && $itemId !== '' && is_string($callId) && $callId !== '') {
                                            $toolCallIdByItemId[$itemId] = $callId;
                                        }

                                        if (is_string($openAiToolName) && $openAiToolName !== '' && is_string($callId) && $callId !== '') {
                                            $toolCallsCollector[$callId] = [
                                                'id' => is_string($itemId) ? $itemId : null,
                                                'call_id' => $callId,
                                                'name' => $openAiToolName,
                                                'arguments' => '',
                                            ];
                                        }
                                    } elseif ($event === 'response.function_call_arguments.delta') {
                                        $delta = $decoded['delta'] ?? '';
                                        $itemId = $decoded['item_id'] ?? null;
                                        $callId = (is_string($itemId) && isset($toolCallIdByItemId[$itemId])) ? $toolCallIdByItemId[$itemId] : null;

                                        if (is_string($callId) && $callId !== '' && is_string($delta) && isset($toolCallsCollector[$callId])) {
                                            $toolCallsCollector[$callId]['arguments'] .= $delta;
                                        }
                                    } elseif ($event === 'response.function_call_arguments.done') {
                                        $args = $decoded['arguments'] ?? '';
                                        $itemId = $decoded['item_id'] ?? null;
                                        $callId = (is_string($itemId) && isset($toolCallIdByItemId[$itemId])) ? $toolCallIdByItemId[$itemId] : null;

                                        if (is_string($callId) && $callId !== '' && is_string($args) && isset($toolCallsCollector[$callId])) {
                                            $toolCallsCollector[$callId]['arguments'] = $args;
                                        }
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

                            // Auto-continue only if we were truncated by max_output_tokens and there are no tool calls.
                            if (empty($toolCallsCollector) && $incompleteReason === 'max_output_tokens' && is_string($currentResponseId) && $currentResponseId !== '') {
                                $sendEvent('openai.event', [
                                    'event' => 'server.output.continue',
                                    'preview' => [
                                        'iteration' => $iteration,
                                        'pass' => $continuationPass,
                                        'reason' => $incompleteReason,
                                    ],
                                    'raw' => null,
                                ]);
                                // Continue from the last response without re-sending the whole input.
                                $passPreviousResponseId = $currentResponseId;
                                $passMessagesForApi = [];
                                continue;
                            }

                            break;
                        }
                    } catch (\Throwable $e) {
                        $sendEvent('error', [
                            'error' => 'OpenAI stream failed: ' . $e->getMessage(),
                        ]);
                        return;
                    }

                    // No tool calls → final answer
                    if (empty($toolCallsCollector)) {
                        $messages[] = ['role' => 'assistant', 'content' => $assistant];
                        
                        // Save assistant message to DB if thread exists
                        if ($thread && trim($assistant) !== '') {
                            $usageAggregate = $usageAggregate ?? [];
                            $tokensIn = (int)($usageAggregate['input_tokens'] ?? 0);
                            $tokensOut = (int)($usageAggregate['output_tokens'] ?? 0);
                            $tokensCached = (int)($usageAggregate['input_tokens_details']['cached_tokens'] ?? 0);
                            $tokensReasoning = (int)($usageAggregate['output_tokens_details']['reasoning_tokens'] ?? 0);
                            
                            // Calculate cost based on model pricing
                            $cost = 0.0;
                            $currency = 'USD';
                            $modelRecord = CoreAiModel::where('model_id', $model)->whereHas('provider', fn($q) => $q->where('key', 'openai'))->first();
                            if ($modelRecord) {
                                $priceInput = (float)($modelRecord->price_input_per_1m ?? 0);
                                $priceCached = (float)($modelRecord->price_cached_input_per_1m ?? 0);
                                $priceOutput = (float)($modelRecord->price_output_per_1m ?? 0);
                                $currency = (string)($modelRecord->pricing_currency ?? 'USD');
                                
                                $inputTokens = $tokensIn - $tokensCached; // Non-cached input tokens
                                $cost = ($inputTokens / 1_000_000 * $priceInput)
                                    + ($tokensCached / 1_000_000 * $priceCached)
                                    + ($tokensOut / 1_000_000 * $priceOutput);
                            }
                            
                            // Save assistant message with all token details
                            CoreChatMessage::create([
                                'core_chat_id' => $thread->core_chat_id,
                                'thread_id' => $thread->id,
                                'role' => 'assistant',
                                'content' => $assistant,
                                'meta' => [
                                    'reasoning' => $reasoning,
                                    'thinking' => $thinking,
                                ],
                                'tokens_in' => $tokensIn, // Total input tokens for this request
                                'tokens_out' => $tokensOut,
                                'tokens_cached' => $tokensCached,
                                'tokens_reasoning' => $tokensReasoning,
                                'cost' => $cost,
                                'pricing_currency' => $currency,
                                'model_id' => $model,
                            ]);
                            $assistantSavedToDb = true;
                            
                            // Update thread token counts, cost, and model
                            $thread->increment('total_tokens_in', $tokensIn);
                            $thread->increment('total_tokens_out', $tokensOut);
                            $thread->increment('total_tokens_cached', $tokensCached);
                            $thread->increment('total_tokens_reasoning', $tokensReasoning);
                            $thread->increment('total_cost', $cost);
                            $updates = [];
                            if ($thread->pricing_currency !== $currency) {
                                $updates['pricing_currency'] = $currency;
                            }
                            // Save model in thread if not already set or if changed
                            if ($thread->model_id !== $model) {
                                $updates['model_id'] = $model;
                            }
                            if (!empty($updates)) {
                                $thread->update($updates);
                            }
                            
                            // Also update chat totals (aggregate)
                            $thread->chat->increment('total_tokens_out', $tokensOut);
                            if (isset($usageAggregate['input_tokens'])) {
                                $thread->chat->increment('total_tokens_in', $tokensIn);
                            }

                            // Ensure UI always receives request usage (Req tokens + Req $) even when the
                            // upstream stream doesn't emit response.usage events.
                            $thread->refresh();
                            $sendEvent('usage', [
                                'model' => $model,
                                'iteration' => $iteration,
                                'cumulative' => true,
                                'usage' => [
                                    'input_tokens' => $tokensIn,
                                    'output_tokens' => $tokensOut,
                                    'total_tokens' => $tokensIn + $tokensOut,
                                    'input_tokens_details' => [
                                        'cached_tokens' => $tokensCached,
                                    ],
                                    'output_tokens_details' => [
                                        'reasoning_tokens' => $tokensReasoning,
                                    ],
                                ],
                                'last_increment' => [
                                    'input_tokens' => $tokensIn,
                                    'output_tokens' => $tokensOut,
                                    'total_tokens' => $tokensIn + $tokensOut,
                                    'cached_tokens' => $tokensCached,
                                    'reasoning_tokens' => $tokensReasoning,
                                    'cost' => $cost,
                                    'currency' => $currency,
                                ],
                                'thread_totals' => [
                                    'total_tokens_in' => $thread->total_tokens_in,
                                    'total_tokens_out' => $thread->total_tokens_out,
                                    'total_tokens_cached' => $thread->total_tokens_cached,
                                    'total_tokens_reasoning' => $thread->total_tokens_reasoning,
                                    'total_cost' => $thread->total_cost,
                                    'pricing_currency' => $thread->pricing_currency,
                                ],
                            ]);
                        }
                        
                        $sendEvent('complete', [
                            'assistant' => $assistant,
                            'reasoning' => $reasoning,
                            'thinking' => $thinking,
                            'chat_history' => $messages,
                            'continuation' => null,
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

                        // Robustness: Some models occasionally emit "[]" for bulk tools.
                        // If we can detect a list payload, wrap it into the expected object shape.
                        $isList = (array_keys($args) === range(0, count($args) - 1));
                        if ($canonical === 'planner.tasks.bulk.POST' && !array_key_exists('tasks', $args) && $isList && !empty($args)) {
                            $args = ['tasks' => $args];
                        }

                        // Guard: If bulk create is called without tasks, return a precise hint (prevents repeated empty calls).
                        if ($canonical === 'planner.tasks.bulk.POST' && (!isset($args['tasks']) || !is_array($args['tasks']) || empty($args['tasks']))) {
                            $toolResult = ToolResult::error(
                                'VALIDATION_ERROR',
                                "Feld 'tasks' ist erforderlich. Beispiel: {\"defaults\":{\"project_id\":111,\"project_slot_id\":317},\"tasks\":[{\"title\":\"…\",\"description\":\"Rezept…\",\"dod\":\"DoD…\"}]}",
                            );
                            $toolArray = $toolResult->toArray();
                            $ms = 0;
                            $retriesUsed = 0;
                            $cached = false;

                            $argsHash = md5(json_encode($args, JSON_UNESCAPED_UNICODE) ?: '');
                            $argsPreview = json_encode($args, JSON_UNESCAPED_UNICODE);
                            if (is_string($argsPreview) && strlen($argsPreview) > 180) {
                                $argsPreview = substr($argsPreview, 0, 180) . '…';
                            }

                            $sendEvent('tool.executed', [
                                'tool' => $canonical,
                                'call_id' => $callId,
                                'success' => false,
                                'ms' => $ms,
                                'error_code' => $toolResult->errorCode ?? 'VALIDATION_ERROR',
                                'error' => $toolResult->error,
                                'cached' => $cached,
                                'retries' => $retriesUsed,
                                'args_hash' => $argsHash,
                                'args_preview' => $argsPreview,
                                // Provide full args as the model called them (for debug UI)
                                'args_json' => $argsJson,
                                'args' => $args,
                            ]);

                            $toolOutputsForNextIteration[] = [
                                'type' => 'function_call_output',
                                'call_id' => $callId,
                                'output' => json_encode($toolArray, JSON_UNESCAPED_UNICODE),
                            ];
                            continue;
                        }

                        $sendEvent('openai.event', [
                            'event' => 'server.tool.execute',
                            'preview' => ['tool' => $canonical],
                            'raw' => null,
                        ]);

                        try {
                            // Dedup/cache: if the model calls the same tool with the same args repeatedly
                            // (common in early iterations), reuse the previous result to avoid cost.
                            $isGet = (bool) preg_match('/\.GET$/', $canonical);
                            $isWrite = (bool) preg_match('/\.(POST|PUT|PATCH|DELETE|EDIT)$/', $canonical);
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
                                // Provide full args as the model called them (for debug UI)
                                'args_json' => $argsJson,
                                'args' => $args,
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
                                    $requestedTools = [];

                                    // If module is explicitly provided, treat it as an explicit request to load the module's tools.
                                    // This avoids "search raten" issues where the model only loads a subset (e.g. objectives) and
                                    // then misses critical entry tools (e.g. okr.cycles.GET).
                                    if (is_string($module) && trim($module) !== '') {
                                        try {
                                            $moduleKey = trim($module);
                                            $reg = app(\Platform\Core\Tools\ToolRegistry::class);
                                            $tools = array_values($reg->all());
                                            $permissionService = app(\Platform\Core\Services\ToolPermissionService::class);
                                            $tools = $permissionService->filterToolsByPermission($tools);

                                            foreach ($tools as $t) {
                                                $n = $t->getName();
                                                if (is_string($n) && str_starts_with($n, $moduleKey . '.')) {
                                                    $requestedTools[] = $n;
                                                }
                                            }
                                        } catch (\Throwable $e) {
                                            // Fallback to response-based loading below.
                                        }
                                    }

                                    // Fallback: load exactly the tools that were returned by tools.GET (current page),
                                    // useful if module isn't present (legacy) or registry isn't available.
                                    if (empty($requestedTools)) {
                                        $toolsData = $toolArray['data']['tools'] ?? $toolArray['tools'] ?? [];
                                        if (is_array($toolsData)) {
                                            foreach ($toolsData as $t) {
                                                $n = $t['name'] ?? null;
                                                if (is_string($n) && $n !== '') { $requestedTools[] = $n; }
                                            }
                                        }
                                    }

                                    $requestedTools = array_values(array_unique($requestedTools));
                                    if (!empty($requestedTools)) {
                                        $openAiService->loadToolsDynamically($requestedTools);
                                        $sendEvent('openai.event', [
                                            'event' => 'server.tools.loaded',
                                            'preview' => [
                                                'count' => count($requestedTools),
                                                'mode' => (!empty($module) ? 'module' : 'page'),
                                                'module' => is_string($module) ? $module : null,
                                            ],
                                            'raw' => json_encode([
                                                'mode' => (!empty($module) ? 'module' : 'page'),
                                                'module' => is_string($module) ? $module : null,
                                                'tools' => $requestedTools,
                                            ], JSON_UNESCAPED_UNICODE),
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
                                // Provide full args as the model called them (for debug UI)
                                'args_json' => $argsJson,
                                'args' => $args,
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
                    // Allow the client to continue seamlessly (no restart) using previous_response_id + next_input.
                    'continuation' => [
                        'pending' => true,
                        'previous_response_id' => $previousResponseId,
                        'next_input' => $messagesForApi,
                    ],
                ]);
                return;

            } catch (\Throwable $e) {
                if ($e->getMessage() === '__CLIENT_ABORTED__') {
                    Log::info('[SimpleToolController] SSE stream aborted by client', [
                        'rid' => $rid ?? null,
                        'path' => $request->path(),
                        'user_id' => optional($request->user())->id,
                    ]);
                    // If the user aborted mid-request, we don't want to keep a dangling user message in the DB.
                    // Only delete if we created it in THIS request and we did not persist an assistant answer.
                    if ($thread && $createdUserMessageId && !$assistantSavedToDb) {
                        try {
                            CoreChatMessage::where('id', $createdUserMessageId)
                                ->where('thread_id', $thread->id)
                                ->where('role', 'user')
                                ->delete();
                        } catch (\Throwable $e2) {
                            Log::warning('[SimpleToolController] Failed to delete aborted user message', [
                                'thread_id' => $thread->id,
                                'message_id' => $createdUserMessageId,
                                'error' => $e2->getMessage(),
                            ]);
                        }
                    }
                    return;
                }
                try {
                    $sendEvent('error', ['error' => $e->getMessage()]);
                } catch (\Throwable $e2) {
                    // client likely aborted; nothing to do
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            // Prevent intermediary buffering/compression.
            'Content-Encoding' => 'none',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

