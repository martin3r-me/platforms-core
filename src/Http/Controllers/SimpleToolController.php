<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Services\OpenAiService;
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

                // Initialisiere Messages mit Historie (nur role+content)
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

                $sendEvent('start', ['message' => '🚀 Starte...']);

                // Chat-only Streaming (keine Tools, keine Iterationen)
                $assistant = '';
                $reasoning = '';
                $thinking = '';
                $debugEventCount = 0;
                $usageSent = false;

                try {
                $openAiService->streamChat(
                    $messages,
                    function(string $delta) use ($sendEvent, &$assistant) {
                        $assistant .= $delta;
                        $sendEvent('assistant.delta', ['delta' => $delta, 'content' => $assistant]);
                    },
                        $model,
                    [
                        // OpenAI built-in tool (no internal tool execution)
                        'tools' => [
                            ['type' => 'web_search'],
                        ],
                        'max_tokens' => 2000,
                        'with_context' => false,
                        // Tools-ready: actually let the model "think"
                        // This should trigger reasoning_summary_text / reasoning_text stream events (model-dependent).
                        'reasoning' => [
                            'effort' => 'medium',
                        ],
                        // Optional: forward selected OpenAI stream events to the client for debugging/observability
                        'on_debug' => function(?string $event, array $decoded) use ($sendEvent, &$debugEventCount, &$usageSent) {
                            $event = $event ?? '';
                            // Debug mode: forward EVERYTHING (cap only for safety)
                            if ($debugEventCount >= 2000) return;
                            $debugEventCount++;

                            // Emit token usage as a dedicated event once available (usually on response.completed)
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

                // Chat-History für Client (nur user+assistant)
                $messages[] = ['role' => 'assistant', 'content' => $assistant];
                $sendEvent('complete', [
                    'assistant' => $assistant,
                    'reasoning' => $reasoning,
                    'thinking' => $thinking,
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

