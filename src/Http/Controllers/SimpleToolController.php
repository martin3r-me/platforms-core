<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
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
                $response = $openAiService->chat($messages, 'gpt-4o-mini', [
                    'max_tokens' => 2000,
                    'temperature' => 0.7,
                    'tools' => null, // null = alle verfügbaren Tools verwenden
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

                // Tool-Calls ausführen
                $toolActionsText = "\n\n**🔧 Ausgeführte Aktionen:**\n";
                foreach ($toolCalls as $toolCall) {
                    $toolName = $toolCall['function']['name'] ?? null;
                    $toolArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                    $toolCallId = $toolCall['id'] ?? null;

                    if (!$toolName) {
                        continue;
                    }

                    // Normalisiere Tool-Name (OpenAI Format → Internal Format)
                    $internalToolName = $this->denormalizeToolName($toolName);

                    // Tool ausführen
                    try {
                        $result = $executor->execute($internalToolName, $toolArguments, $context);
                        $toolActionsText .= "- {$internalToolName}\n";

                        // Format Tool Result für LLM
                        $resultText = $this->formatToolResult($internalToolName, $result, $toolCallId);
                        
                        // Füge Tool-Result zu Messages hinzu
                        $messages[] = [
                            'role' => 'user',
                            'content' => $resultText,
                        ];

                        // Tracke Result
                        $toolResults[] = [
                            'iteration' => $iteration,
                            'tool' => $internalToolName,
                            'arguments' => $toolArguments,
                            'success' => $result->success,
                            'data' => $result->data,
                            'error' => $result->error,
                        ];

                    } catch (\Throwable $e) {
                        Log::error('[SimpleToolController] Tool execution failed', [
                            'tool' => $internalToolName,
                            'error' => $e->getMessage(),
                        ]);

                        // Fehler-Result zu Messages hinzufügen
                        $messages[] = [
                            'role' => 'user',
                            'content' => "Tool-Result: {$internalToolName}\nStatus: Fehler\n\nFehler: " . $e->getMessage(),
                        ];
                    }
                }

                // Füge Assistant-Message mit Tool-Actions hinzu
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $content . $toolActionsText,
                    'tool_calls' => $toolCalls,
                ];

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

                $sendEvent('start', ['message' => '🚀 Starte...']);

                $toolResults = [];
                $iteration = 0;
                $assistantContent = '';

                // Multi-Step Loop
                while ($iteration < self::MAX_ITERATIONS) {
                    $iteration++;
                    $sendEvent('iteration.start', ['iteration' => $iteration]);

                    try {
                        // LLM aufrufen mit ECHTEM Streaming
                        $assistantContent = '';
                        $toolCalls = [];
                        $currentToolCall = null;
                        $toolArguments = '';
                        $toolCallId = null;
                        
                        // Sammle Tool-Calls während des Streams über Debug-Callback
                        $onDebug = function($event, $data) use (&$toolCalls, &$currentToolCall, &$toolArguments, &$toolCallId, $sendEvent) {
                            // Tool-Call erkannt
                            if ($event === 'response.output_item.added' && isset($data['item']['type']) && $data['item']['type'] === 'function_call') {
                                $currentToolCall = $data['item']['name'] ?? null;
                                $toolCallId = $data['item']['id'] ?? ($data['item']['call_id'] ?? bin2hex(random_bytes(8)));
                                $toolArguments = '';
                                
                                if ($currentToolCall) {
                                    $internalName = $this->denormalizeToolName($currentToolCall);
                                    $sendEvent('tool.start', ['tool' => $internalName, 'call_id' => $toolCallId]);
                                }
                            }
                            
                            // Tool-Argumente werden gestreamt
                            if ($event === 'response.function_call_arguments.delta' && isset($data['delta'])) {
                                $toolArguments .= $data['delta'];
                            }
                            
                            // Tool-Argumente vollständig → Tool-Call sammeln
                            if ($event === 'response.function_call_arguments.done' && $currentToolCall) {
                                $arguments = $data['arguments'] ?? $toolArguments;
                                
                                $toolCall = [
                                    'id' => $toolCallId ?? bin2hex(random_bytes(8)),
                                    'type' => 'function',
                                    'function' => [
                                        'name' => $currentToolCall,
                                        'arguments' => is_string($arguments) ? $arguments : json_encode($arguments),
                                    ],
                                ];
                                
                                $toolCalls[] = $toolCall;
                                
                                $internalName = $this->denormalizeToolName($currentToolCall);
                                $sendEvent('tool.complete', [
                                    'tool' => $internalName,
                                    'call_id' => $toolCallId,
                                ]);
                                
                                // Reset für nächsten Tool-Call
                                $currentToolCall = null;
                                $toolArguments = '';
                                $toolCallId = null;
                            }
                        };
                        
                        $openAiService->streamChat($messages, function($delta) use ($sendEvent, &$assistantContent) {
                            $assistantContent .= $delta;
                            $sendEvent('llm.delta', ['delta' => $delta, 'content' => $assistantContent]);
                        }, 'gpt-4o-mini', [
                            'max_tokens' => 2000,
                            'temperature' => 0.7,
                            'tools' => null,
                            'on_tool_start' => function($toolName) use ($sendEvent) {
                                $internalName = $this->denormalizeToolName($toolName);
                                $sendEvent('tool.detected', ['tool' => $internalName]);
                            },
                            'on_debug' => $onDebug,
                            'tool_executor' => null, // Wir führen Tools selbst aus
                        ]);

                        // Wenn keine Tool-Calls: Fertig
                        if (empty($toolCalls)) {
                            $sendEvent('complete', [
                                'message' => $assistantContent,
                                'iterations' => $iteration,
                                'tool_results' => $toolResults,
                                'chat_history' => $messages, // Sende aktualisierte Historie zurück
                            ]);
                            return;
                        }

                        $sendEvent('tools.start', ['count' => count($toolCalls)]);

                        // Tool-Calls ausführen
                        foreach ($toolCalls as $toolCall) {
                            $toolName = $toolCall['function']['name'] ?? null;
                            $toolArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                            $toolCallId = $toolCall['id'] ?? null;

                            if (!$toolName) {
                                continue;
                            }

                            $internalToolName = $this->denormalizeToolName($toolName);
                            $sendEvent('tool.start', ['tool' => $internalToolName]);

                            try {
                                $result = $executor->execute($internalToolName, $toolArguments, $context);
                                
                                $resultText = $this->formatToolResult($internalToolName, $result, $toolCallId);
                                
                                $messages[] = [
                                    'role' => 'user',
                                    'content' => $resultText,
                                ];

                                $toolResults[] = [
                                    'iteration' => $iteration,
                                    'tool' => $internalToolName,
                                    'success' => $result->success,
                                ];

                                $sendEvent('tool.complete', [
                                    'tool' => $internalToolName,
                                    'success' => $result->success,
                                ]);

                            } catch (\Throwable $e) {
                                $sendEvent('tool.error', [
                                    'tool' => $internalToolName,
                                    'error' => $e->getMessage(),
                                ]);

                                $messages[] = [
                                    'role' => 'user',
                                    'content' => "Tool-Result: {$internalToolName}\nStatus: Fehler\n\nFehler: " . $e->getMessage(),
                                ];
                            }
                        }

                        // Füge Assistant-Message hinzu (mit Tool-Calls, falls vorhanden)
                        $assistantMessage = [
                            'role' => 'assistant',
                            'content' => $assistantContent,
                        ];
                        
                        if (!empty($toolCalls)) {
                            $assistantMessage['tool_calls'] = $toolCalls;
                        }
                        
                        $messages[] = $assistantMessage;

                        $sendEvent('iteration.complete', ['iteration' => $iteration]);

                    } catch (\Throwable $e) {
                        $sendEvent('error', [
                            'error' => $e->getMessage(),
                            'iteration' => $iteration,
                        ]);
                        return;
                    }
                }

                // Max Iterations erreicht
                $sendEvent('complete', [
                    'message' => $assistantContent,
                    'iterations' => $iteration,
                    'tool_results' => $toolResults,
                    'max_iterations' => true,
                    'chat_history' => $messages, // Sende aktualisierte Historie zurück
                ]);

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

