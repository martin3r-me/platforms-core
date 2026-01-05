<?php

namespace Platform\Core\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Tools\CoreContextTool;
use Platform\Core\Tools\ToolRegistry;

class OpenAiService
{
    // Default model; prefer config('tools.openai.model') at runtime.
    private const DEFAULT_MODEL = 'gpt-5';
    private string $baseUrl = 'https://api.openai.com/v1';

    // Loose coupling: ToolRegistry ist optional (Chat funktioniert auch ohne Tools)
    private ?ToolRegistry $toolRegistry = null;
    
    public function __construct(?ToolRegistry $toolRegistry = null)
    {
        // Dependency Injection mit optionalem Parameter für loose coupling
        // Falls nicht injiziert, wird es lazy über app() geladen (fallback)
        $this->toolRegistry = $toolRegistry;
    }
    
    /**
     * Lazy-Loading für ToolRegistry (fallback wenn nicht injiziert)
     */
    private function getToolRegistry(): ?ToolRegistry
    {
        if ($this->toolRegistry === null) {
            try {
                $container = app();
                if ($container->bound(ToolRegistry::class)) {
                    $this->toolRegistry = $container->make(ToolRegistry::class);
                }
            } catch (\Throwable $e) {
                // Registry nicht verfügbar - kein Problem, Chat funktioniert auch ohne Tools
                return null;
            }
        }
        return $this->toolRegistry;
    }

    private function getApiKey(): string
    {
        $key = config('services.openai.api_key');
        if (!is_string($key) || $key === '') { $key = config('services.openai.key') ?? ''; }
        if ($key === '') { $key = env('OPENAI_API_KEY') ?? ''; }
        if ($key === '') { throw new \RuntimeException('AUTHENTICATION_FAILED: OPENAI_API_KEY fehlt oder ist leer.'); }
        return $key;
    }

    public function chat(array $messages, string $model = self::DEFAULT_MODEL, array $options = []): array
    {
        // Allow runtime override via config
        $model = $options['model'] ?? config('tools.openai.model', $model);
        $messagesWithContext = $this->buildMessagesWithContext($messages, $options);
        $responsesInput = $this->buildResponsesInput($messagesWithContext);
        
        // DEBUG: Prüfe ob Tool-Results in Input sind
        $debugInfo = [
            'original_messages_count' => count($messages),
            'messages_with_context_count' => count($messagesWithContext),
            'responses_input_count' => count($responsesInput),
            'has_tool_results' => false,
            'tool_result_inputs' => [],
        ];
        
        foreach ($responsesInput as $idx => $input) {
            if (($input['role'] ?? '') === 'user' && str_contains($input['content'] ?? '', 'Tool-Result:')) {
                $debugInfo['has_tool_results'] = true;
                $debugInfo['tool_result_inputs'][] = [
                    'index' => $idx,
                    'role' => $input['role'] ?? 'unknown',
                    'content_preview' => substr($input['content'] ?? '', 0, 200),
                    'content_length' => strlen($input['content'] ?? ''),
                ];
            }
        }
        
        \Log::debug('[OpenAiService] Messages Debug', $debugInfo);
        
        try {
            $payload = [
                'model' => $model,
                'input' => $responsesInput,
                'stream' => false,
                'max_output_tokens' => $options['max_tokens'] ?? 1000,
            ];
            // Some models (e.g. gpt-5.2*) do not support temperature in the Responses API.
            if (!str_starts_with($model, 'gpt-5.2') && array_key_exists('temperature', $options)) {
                $payload['temperature'] = $options['temperature'];
            } elseif (!str_starts_with($model, 'gpt-5.2') && !array_key_exists('temperature', $options)) {
                $payload['temperature'] = 0.7;
            }
            if (isset($options['tools']) && $options['tools'] === false) {
                // Tools explizit deaktiviert - nichts hinzufügen
            } else {
            // Standard tools Array (MCP-Events kommen während des Streams)
                $tools = $this->getAvailableTools();
                if (!empty($tools)) {
                    $payload['tools'] = $this->normalizeToolsForResponses($tools);
                    if (isset($options['tool_choice'])) {
                        $payload['tool_choice'] = $options['tool_choice'];
                    }
                }
            }
            
            // Debug: Log Payload mit Größen-Info für cURL-Fehler-Debugging
            $payloadSize = strlen(json_encode($payload));
            $inputSize = isset($payload['input']) ? strlen(json_encode($payload['input'])) : 0;
            $toolsSize = isset($payload['tools']) ? strlen(json_encode($payload['tools'])) : 0;
            
            Log::debug('[OpenAI Chat] Sending request', [
                'url' => $this->baseUrl . '/responses',
                'payload_keys' => array_keys($payload),
                'payload_size_bytes' => $payloadSize,
                'payload_size_kb' => round($payloadSize / 1024, 2),
                'input_count' => count($payload['input'] ?? []),
                'input_size_bytes' => $inputSize,
                'has_tools' => isset($payload['tools']),
                'tools_count' => isset($payload['tools']) ? count($payload['tools']) : 0,
                'tools_size_bytes' => $toolsSize,
                'tool_names' => isset($payload['tools']) ? array_map(function($t) {
                    return $t['name'] ?? ($t['function']['name'] ?? 'unknown');
                }, $payload['tools']) : [],
                'note' => 'cURL error 52 (Empty reply) kann bei großen Requests auftreten',
            ]);
            
            $response = $this->http()->post($this->baseUrl . '/responses', $payload);
            
            // Debug: Log Response
            $rawBody = $response->body();
            Log::debug('[OpenAI Chat] Response received', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'body_length' => strlen($rawBody),
                'body_preview' => substr($rawBody, 0, 500),
            ]);
            
            if ($response->failed()) {
                $errorBody = $response->body();
                $this->logApiError('OpenAI API Error (responses)', $response->status(), $errorBody);
                
                // Versuche Fehler-Details zu extrahieren
                $errorMessage = $this->formatApiErrorMessage($response->status(), $errorBody);
                try {
                    $errorJson = json_decode($errorBody, true);
                    if ($errorJson && isset($errorJson['error'])) {
                        $errorDetails = json_encode($errorJson['error'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        $errorMessage .= "\n\nOpenAI Error Details:\n" . $errorDetails;
                        
                        // Zeige auch im Log für besseres Debugging
                        Log::error('[OpenAI Chat] Error details', [
                            'status' => $response->status(),
                            'error' => $errorJson['error'],
                            'payload_tools_count' => count($payload['tools'] ?? []),
                            'payload_keys' => array_keys($payload),
                        ]);
                    } else {
                        $errorMessage .= "\n\nOpenAI Response Body: " . substr($errorBody, 0, 1000);
                    }
                } catch (\Throwable $e) {
                    // Ignore JSON parse errors
                    $errorMessage .= "\n\nRaw Response: " . substr($errorBody, 0, 1000);
                }
                
                // Erweiterte Error-Logging für cURL-Fehler
                $isCurlError = str_contains($errorMessage, 'cURL error') || str_contains($errorMessage, 'Empty reply');
                $errorContext = [
                    'status' => $response->status(),
                    'body' => substr($errorBody, 0, 500),
                    'error_message' => $errorMessage,
                ];
                
                if ($isCurlError) {
                    // Spezielle Logging für cURL-Fehler
                    $errorContext['curl_error'] = true;
                    $errorContext['payload_size_kb'] = isset($payloadSize) ? round($payloadSize / 1024, 2) : 'unknown';
                    $errorContext['input_count'] = count($payload['input'] ?? []);
                    $errorContext['tools_count'] = count($payload['tools'] ?? []);
                    $errorContext['possible_causes'] = [
                        'Request zu groß (Payload > 1MB kann Probleme verursachen)',
                        'Server-Überlastung (OpenAI-Server hat Verbindung geschlossen)',
                        'Netzwerk-Timeout (Verbindung wurde unterbrochen)',
                        'Zu viele Tools (kann Request-Größe erhöhen)',
                    ];
                    $errorContext['suggestions'] = [
                        'Chat-Historie kürzen (ältere Messages entfernen)',
                        'Weniger Tools senden (nur relevante Tools)',
                        'Request später erneut versuchen',
                    ];
                }
                
                Log::error('[OpenAI Chat] Request failed', $errorContext);
                
                throw new \Exception($errorMessage);
            }
            $data = $response->json();
            
            // Debug: Log Response Data - zeige vollständige Response
            Log::debug('[OpenAI Chat] Response data', [
                'has_output' => isset($data['output']),
                'output_count' => isset($data['output']) && is_array($data['output']) ? count($data['output']) : 0,
                'has_output_text' => isset($data['output_text']),
                'has_content' => isset($data['content']),
                'content_type' => isset($data['content']) ? gettype($data['content']) : 'not set',
                'has_tool_calls' => isset($data['tool_calls']),
                'has_usage' => isset($data['usage']),
                'keys' => array_keys($data),
                'output_structure' => isset($data['output']) && is_array($data['output']) && isset($data['output'][0]) 
                    ? ['keys' => array_keys($data['output'][0]), 'has_content' => isset($data['output'][0]['content'])]
                    : null,
                'full_response' => $data, // Vollständige Response für Debugging
            ]);
            
            // Debug: Zeige alle möglichen Text-Felder
            $possibleTextFields = ['output_text', 'text', 'message', 'output', 'response', 'answer'];
            foreach ($possibleTextFields as $field) {
                if (isset($data[$field])) {
                    Log::debug("[OpenAI Chat] Found field '{$field}'", [
                        'type' => gettype($data[$field]),
                        'value_preview' => is_string($data[$field]) ? substr($data[$field], 0, 100) : $data[$field],
                    ]);
                }
            }
            
            // Versuche verschiedene Response-Formate
            $content = '';
            $toolCalls = null;
            
            // Format 1: output[*] (Responses API Format) – wichtig für mehrere Tool-Calls pro Runde
            if (isset($data['output']) && is_array($data['output']) && count($data['output']) > 0) {
                $toolCalls = [];
                foreach ($data['output'] as $outputItem) {
                    if (!is_array($outputItem)) { continue; }
                
                    // Responses API kann function_call direkt als output item liefern
                    // Format: {"type":"function_call","name":"...","arguments":"{...}","call_id":"..."}
                if (isset($outputItem['type']) && $outputItem['type'] === 'function_call') {
                    $toolCalls[] = [
                        'id' => $outputItem['call_id'] ?? ($outputItem['id'] ?? null),
                        'type' => 'function',
                        'function' => [
                            'name' => $outputItem['name'] ?? null,
                            'arguments' => isset($outputItem['arguments']) 
                                ? (is_string($outputItem['arguments']) ? $outputItem['arguments'] : json_encode($outputItem['arguments']))
                                : '{}',
                        ],
                    ];
                        continue;
                }
                
                    // Legacy: tool_calls direkt in output item
                    if (isset($outputItem['tool_calls']) && is_array($outputItem['tool_calls'])) {
                        $toolCalls = array_merge($toolCalls, $outputItem['tool_calls']);
                }
                
                    // content kann gemischte Items enthalten (Text + function_call)
                if (isset($outputItem['content']) && is_array($outputItem['content'])) {
                    foreach ($outputItem['content'] as $contentItem) {
                            if (!is_array($contentItem)) { continue; }
                            
                        // Tool-Call in content?
                            if (isset($contentItem['type']) && ($contentItem['type'] === 'tool_call' || $contentItem['type'] === 'function_call')) {
                                $toolCalls[] = [
                                    'id' => $contentItem['id'] ?? ($contentItem['tool_call_id'] ?? $contentItem['call_id'] ?? null),
                                    'type' => 'function',
                                    'function' => [
                                        'name' => $contentItem['name'] ?? ($contentItem['function_name'] ?? ($contentItem['function']['name'] ?? null)),
                                        'arguments' => isset($contentItem['arguments']) 
                                            ? (is_string($contentItem['arguments']) ? $contentItem['arguments'] : json_encode($contentItem['arguments']))
                                            : (isset($contentItem['function_arguments']) 
                                                ? (is_string($contentItem['function_arguments']) ? $contentItem['function_arguments'] : json_encode($contentItem['function_arguments']))
                                                : (isset($contentItem['function']['arguments']) 
                                                    ? (is_string($contentItem['function']['arguments']) ? $contentItem['function']['arguments'] : json_encode($contentItem['function']['arguments']))
                                                    : '{}')),
                                    ],
                                ];
                                continue;
                        }
                        
                            // Text-Content (kann mehrere Segmente enthalten → append)
                        if (isset($contentItem['text']) && is_string($contentItem['text'])) {
                                $content .= $contentItem['text'];
                            } elseif (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text']) && is_string($contentItem['text'])) {
                                $content .= $contentItem['text'];
                        }
                    }
                }
                }
                
                if (empty($toolCalls)) {
                    $toolCalls = null;
                }
                $content = trim($content);
            }
            // Format 2: output_text (String) - Legacy
            elseif (isset($data['output_text']) && is_string($data['output_text'])) {
                $content = $data['output_text'];
            }
            // Format 3: content als Array [{'type': 'text', 'text': '...'}]
            elseif (isset($data['content']) && is_array($data['content']) && isset($data['content'][0])) {
                if (isset($data['content'][0]['text'])) {
                    $content = $data['content'][0]['text'];
                } elseif (isset($data['content'][0]['content'])) {
                    $content = $data['content'][0]['content'];
                }
            }
            // Format 4: text direkt
            elseif (isset($data['text']) && is_string($data['text'])) {
                $content = $data['text'];
            }
            // Format 5: message
            elseif (isset($data['message']) && is_string($data['message'])) {
                $content = $data['message'];
            }
            
            // Tool-Calls aus verschiedenen Quellen extrahieren
            if ($toolCalls === null) {
                // Versuche tool_calls direkt aus data
                if (isset($data['tool_calls']) && is_array($data['tool_calls'])) {
                    $toolCalls = $data['tool_calls'];
                }
                // Versuche function_calls (Legacy)
                elseif (isset($data['function_calls']) && is_array($data['function_calls'])) {
                    $toolCalls = array_map(function($fc) {
                        return [
                            'id' => $fc['id'] ?? null,
                            'type' => 'function',
                            'function' => [
                                'name' => $fc['name'] ?? null,
                                'arguments' => json_encode($fc['arguments'] ?? []),
                            ],
                        ];
                    }, $data['function_calls']);
                }
            }
            
            // Fallback: Wenn content leer, aber output_tokens > 0 UND es gibt auch keine Tool-Calls,
            // dann ist wahrscheinlich was schiefgelaufen.
            // (Bei function_call ist empty content normal.)
            if ($content === '' && ($toolCalls === null) && isset($data['usage']['output_tokens']) && $data['usage']['output_tokens'] > 0) {
                Log::warning('[OpenAI Chat] Content ist leer, aber output_tokens > 0', [
                    'data_keys' => array_keys($data),
                    'has_output' => isset($data['output']),
                    'output_count' => isset($data['output']) ? count($data['output']) : 0,
                    'output_structure' => isset($data['output']) && is_array($data['output']) && isset($data['output'][0])
                        ? json_encode($data['output'][0], JSON_PRETTY_PRINT)
                        : null,
                    'full_data' => $data, // Vollständige Response für Debugging
                ]);
            }
            
            // Debug: Log Tool-Calls
            if ($toolCalls !== null) {
                Log::debug('[OpenAI Chat] Tool-Calls gefunden', [
                    'count' => count($toolCalls),
                    'tool_calls' => $toolCalls,
                ]);
            }
            
            return [
                'content' => $content,
                'usage' => $data['usage'] ?? [],
                'model' => $data['model'] ?? $model,
                'tool_calls' => $toolCalls,
                'finish_reason' => $data['finish_reason'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [ 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ]);
            throw $e;
        }
    }

    public function streamChat(array $messages, callable $onDelta, string $model = self::DEFAULT_MODEL, array $options = []): void
    {
        // Allow runtime override via config
        $model = $options['model'] ?? config('tools.openai.model', $model);
        $messagesWithContext = $this->buildMessagesWithContext($messages, $options);
        $payload = [
            'model' => $model,
            'input' => $this->buildResponsesInput($messagesWithContext),
            'stream' => true,
            'max_output_tokens' => $options['max_tokens'] ?? 1000,
        ];
        // Some models (e.g. gpt-5.2*) do not support temperature in the Responses API.
        if (!str_starts_with($model, 'gpt-5.2') && array_key_exists('temperature', $options)) {
            $payload['temperature'] = $options['temperature'];
        } elseif (!str_starts_with($model, 'gpt-5.2') && !array_key_exists('temperature', $options)) {
            $payload['temperature'] = 0.7;
        }

        // Optional: enable reasoning signals in the Responses API (model-dependent)
        // Example: ['effort' => 'medium', 'summary' => 'auto']
        if (isset($options['reasoning']) && is_array($options['reasoning'])) {
            $payload['reasoning'] = $options['reasoning'];
        }
        if (isset($options['tools']) && $options['tools'] === false) {
            // Tools explizit deaktiviert - nichts hinzufügen
        } else {
            // Tools aktiviert:
            // - Wenn $options['tools'] ein Array ist, nutzen wir es direkt (z.B. OpenAI built-in Tools wie web_search)
            // - Sonst: Standard = interne Tools aus Registry
            if (isset($options['tools']) && is_array($options['tools'])) {
                $payload['tools'] = $options['tools'];
                $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
            } else {
                // Standard tools Array (MCP-Events kommen während des Streams)
                $tools = $this->getAvailableTools();
                $payload['tools'] = $this->normalizeToolsForResponses($tools);
                $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
                
                // Debug: Log Tools (nur wenn Logging aktiviert ist)
                if (config('app.debug', false)) {
                    Log::debug('[OpenAI Stream] Tools aktiviert', [
                        'tool_count' => count($tools),
                        'tool_names' => array_map(function($t) {
                            return $t['function']['name'] ?? $t['name'] ?? 'unknown';
                        }, $tools),
                    ]);
                }
            }
            
            // Debug: Log Tools (nur wenn Logging aktiviert ist)
            if (config('app.debug', false)) {
                Log::debug('[OpenAI Stream] Tool payload (final)', [
                    'tool_count' => count($payload['tools'] ?? []),
                    'tool_types' => array_values(array_unique(array_map(fn($t) => $t['type'] ?? 'unknown', $payload['tools'] ?? []))),
                ]);
            }
        }

        // Optional: prepend OpenAI built-in tools (e.g. web_search) while still using internal discovery tools.
        if (!empty($options['include_web_search'])) {
            $payload['tools'] = $payload['tools'] ?? [];
            $hasWebSearch = false;
            foreach ($payload['tools'] as $t) {
                if (($t['type'] ?? null) === 'web_search') { $hasWebSearch = true; break; }
            }
            if (!$hasWebSearch) {
                array_unshift($payload['tools'], ['type' => 'web_search']);
            }
        }
        // Debug: Log Payload (nur wenn Debug aktiviert)
        if (config('app.debug', false)) {
            Log::debug('[OpenAI Stream] Sending payload', [
                'payload_keys' => array_keys($payload),
                'tools_count' => count($payload['tools'] ?? []),
                'tools' => $payload['tools'] ?? [],
            ]);
        }
        
        $response = $this->http(withStream: true)->post($this->baseUrl . '/responses', $payload);
        if ($response->failed()) {
            $errorBody = $response->body();
            $this->logApiError('OpenAI API Error (responses stream)', $response->status(), $errorBody);
            
            // Debug: Zeige vollständige Fehlerantwort
            if (config('app.debug', false)) {
                Log::error('[OpenAI Stream] Full error response', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'payload' => $payload,
                ]);
            }
            
            // Erweitere Fehlermeldung mit vollständiger Antwort
            $errorMessage = $this->formatApiErrorMessage($response->status(), $errorBody);
            try {
                $errorJson = json_decode($errorBody, true);
                if ($errorJson && isset($errorJson['error'])) {
                    $errorDetails = json_encode($errorJson['error'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $errorMessage .= "\n\nOpenAI Error Details:\n" . $errorDetails;
                    
                    // Zeige auch im Log für besseres Debugging
                    Log::error('[OpenAI Stream] Error details', [
                        'error' => $errorJson['error'],
                        'payload_tools' => $payload['tools'] ?? [],
                    ]);
                } else {
                    $errorMessage .= "\n\nOpenAI Response Body: " . substr($errorBody, 0, 1000);
                }
            } catch (\Throwable $e) {
                // Ignore JSON parse errors
            }
            throw new \Exception($errorMessage);
        }
        $this->parseResponsesStream($response->toPsrResponse()->getBody(), $onDelta, $messages, $options);
    }

    private function normalizeToolsForResponses(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            if (isset($tool['function']) && is_array($tool['function'])) {
                $fn = $tool['function'];
                
                // WICHTIG: Responses API erlaubt keine Punkte im Tool-Namen
                // Erlaubt nur: [a-zA-Z0-9_-]+
                // Mapping: "planner.projects.create" -> "planner_projects_create"
                $originalName = $fn['name'] ?? null;
                $openAiName = $this->normalizeToolNameForOpenAi($originalName);
                
                $out[] = [
                    'type' => 'function',
                    'name' => $openAiName,
                    'description' => $fn['description'] ?? ($tool['description'] ?? null),
                    'parameters' => $fn['parameters'] ?? null,
                ];
            } else {
                $out[] = $tool;
            }
        }
        return $out;
    }
    
    /**
     * Tool-Name-Mapper (lazy-loaded)
     */
    private ?ToolNameMapper $nameMapper = null;
    
    /**
     * Normalisiert Tool-Namen für OpenAI Responses API
     * 
     * Responses API erlaubt nur: [a-zA-Z0-9_-]+
     * Mapping: "planner.projects.create" -> "planner_projects_create"
     * 
     * @deprecated Verwende ToolNameMapper::toProvider() stattdessen
     */
    private function normalizeToolNameForOpenAi(string $name): string
    {
        if ($this->nameMapper === null) {
            try {
                $this->nameMapper = app(ToolNameMapper::class);
            } catch (\Throwable $e) {
                // Fallback: Direktes Mapping
                return str_replace('.', '_', $name);
            }
        }
        
        return $this->nameMapper->toProvider($name);
    }
    
    /**
     * Denormalisiert Tool-Namen von OpenAI Responses API zurück zu internem Format
     * 
     * Mapping: "planner_projects_create" -> "planner.projects.create"
     * 
     * @deprecated Verwende ToolNameMapper::toCanonical() stattdessen
     */
    private function denormalizeToolNameFromOpenAi(string $openAiName): string
    {
        if ($this->nameMapper === null) {
            try {
                $this->nameMapper = app(ToolNameMapper::class);
            } catch (\Throwable $e) {
                // Fallback: Direktes Mapping
                return str_replace('_', '.', $openAiName);
            }
        }
        
        return $this->nameMapper->toCanonical($openAiName);
    }

    private function parseResponsesStream($body, callable $onDelta, array $messages, array $options): void
    {
        $buffer = '';
        $currentEvent = null; $currentToolCall = null; $toolArguments = '';
        $currentMcpServer = null; // MCP: Server-Name für aktuellen Tool-Call
        $onToolStart = $options['on_tool_start'] ?? null; $toolExecutor = $options['tool_executor'] ?? null;
        $onDebug = $options['on_debug'] ?? null; // Optional: Debug-Callback für detailliertes Logging
        // Optional: Separate Streams für reasoning/thinking (gpt-5.2-thinking)
        // Doku: response.reasoning_text.delta + response.reasoning_summary_text.delta
        $onReasoningDelta = $options['on_reasoning_delta'] ?? null; // z.B. reasoning_summary_text
        $onThinkingDelta = $options['on_thinking_delta'] ?? null;   // z.B. reasoning_text
        $eventCount = 0;
        $deltaCount = 0;
        while (!$body->eof()) {
            $chunk = $body->read(8192); if ($chunk === '' || $chunk === false) { usleep(10000); continue; }
            $buffer .= str_replace(["\r\n","\r"], "\n", $chunk);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos); $buffer = substr($buffer, $pos + 1);
                if ($line === '') { continue; }
                
                // SSE Format: event: <name> oder data: <json>
                if (strncmp($line, 'event:', 6) === 0) { 
                    $currentEvent = trim(substr($line, 6)); 
                    continue; 
                }
                if (strncmp($line, 'data:', 5) !== 0) { continue; }
                $data = ltrim(substr($line, 5)); 
                if ($data === '[DONE]') { 
                    Log::info('[OpenAI Stream] Stream completed', ['delta_count' => $deltaCount]);
                    return; 
                }
                $decoded = json_decode($data, true); 
                if (!is_array($decoded)) { 
                    Log::debug('[OpenAI Stream] Non-array data', ['data' => substr($data, 0, 100)]);
                    continue; 
                }
                
                // Debug: Log alle Events für die ersten 20 Events
                if ($eventCount < 20) {
                    Log::debug('[OpenAI Stream] Event data', [
                        'event' => $currentEvent,
                        'data_keys' => array_keys($decoded),
                        'data_preview' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ]);
                    $eventCount++;
                }
                
                // Optional: Debug-Callback für detailliertes Logging (z.B. im TestCommand)
                if (is_callable($onDebug)) {
                    try {
                        $onDebug($currentEvent, $decoded);
                    } catch (\Throwable $e) {
                        // Ignore debug callback errors
                    }
                }
                
                switch ($currentEvent) {
                    // Reasoning / Thinking Streaming (Responses API Doku)
                    // - response.reasoning_summary_text.delta: "safe" summary stream (wir nennen das reasoning)
                    // - response.reasoning_text.delta: detailed reasoning stream (wir nennen das thinking)
                    case 'response.reasoning_summary_text.delta':
                    case 'reasoning_summary_text.delta':
                        if (is_callable($onReasoningDelta)) {
                            $rDelta = $decoded['delta'] ?? ($decoded['text'] ?? ($decoded['content'] ?? ''));
                            if (is_string($rDelta) && $rDelta !== '') {
                                try { $onReasoningDelta($rDelta); } catch (\Throwable $e) {}
                            }
                        }
                        break;
                    case 'response.reasoning_summary_text.done':
                    case 'reasoning_summary_text.done':
                        if (is_callable($onReasoningDelta)) {
                            $rText = $decoded['text'] ?? ($decoded['content'] ?? '');
                            if (is_string($rText) && $rText !== '') {
                                try { $onReasoningDelta($rText); } catch (\Throwable $e) {}
                            }
                        }
                        break;
                    case 'response.reasoning_text.delta':
                    case 'reasoning_text.delta':
                        if (is_callable($onThinkingDelta)) {
                            $tDelta = $decoded['delta'] ?? ($decoded['text'] ?? ($decoded['content'] ?? ''));
                            if (is_string($tDelta) && $tDelta !== '') {
                                try { $onThinkingDelta($tDelta); } catch (\Throwable $e) {}
                            }
                        }
                        break;
                    case 'response.reasoning_text.done':
                    case 'reasoning_text.done':
                        if (is_callable($onThinkingDelta)) {
                            $tText = $decoded['text'] ?? ($decoded['content'] ?? '');
                            if (is_string($tText) && $tText !== '') {
                                try { $onThinkingDelta($tText); } catch (\Throwable $e) {}
                            }
                        }
                        break;
                    case 'response.output_text.delta':
                    case 'response.output.delta':
                    case 'output_text.delta':
                    case 'output.delta':
                        // Responses API: delta kann direkt im decoded sein, oder in einem nested object
                        $delta = '';
                        if (isset($decoded['delta']) && is_string($decoded['delta'])) {
                            $delta = $decoded['delta'];
                        } elseif (isset($decoded['text']) && is_string($decoded['text'])) {
                            $delta = $decoded['text'];
                        } elseif (isset($decoded['content']) && is_string($decoded['content'])) {
                            $delta = $decoded['content'];
                        } elseif (isset($decoded['output_text']) && is_string($decoded['output_text'])) {
                            $delta = $decoded['output_text'];
                        } elseif (isset($decoded['delta']['text'])) {
                            $delta = $decoded['delta']['text'];
                        }
                        
                        if ($delta !== '') { 
                            $deltaCount++;
                            $onDelta($delta); 
                        }
                        break;
                    case 'response.output_item.added':
                        // Responses API: Tool-Aufruf wurde erstellt - extrahiere Tool-Namen
                        if (isset($decoded['item']['type'])) {
                            if ($decoded['item']['type'] === 'function_call') {
                                // Standard Function-Calling Format
                                $currentToolCall = $decoded['item']['name'] ?? ($decoded['item']['function_name'] ?? null);
                                if ($currentToolCall && is_callable($onToolStart)) { 
                                    try { 
                                        $onToolStart($currentToolCall); 
                                    } catch (\Throwable $e) {} 
                                }
                            } elseif ($decoded['item']['type'] === 'mcp_call') {
                                // MCP Format: Tool-Aufruf wurde erstellt
                                $currentToolCall = $decoded['item']['name'] ?? ($decoded['item']['tool_name'] ?? null);
                                $currentMcpServer = $decoded['item']['server'] ?? ($decoded['server_name'] ?? null);
                                if ($currentToolCall && is_callable($onToolStart)) { 
                                    try { 
                                        $onToolStart($currentToolCall); 
                                    } catch (\Throwable $e) {} 
                                }
                                Log::debug('[OpenAI Stream] MCP call created', [
                                    'item_id' => $decoded['item']['id'] ?? null,
                                    'tool_name' => $currentToolCall,
                                    'server' => $currentMcpServer,
                                ]);
                            }
                        }
                        break;
                    case 'response.tool_call.created':
                    case 'tool_call.created':
                        $currentToolCall = $decoded['name'] ?? ($decoded['tool_name'] ?? null);
                        if ($currentToolCall && is_callable($onToolStart)) { try { $onToolStart($currentToolCall); } catch (\Throwable $e) {} }
                        break;
                    case 'response.function_call_arguments.delta':
                        // Responses API: Tool-Argumente werden gestreamt
                        $delta = $decoded['delta'] ?? '';
                        if (is_string($delta)) {
                            $toolArguments .= $delta;
                        }
                        break;
                    case 'response.mcp_call_arguments.delta':
                        // MCP: Partielle Tool-Argumente werden gestreamt
                        $delta = $decoded['delta'] ?? '';
                        if (is_string($delta)) {
                            $toolArguments .= $delta;
                        }
                        // Extrahiere Tool-Namen aus item_id, falls vorhanden
                        if (!$currentToolCall && isset($decoded['item_id'])) {
                            // item_id könnte Tool-Informationen enthalten - für später
                            Log::debug('[OpenAI Stream] MCP call arguments delta', [
                                'item_id' => $decoded['item_id'] ?? null,
                                'output_index' => $decoded['output_index'] ?? null,
                            ]);
                        }
                        break;
                    case 'response.tool_call.delta':
                    case 'tool_call.delta':
                        $toolArguments .= $decoded['arguments_delta'] ?? ($decoded['arguments'] ?? '');
                        break;
                    case 'response.function_call_arguments.done':
                        // Responses API: Tool-Argumente sind vollständig
                        $arguments = $decoded['arguments'] ?? '';
                        if (is_string($arguments)) {
                            $toolArguments = $arguments; // Ersetze statt append, da done ist
                        }
                        // Führe Tool sofort aus, da Argumente vollständig sind
                        if ($currentToolCall && $toolArguments !== '') {
                            $this->executeToolIfReady($currentToolCall, $toolArguments, $toolExecutor, $onDelta, $messages);
                            $currentToolCall = null; 
                            $toolArguments = '';
                        }
                        break;
                    case 'response.mcp_call_arguments.done':
                        // MCP: Tool-Argumente sind vollständig
                        $arguments = $decoded['arguments'] ?? '';
                        if (is_string($arguments)) {
                            $toolArguments = $arguments; // Ersetze statt append, da done ist
                        }
                        // Extrahiere Server-Name, falls nicht bereits gesetzt
                        if (!$currentMcpServer && isset($decoded['server'])) {
                            $currentMcpServer = $decoded['server'];
                        }
                        // Führe Tool sofort aus, da Argumente vollständig sind
                        if ($currentToolCall && $toolArguments !== '') {
                            $this->executeToolIfReady($currentToolCall, $toolArguments, $toolExecutor, $onDelta, $messages, $currentMcpServer);
                            $currentToolCall = null; 
                            $currentMcpServer = null;
                            $toolArguments = '';
                        }
                        Log::debug('[OpenAI Stream] MCP call arguments done', [
                            'item_id' => $decoded['item_id'] ?? null,
                            'output_index' => $decoded['output_index'] ?? null,
                            'has_arguments' => !empty($arguments),
                        ]);
                        break;
                    case 'response.mcp_call.completed':
                        // MCP: Tool-Aufruf erfolgreich abgeschlossen
                        if (!$currentMcpServer && isset($decoded['server'])) {
                            $currentMcpServer = $decoded['server'];
                        }
                        if ($currentToolCall && $toolArguments !== '') {
                            // Falls noch nicht ausgeführt, jetzt ausführen
                            $this->executeToolIfReady($currentToolCall, $toolArguments, $toolExecutor, $onDelta, $messages, $currentMcpServer);
                        }
                        Log::debug('[OpenAI Stream] MCP call completed', [
                            'item_id' => $decoded['item_id'] ?? null,
                            'output_index' => $decoded['output_index'] ?? null,
                            'server' => $currentMcpServer,
                        ]);
                        $currentToolCall = null; 
                        $currentMcpServer = null;
                        $toolArguments = '';
                        break;
                    case 'response.mcp_call.failed':
                        // MCP: Tool-Aufruf fehlgeschlagen
                        $errorMessage = $decoded['error'] ?? ($decoded['message'] ?? 'MCP call failed');
                        $onDelta("\n\n**⚠️ Tool-Fehler (MCP):** " . $errorMessage . "\n");
                        Log::warning('[OpenAI Stream] MCP call failed', [
                            'item_id' => $decoded['item_id'] ?? null,
                            'output_index' => $decoded['output_index'] ?? null,
                            'error' => $errorMessage,
                        ]);
                        $currentToolCall = null; 
                        $toolArguments = '';
                        break;
                    case 'response.mcp_list_tools':
                    case 'response.mcp_list_tools.in_progress':
                    case 'response.mcp_list_tools.completed':
                    case 'response.mcp_list_tools.failed':
                        // MCP: Tool-Liste wurde angefordert/geladen
                        $serverName = $decoded['server'] ?? ($decoded['server_name'] ?? null);
                        $tools = $decoded['tools'] ?? [];
                        
                        Log::debug('[OpenAI Stream] MCP list tools event', [
                            'event' => $currentEvent,
                            'server' => $serverName,
                            'output_index' => $decoded['output_index'] ?? null,
                            'has_tools' => !empty($tools),
                            'tools_count' => count($tools),
                        ]);
                        
                        // Wenn Tools geladen wurden, können wir sie dynamisch hinzufügen
                        if ($currentEvent === 'response.mcp_list_tools.completed' && !empty($tools) && $serverName) {
                            // Tools wurden erfolgreich geladen - können für zukünftige Calls verwendet werden
                            // Hinweis: Aktueller Stream hat bereits die Tools, aber für nächste Iterationen
                            Log::info('[OpenAI Stream] MCP tools loaded', [
                                'server' => $serverName,
                                'tools_count' => count($tools),
                                'tool_names' => array_map(function($t) {
                                    return $t['name'] ?? 'unknown';
                                }, $tools),
                        ]);
                        }
                        break;
                    case 'response.tool_call.completed':
                    case 'tool_call.completed':
                        $this->executeToolIfReady($currentToolCall, $toolArguments, $toolExecutor, $onDelta, $messages);
                        $currentToolCall = null; $toolArguments = '';
                        break;
                    case 'response.completed':
                    case 'completed':
                        return;
                    default:
                        break;
                }
            }
        }
    }

    private function executeToolIfReady(?string $toolName, string $toolArguments, $toolExecutor, callable $onDelta, array $messages, ?string $mcpServerName = null): void
    {
        if (!$toolName || $toolArguments === '') { return; }
        try {
            // MCP-Format: Tool-Name ohne Modul-Präfix (z.B. "projects.GET")
            // Modul kommt aus Server-Name (z.B. "planner")
            // Kombiniert: "planner.projects.GET"
            if ($mcpServerName) {
                $internalToolName = $mcpServerName . '.' . $toolName;
            } else {
                // Fallback: Standard Denormalisierung (für Backwards-Kompatibilität)
            $internalToolName = $this->denormalizeToolNameFromOpenAi($toolName);
            }
            
            $arguments = json_decode($toolArguments, true);
            $result = null;
            if ($arguments && is_callable($toolExecutor)) { try { $result = $toolExecutor($internalToolName, $arguments); } catch (\Throwable $e) { Log::error('tool_executor failed: '.$e->getMessage()); } }
            if ($result !== null) {
                $lastUser = '';
                foreach (array_reverse($messages) as $m) { if (($m['role'] ?? '') === 'user' && is_string($m['content'] ?? null)) { $lastUser = $m['content']; break; } }
                $summarySystem = 'Formuliere eine kurze, präzise, deutschsprachige Antwort für den Nutzer basierend auf dem folgenden Tool-Ergebnis. Vermeide Roh-JSON.';
                $summaryUser = (($lastUser !== '' ? ("Frage: " . $lastUser . "\n\n") : '') . "Tool: " . $toolName . "\nErgebnis (JSON):\n" . json_encode($result, JSON_UNESCAPED_UNICODE));
                try {
                    $summary = $this->chat([
                        ['role' => 'system', 'content' => $summarySystem],
                        ['role' => 'user', 'content' => $summaryUser],
                    ], model: self::DEFAULT_MODEL, options: [ 'with_context' => false, 'tools' => false, 'max_tokens' => 300, 'temperature' => 0.2 ]);
                    $text = $summary['content'] ?? '';
                    if ($text !== '') { $onDelta("\n" . $text . "\n"); }
                } catch (\Throwable $e) {
                    Log::error('[OpenAI Stream] Summary generation failed', ['error' => $e->getMessage()]);
                    $onDelta("\nErgebnis wurde ermittelt.\n");
                }
            }
        } catch (\Throwable $e) {
            Log::error('executeToolIfReady error: '.$e->getMessage());
            $onDelta("\n\n**Tool-Fehler:** " . $e->getMessage() . "\n");
        }
    }

    private function http(bool $withStream = false): PendingRequest
    {
        $timeoutSeconds = (int) config('tools.openai.timeout_seconds', 60);
        $connectTimeoutSeconds = (int) config('tools.openai.connect_timeout_seconds', 10);
        $retryAttempts = (int) config('tools.openai.retry_attempts', 3);
        $retryMinMs = (int) config('tools.openai.retry_sleep_min_ms', 400);
        $retryMaxMs = (int) config('tools.openai.retry_sleep_max_ms', 1200);
        if ($retryMinMs < 0) { $retryMinMs = 0; }
        if ($retryMaxMs < $retryMinMs) { $retryMaxMs = $retryMinMs; }

        $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
                'User-Agent' => 'Glowkit-Core/1.0 (+Laravel)'
            ])
            ->timeout($timeoutSeconds)
            ->connectTimeout($connectTimeoutSeconds)
            ->retry(
                $retryAttempts,
                random_int($retryMinMs, $retryMaxMs),
                function ($exception, $request) { return $exception instanceof ConnectionException; },
                false // WICHTIG: nicht automatisch auf 4xx/5xx throwen – wir wollen Body/JSON sauber auswerten
            );
        if ($withStream) { $request = $request->withOptions(['stream' => true]); }
        return $request;
    }

    private function buildMessagesWithContext(array $messages, array $options): array
    {
        $withContext = $options['with_context'] ?? true;
        if (!$withContext) { return $messages; }
        try {
            $context = app(CoreContextTool::class)->getContext();
            if (!empty($options['source_route'])) { $context['data']['route'] = $options['source_route']; }
            if (!empty($options['source_module'])) { $context['data']['module'] = $options['source_module']; }
            if (!empty($options['source_url'])) { $context['data']['url'] = $options['source_url']; }
            $defaultPrompt = "Du bist ein hilfreicher Assistent für eine Plattform. Antworte kurz, präzise und auf Deutsch.

Tools sind verfügbar, wenn du sie benötigst. Tools folgen REST-Logik. Wenn du ein Tool brauchst, das du nicht siehst, rufe 'tools.GET' mit dem entsprechenden Modul auf. Wenn wirklich kein Tool existiert, kannst du 'tools.request' nutzen, um den Bedarf zu dokumentieren.";
            $prompt = $context['data']['system_prompt'] ?? $defaultPrompt;
            $u = $context['data']['user'] ?? null; $t = $context['data']['team'] ?? null;
            $module = $context['data']['module'] ?? null; $route = $context['data']['route'] ?? null; $url = $context['data']['url'] ?? null; $time = $context['data']['current_time'] ?? null; $tz = $context['data']['timezone'] ?? null;
            $naturalCtx = trim(implode(' ', array_filter([
                $u ? 'Nutzer: ' . ($u['name'] ?? ('#'.$u['id'])) : null,
                $t ? 'Team: ' . ($t['name'] ?? ('#'.$t['id'])) : null,
                $module ? 'Modul: ' . $module : null,
                $route ? 'Route: ' . $route : null,
                $url ? 'URL: ' . $url : null,
                ($time && $tz) ? ('Zeit: ' . $time . ' ' . $tz) : null,
            ])));
            
            // Tool-Informationen zum System-Prompt hinzufügen
            $toolsInfo = $this->buildToolsInfo();
            
            $systemMessage = $prompt;
            if ($naturalCtx !== '') {
                $systemMessage .= ' ' . $naturalCtx;
            }
            if ($toolsInfo !== '') {
                $systemMessage .= "\n\n" . $toolsInfo;
            }
            
            array_unshift($messages, [ 'role' => 'system', 'content' => $systemMessage ]);
        } catch (\Throwable $e) { }
        return $messages;
    }

    private function buildResponsesInput(array $messages): array
    {
        $input = [];
        $lastAssistantIndex = null;
        
        foreach ($messages as $m) {
            // Advanced: allow passing raw Responses API input items through (best practice for tool calling)
            // Examples: ['type' => 'function_call', ...], ['type' => 'function_call_output', ...]
            if (isset($m['type']) && is_string($m['type'])) {
                $type = $m['type'];
                if ($type === 'function_call') {
                    $input[] = [
                        'type' => 'function_call',
                        'id' => $m['id'] ?? null,
                        'call_id' => $m['call_id'] ?? null,
                        'name' => $m['name'] ?? null,
                        'arguments' => $m['arguments'] ?? '',
                    ];
                    continue;
                }
                if ($type === 'function_call_output') {
                    $input[] = [
                        'type' => 'function_call_output',
                        'call_id' => $m['call_id'] ?? null,
                        'output' => $m['output'] ?? '',
                    ];
                    continue;
                }
            }

            $role = $m['role'] ?? 'user';
            
            // WICHTIG: Responses API unterstützt 'tool' role nicht!
            // Tool-Results müssen als User-Message gesendet werden
            // Format: User-Message mit Tool-Result als Content
            if ($role === 'tool') {
                // Tool-Result - konvertiere zu User-Message
                $toolCallId = $m['tool_call_id'] ?? null;
                $content = $m['content'] ?? '';
                
                // Versuche Tool-Result zu parsen, um es lesbarer zu machen
                $parsed = json_decode($content, true);
                if (is_array($parsed)) {
                    // Formatiere Tool-Result als lesbaren Text
                    $resultText = "Tool-Result (call_id: {$toolCallId}): " . json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } else {
                    $resultText = "Tool-Result (call_id: {$toolCallId}): " . $content;
                }
                
                $input[] = [
                    'role' => 'user',
                    'content' => $resultText,
                ];
            } else {
                // Normale Messages (user, assistant, system)
                $text = is_array($m['content'] ?? null) ? json_encode($m['content']) : ($m['content'] ?? '');
                $input[] = [
                    'role' => $role,
                    'content' => $text,
                ];
                
                // Speichere Index der letzten Assistant-Message (für zukünftige Erweiterungen)
                if ($role === 'assistant') {
                    $lastAssistantIndex = count($input) - 1;
                }
            }
        }
        
        return $input;
    }

    private function logApiError(string $message, int $status, string $body): void
    {
        Log::error($message, [ 'status' => $status, 'body' => $body ]);
    }

    private function buildToolsInfo(): string
    {
        try {
            // Prüfe ob Registry verfügbar ist
            $container = app();
            if (!$container->bound(ToolRegistry::class)) {
                return '';
            }
            $registry = $container->make(ToolRegistry::class);
            $modules = \Platform\Core\Registry\ModuleRegistry::all();
            $allTools = $registry->all();
            
            if (count($allTools) === 0 && count($modules) === 0) {
                return '';
            }
            
            $info = "";
            
            // Module-Übersicht
            if (count($modules) > 0) {
                $info .= "Module:\n";
                foreach ($modules as $moduleKey => $moduleConfig) {
                    $moduleTools = array_filter($allTools, function($tool) use ($moduleKey) {
                        return str_starts_with($tool->getName(), $moduleKey . '.');
                    });
                    
                    $info .= "- " . ($moduleConfig['title'] ?? ucfirst($moduleKey));
                    if (count($moduleTools) > 0) {
                        $info .= " (" . count($moduleTools) . " Tools)";
                    }
                    $info .= "\n";
                }
                $info .= "\n";
            }
            
            // KEINE Tool-Liste mehr direkt anzeigen - das widerspricht dem Discovery-Layer!
            // Die LLM sieht standardmäßig nur Discovery-Tools und kann tools.GET aufrufen
            // Stattdessen: Nur Module-Übersicht, damit die LLM weiß, welche Module es gibt
            
            return $info;
        } catch (\Throwable $e) {
            // Silent fail - keine Tool-Info
            return '';
        }
    }

    private function formatApiErrorMessage(int $status, string $body): string
    {
        $prefix = match (true) {
            $status === 401 => 'AUTHENTICATION_FAILED',
            $status === 403 => 'PERMISSION_DENIED',
            $status === 404 => 'NOT_FOUND',
            $status === 409 => 'CONFLICT',
            $status === 422 => 'VALIDATION',
            $status === 429 => 'RATE_LIMITED',
            $status >= 500 => 'DEPENDENCY_FAILED',
            default => 'INTERNAL_ERROR',
        };
        // Versuche OpenAI Error JSON zu extrahieren (hilft enorm beim Debugging von 400ern)
        $details = null;
        try {
            $json = json_decode($body, true);
            if (is_array($json) && isset($json['error']) && is_array($json['error'])) {
                $err = $json['error'];
                $details = trim(implode(' | ', array_filter([
                    isset($err['type']) ? ('type=' . $err['type']) : null,
                    isset($err['code']) ? ('code=' . $err['code']) : null,
                    isset($err['param']) ? ('param=' . $err['param']) : null,
                    isset($err['message']) ? ('message=' . $err['message']) : null,
                ])));
            }
        } catch (\Throwable $e) {
            $details = null;
        }

        $msg = $prefix . ': OpenAI request failed (HTTP ' . $status . ').';
        if (is_string($details) && $details !== '') {
            $msg .= ' ' . $details;
        } else {
            // Fallback: Body snippet (ohne riesige Dumps)
            $snippet = trim(substr($body, 0, 600));
            if ($snippet !== '') {
                $msg .= ' body=' . $snippet;
            }
        }
        return $msg;
    }

    public function getModels(): array
    {
        try {
            $response = $this->http()->get($this->baseUrl . '/models');
            if ($response->failed()) { throw new \Exception($this->formatApiErrorMessage($response->status(), $response->body())); }
            return $response->json()['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('OpenAI Models Error', [ 'message' => $e->getMessage() ]);
            return [];
        }
    }

    /**
     * Zusätzliche Tools, die dynamisch nachgeladen wurden (z.B. via tools.GET)
     * Format: ['tool_name' => ['tool' => ToolContract, 'loaded_at' => timestamp, 'used' => bool], ...]
     */
    private array $dynamicallyLoadedTools = [];

    /**
     * Lädt Tools dynamisch nach (wird aufgerufen, wenn LLM tools.GET verwendet hat)
     * 
     * @param array $toolNames Array von Tool-Namen, die nachgeladen werden sollen
     */
    public function loadToolsDynamically(array $toolNames): void
    {
        try {
            $registry = $this->getToolRegistry();
            if ($registry === null) {
                Log::warning('[OpenAI Tools] ToolRegistry nicht verfügbar für dynamisches Nachladen', [
                    'tool_names' => $toolNames,
                ]);
                return;
            }
            $loadedCount = 0;
            $notFoundCount = 0;
            $alreadyLoadedCount = 0;
            
            foreach ($toolNames as $toolName) {
                if ($registry->has($toolName)) {
                    // Prüfe, ob Tool bereits geladen ist
                    if (!isset($this->dynamicallyLoadedTools[$toolName])) {
                        $tool = $registry->get($toolName);
                        $this->dynamicallyLoadedTools[$toolName] = [
                            'tool' => $tool,
                            'loaded_at' => time(),
                            'used' => false,
                            'iterations_since_load' => 0,
                        ];
                        $loadedCount++;
                        Log::debug('[OpenAI Tools] Tool dynamisch geladen', [
                            'tool_name' => $toolName,
                            'tool_class' => get_class($tool),
                        ]);
                    } else {
                        $alreadyLoadedCount++;
                        Log::debug('[OpenAI Tools] Tool bereits geladen, überspringe', [
                            'tool_name' => $toolName,
                        ]);
                }
                } else {
                    $notFoundCount++;
                    Log::warning('[OpenAI Tools] Tool nicht in Registry gefunden', [
                        'tool_name' => $toolName,
                        'available_tools' => array_slice(array_keys($registry->all()), 0, 10), // Erste 10 für Debugging
                    ]);
                }
            }
            
            Log::info('[OpenAI Tools] Dynamisches Tool-Nachladen abgeschlossen', [
                'requested_tools' => count($toolNames),
                'loaded' => $loadedCount,
                'not_found' => $notFoundCount,
                'already_loaded' => $alreadyLoadedCount,
                'total_dynamically_loaded' => count($this->dynamicallyLoadedTools),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[OpenAI Tools] Fehler beim dynamischen Nachladen von Tools', [
                'tool_names' => $toolNames,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
        }
    }

    /**
     * Markiert ein Tool als verwendet (wird aufgerufen, wenn Tool tatsächlich aufgerufen wurde)
     * 
     * @param string $toolName Name des verwendeten Tools
     */
    public function markToolAsUsed(string $toolName): void
    {
        if (isset($this->dynamicallyLoadedTools[$toolName])) {
            $this->dynamicallyLoadedTools[$toolName]['used'] = true;
            $this->dynamicallyLoadedTools[$toolName]['iterations_since_load'] = 0; // Reset
        }
    }

    /**
     * Entfernt nicht genutzte Tools nach einer bestimmten Anzahl von Iterationen
     * 
     * @param int $maxUnusedIterations Maximale Anzahl von Iterationen ohne Nutzung (Standard: 3)
     */
    public function cleanupUnusedTools(int $maxUnusedIterations = 3): void
    {
        $removedTools = [];
        foreach ($this->dynamicallyLoadedTools as $toolName => $toolData) {
            // Erhöhe Iterations-Zähler
            $this->dynamicallyLoadedTools[$toolName]['iterations_since_load']++;
            
            // Entferne Tool, wenn:
            // 1. Es nicht verwendet wurde UND
            // 2. Es bereits X Iterationen geladen ist
            if (!$toolData['used'] && $toolData['iterations_since_load'] >= $maxUnusedIterations) {
                unset($this->dynamicallyLoadedTools[$toolName]);
                $removedTools[] = $toolName;
            }
        }
        
        if (!empty($removedTools)) {
            Log::info('[OpenAI Tools] Nicht genutzte Tools entfernt', [
                'removed_tools' => $removedTools,
                'count' => count($removedTools),
            ]);
        }
    }

    /**
     * Setzt dynamisch geladene Tools zurück (für neue Sessions)
     */
    public function resetDynamicallyLoadedTools(): void
    {
        $this->dynamicallyLoadedTools = [];
    }

    /**
     * Gibt die Liste der dynamisch geladenen Tools zurück.
     * Wird für die Persistenz in der Session benötigt.
     *
     * @return array Array von Tool-Namen (Keys von $dynamicallyLoadedTools)
     */
    public function getDynamicallyLoadedTools(): array
    {
        return array_keys($this->dynamicallyLoadedTools);
    }

    private function getAvailableTools(): array
    {
        $tools = [];
        
        // DISCOVERY-LAYER: Skalierbare Tool-Verwaltung
        // Wenn zu viele Tools vorhanden sind, senden wir nur Discovery-Tools
        // LLM kann dann tools.GET aufrufen, um Tools zu sehen und gezielt anzufordern
        $toolCountThreshold = config('openai.tool_count_threshold', 20); // Konfigurierbar
        
        // 1. Tools aus ToolRegistry (loose gekoppelt - Module registrieren ihre Tools hier)
        // WICHTIG: Robuste Fehlerbehandlung - Chat funktioniert auch ohne Tools
        try {
            $toolRegistry = null;
            
            // Loose coupling: ToolRegistry ist optional (Chat funktioniert auch ohne Tools)
            $toolRegistry = $this->getToolRegistry();
            if ($toolRegistry === null) {
                // Keine Registry verfügbar - Chat funktioniert auch ohne Tools
                    return $tools; // Leeres Array zurückgeben
            }
            
            // Tools aus Registry holen
            try {
                $allTools = $toolRegistry->all();
                $totalToolCount = count($allTools);
                
                // WICHTIG: Filtere Tools nach Berechtigung (Modul-Zugriff)
                $permissionService = app(\Platform\Core\Services\ToolPermissionService::class);
                $allTools = $permissionService->filterToolsByPermission($allTools);
                
                // DISCOVERY-LAYER: Standardmäßig NUR Discovery-Tools senden
                // LLM kann dann tools.GET aufrufen, um weitere Tools bei Bedarf zu sehen
                // Das ist MCP Best Practice und skaliert auch bei 100+ Tools
                $discovery = app(\Platform\Core\Tools\ToolDiscoveryService::class);
                $readOnlyCount = 0;
                $writeCount = 0;
                
                foreach ($allTools as $tool) {
                    $toolName = $tool->getName();
                    $metadata = $discovery->getToolMetadata($tool);
                    $isReadOnly = (bool)($metadata['read_only'] ?? false);
                    
                    // Zähle für Logging
                    if ($isReadOnly) {
                        $readOnlyCount++;
                    } else {
                        $writeCount++;
                    }
                    
                    // NUR Discovery-Tools (standardmäßig)
                    // LLM kann tools.GET aufrufen, um weitere Tools zu sehen
                    $isDiscoveryTool = in_array($toolName, [
                        'tools.GET',           // Tool-Liste anfordern (wichtigste Discovery-Tool)
                        'tools.request',       // Fehlende Tools anmelden
                        'core.modules.GET',    // Verfügbare Module sehen
                        'core.context.GET',    // Aktuellen Kontext sehen
                        'core.user.GET',       // Aktuellen User sehen
                        'core.teams.GET',      // Verfügbare Teams sehen
                    ]);
                    
                    // NUR Discovery-Tools senden (nicht read-only!)
                    // LLM muss aktiv tools.GET aufrufen, um weitere Tools zu sehen
                    if ($isDiscoveryTool) {
                        try {
                            $toolDef = $this->convertToolToOpenAiFormat($tool);
                            if ($toolDef) {
                                $tools[] = $toolDef;
                            }
                        } catch (\Throwable $e) {
                            // Einzelnes Tool-Fehler - überspringen
                        }
                    }
                }
                
                // DYNAMISCH NACHGELADENE TOOLS: Füge Tools hinzu, die via tools.GET angefordert wurden
                foreach ($this->dynamicallyLoadedTools as $toolName => $toolData) {
                    $tool = $toolData['tool'];
                    
                    // Prüfe, ob Tool bereits in der Liste ist (verhindere Duplikate)
                    // WICHTIG: Vergleiche sowohl ursprünglichen als auch normalisierten Namen
                    $alreadyIncluded = false;
                    $normalizedToolName = $this->normalizeToolNameForOpenAi($toolName);
                    
                    foreach ($tools as $existingTool) {
                        $existingName = $existingTool['function']['name'] ?? null;
                        $existingNormalized = $this->normalizeToolNameForOpenAi($existingName ?? '');
                        
                        // Prüfe sowohl ursprünglichen als auch normalisierten Namen
                        if ($existingName === $toolName || $existingNormalized === $normalizedToolName) {
                            $alreadyIncluded = true;
                            break;
                        }
                    }
                    
                    if (!$alreadyIncluded) {
                        try {
                            $toolDef = $this->convertToolToOpenAiFormat($tool);
                            if ($toolDef) {
                                $tools[] = $toolDef;
                                Log::debug('[OpenAI Tools] Dynamisch nachgeladenes Tool hinzugefügt', [
                                    'tool_name' => $toolName,
                                    'normalized_name' => $normalizedToolName,
                                    'iterations_since_load' => $toolData['iterations_since_load'],
                                    'used' => $toolData['used'],
                                ]);
                            } else {
                                Log::warning('[OpenAI Tools] Tool-Definition konnte nicht erstellt werden', [
                                    'tool_name' => $toolName,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('[OpenAI Tools] Fehler beim Hinzufügen von dynamisch geladenem Tool', [
                                'tool_name' => $toolName,
                                'error' => $e->getMessage(),
                                'trace' => substr($e->getTraceAsString(), 0, 500),
                            ]);
                        }
                    } else {
                        Log::debug('[OpenAI Tools] Tool bereits in Liste enthalten, überspringe', [
                            'tool_name' => $toolName,
                            'normalized_name' => $normalizedToolName,
                        ]);
                    }
                }
                
                Log::info('[OpenAI Tools] Discovery-Layer aktiviert', [
                    'total_tools' => $totalToolCount,
                    'read_only_tools' => $readOnlyCount,
                    'write_tools' => $writeCount,
                    'discovery_tools_sent' => count($tools) - count($this->dynamicallyLoadedTools),
                    'dynamically_loaded_tools' => count($this->dynamicallyLoadedTools),
                    'dynamically_loaded_tool_names' => array_keys($this->dynamicallyLoadedTools),
                    'total_tools_sent' => count($tools),
                    'note' => 'LLM kann tools.GET aufrufen, um weitere Tools bei Bedarf zu sehen',
                ]);
            } catch (\Throwable $e) {
                // Registry-Zugriff fehlgeschlagen - ohne Tools weiter
            }
        } catch (\Throwable $e) {
            // Kompletter Registry-Zugriff fehlgeschlagen - ohne Tools weiter
            // Chat funktioniert auch ohne Tools
        }
        
        return $tools;
    }

    /**
     * Konvertiert ein ToolContract zu OpenAI Function Format
     */
    /**
     * Konvertiert Tool zu OpenAI-Format mit komprimierten Manifesten
     * 
     * Komprimiert Tool-Manifeste für OpenAI:
     * - Kürzt description auf max. 150 Zeichen
     * - Minimale Schema-Definitionen (nur required fields)
     * - Metadaten werden nicht im Schema übertragen (reduziert Token-Usage)
     */
    private function convertToolToOpenAiFormat(\Platform\Core\Contracts\ToolContract $tool): array
    {
        // Komprimiere Description (max 150 Zeichen)
        $description = $tool->getDescription();
        if (mb_strlen($description) > 150) {
            $description = mb_substr($description, 0, 147) . '...';
        }
        
        // Komprimiere Schema (nur required fields behalten, restliche Properties optional)
        $schema = $tool->getSchema();
        $compressedSchema = $this->compressSchema($schema);
        
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool->getName(),
                'description' => $description,
                'parameters' => $compressedSchema,
            ]
        ];
    }
    
    /**
     * Komprimiert JSON Schema für OpenAI (reduziert Token-Usage)
     * 
     * - Behält nur required fields bei
     * - Entfernt optionale Beschreibungen aus Properties (wenn zu lang)
     * - Minimiert nested structures
     */
    private function compressSchema(array $schema): array
    {
        $compressed = [
            'type' => $schema['type'] ?? 'object',
        ];
        
        // Properties komprimieren
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $compressedProperties = [];
            foreach ($schema['properties'] as $key => $property) {
                $compressedProperty = [
                    'type' => $property['type'] ?? 'string',
                ];
                
                // WICHTIG: Für Arrays muss 'items' erhalten bleiben (OpenAI-Requirement)
                if (($property['type'] ?? '') === 'array' && isset($property['items'])) {
                    $compressedProperty['items'] = $this->compressSchema($property['items']);
                }
                
                // Nur required fields behalten
                if (isset($schema['required']) && in_array($key, $schema['required'])) {
                    // Für required fields: kürze description auf max 50 Zeichen
                    if (isset($property['description']) && mb_strlen($property['description']) > 50) {
                        $compressedProperty['description'] = mb_substr($property['description'], 0, 47) . '...';
                    } elseif (isset($property['description'])) {
                        $compressedProperty['description'] = $property['description'];
                    }
                    
                    // Enum-Werte behalten (wichtig für Validierung)
                    if (isset($property['enum'])) {
                        $compressedProperty['enum'] = $property['enum'];
                    }
                } else {
                    // Für optionale fields: nur type, keine description (spart Tokens)
                    // ABER: items für Arrays immer behalten!
                    if (($property['type'] ?? '') === 'array' && isset($property['description'])) {
                        // Für optionale Arrays: description behalten (wichtig für LLM)
                        if (mb_strlen($property['description']) > 50) {
                            $compressedProperty['description'] = mb_substr($property['description'], 0, 47) . '...';
                        } else {
                            $compressedProperty['description'] = $property['description'];
                        }
                    }
                }
                
                $compressedProperties[$key] = $compressedProperty;
            }
            // WICHTIG: OpenAI erwartet immer ein 'properties' Objekt bei type: "object"
            // Auch wenn leer, muss es vorhanden sein (als leeres Objekt {}, nicht Array [])
            // Wenn leer, konvertiere zu Objekt, damit es zu {} in JSON wird, nicht []
            if (empty($compressedProperties)) {
                $compressed['properties'] = new \stdClass();
            } else {
            $compressed['properties'] = $compressedProperties;
            }
        } elseif (($schema['type'] ?? 'object') === 'object') {
            // Wenn kein properties im Schema, aber type ist "object", füge leeres Objekt hinzu
            $compressed['properties'] = new \stdClass();
        }
        
        // Required fields behalten (nur wenn nicht leer)
        if (isset($schema['required']) && is_array($schema['required']) && !empty($schema['required'])) {
            $compressed['required'] = $schema['required'];
        }
        
        return $compressed;
    }

    /**
     * Baut MCP-Server-Struktur aus verfügbaren Tools
     * Gruppiert Tools nach Modulen (z.B. planner, core)
     */
    private function buildMcpServers(): array
    {
        $tools = $this->getAvailableTools();
        $mcpServers = [];
        
        foreach ($tools as $tool) {
            // Extrahiere Tool-Name und Modul
            $toolName = $tool['function']['name'] ?? null;
            if (!$toolName) {
                continue;
            }
            
            // Modul aus Tool-Namen extrahieren (z.B. "planner.projects.GET" -> "planner")
            $module = $this->extractModuleFromToolName($toolName);
            
            // Initialisiere Server, falls nicht vorhanden
            if (!isset($mcpServers[$module])) {
                $mcpServers[$module] = [
                    'tools' => [],
                ];
            }
            
            // Konvertiere Tool zu MCP-Format
            $mcpTool = $this->convertToolToMcpFormat($tool);
            if ($mcpTool) {
                $mcpServers[$module]['tools'][] = $mcpTool;
            }
        }
        
        return $mcpServers;
    }

    /**
     * Konvertiert ein Tool von Standard Function-Calling Format zu MCP-Format
     */
    private function convertToolToMcpFormat(array $tool): ?array
    {
        if (!isset($tool['function']) || !is_array($tool['function'])) {
            return null;
        }
        
        $fn = $tool['function'];
        $originalName = $fn['name'] ?? null;
        if (!$originalName) {
            return null;
        }
        
        // MCP-Format: Tool-Name ohne Modul-Präfix (z.B. "projects.GET" statt "planner.projects.GET")
        $toolNameWithoutModule = $this->removeModulePrefix($originalName);
        
        // MCP nutzt "inputSchema" statt "parameters"
        $inputSchema = $fn['parameters'] ?? [];
        
        return [
            'name' => $toolNameWithoutModule,
            'description' => $fn['description'] ?? '',
            'inputSchema' => $inputSchema,
        ];
    }

    /**
     * Extrahiert Modul aus Tool-Namen (z.B. "planner.projects.GET" -> "planner")
     */
    private function extractModuleFromToolName(string $toolName): string
    {
        if (str_contains($toolName, '.')) {
            $parts = explode('.', $toolName);
            return $parts[0];
        }
        return 'core';
    }

    /**
     * Entfernt Modul-Präfix aus Tool-Namen (z.B. "planner.projects.GET" -> "projects.GET")
     */
    private function removeModulePrefix(string $toolName): string
    {
        if (str_contains($toolName, '.')) {
            $parts = explode('.', $toolName, 2);
            return $parts[1] ?? $toolName;
        }
        return $toolName;
    }
}

