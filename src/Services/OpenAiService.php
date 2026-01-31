<?php

namespace Platform\Core\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Tools\CoreContextTool;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Models\CoreAiProvider;

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
            // DB-driven parameter support (fallback: one retry stripping unsupported params).
            $payload = $this->applySupportedSamplingParams($payload, $options);
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

            // Retry once without unsupported parameters (loose robustness).
            if ($response->failed()) {
                $retryPayload = $this->stripUnsupportedParamFromError($payload, $response->body());
                if ($retryPayload !== null) {
                    $response = $this->http()->post($this->baseUrl . '/responses', $retryPayload);
                }
            }
            
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
        // Responses API: continue from previous response (best practice for tool calling loops)
        $hasPreviousResponseId = isset($options['previous_response_id']) && is_string($options['previous_response_id']) && $options['previous_response_id'] !== '';
        if ($hasPreviousResponseId) {
            $payload['previous_response_id'] = $options['previous_response_id'];
        }
        // DB-driven parameter support (fallback: one retry stripping unsupported params).
        $payload = $this->applySupportedSamplingParams($payload, $options);

        // Optional: enable reasoning signals in the Responses API (model-dependent)
        // Example: ['effort' => 'medium', 'summary' => 'auto']
        if (isset($options['reasoning']) && is_array($options['reasoning'])) {
            $payload['reasoning'] = $options['reasoning'];
        }

        // WICHTIG: Bei previous_response_id (Tool-Continuation) werden Tools NICHT erneut gesendet!
        // Die Tools sind bereits in der vorherigen Response enthalten. Wenn wir sie erneut senden,
        // kann es zu Inkonsistenzen kommen (z.B. durch dynamisch geladene Tools), was HTTP 400 verursacht.
        if ($hasPreviousResponseId) {
            // Bei Continuation: Keine Tools senden - OpenAI nutzt die Tools aus der vorherigen Response
            // Debug-Log für Troubleshooting
            if (config('app.debug', false)) {
                Log::debug('[OpenAI Stream] Continuation mode - Tools nicht gesendet (previous_response_id aktiv)', [
                    'previous_response_id' => $options['previous_response_id'],
                    'input_count' => count($payload['input'] ?? []),
                    'input_types' => array_map(fn($i) => $i['type'] ?? ($i['role'] ?? 'unknown'), $payload['input'] ?? []),
                ]);
            }
        } elseif (isset($options['tools']) && $options['tools'] === false) {
            // Tools explizit deaktiviert - nichts hinzufügen
        } else {
            // Tools aktiviert (nur bei initialem Request ohne previous_response_id):
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
        // WICHTIG: Nicht bei previous_response_id, da Tools dort nicht gesendet werden dürfen!
        if (!empty($options['include_web_search']) && !$hasPreviousResponseId) {
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

        // Retry once without unsupported parameters (loose robustness).
        if ($response->failed()) {
            $retryPayload = $this->stripUnsupportedParamFromError($payload, $response->body());
            if ($retryPayload !== null) {
                $response = $this->http(withStream: true)->post($this->baseUrl . '/responses', $retryPayload);
            }
        }
        if ($response->failed()) {
            // IMPORTANT: When using Laravel HTTP client with stream=true, body() can be empty on 4xx/5xx.
            // Try to read from the PSR response body stream to capture the actual OpenAI error JSON.
            $errorBody = $response->body();
            if (!is_string($errorBody)) { $errorBody = ''; }
            if (trim($errorBody) === '') {
                try {
                    $psrBody = $response->toPsrResponse()->getBody();
                    if (is_object($psrBody) && method_exists($psrBody, 'getContents')) {
                        $errorBody = (string) $psrBody->getContents();
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            // Last resort (debug/opt-in only): replay the same request once without streaming to capture error details.
            // In production this can cause extra latency/cost, so keep it behind a flag.
            if (trim($errorBody) === '' && (config('app.debug', false) || config('services.openai.diagnose_empty_stream_errors', false))) {
                try {
                    $diagPayload = $payload;
                    $diagPayload['stream'] = false;
                    $diag = $this->http(withStream: false)->post($this->baseUrl . '/responses', $diagPayload);
                    $diagBody = $diag->body();
                    if (is_string($diagBody) && trim($diagBody) !== '') {
                        $errorBody = $diagBody;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
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
        $body = $response->toPsrResponse()->getBody();
        try {
            $this->parseResponsesStream($body, $onDelta, $messages, $options);
        } finally {
            // Ensure we close the upstream stream when the client aborts (or any exception occurs).
            // This gives OpenAI a chance to stop generating and releases resources immediately.
            try {
                if (is_object($body) && method_exists($body, 'close')) {
                    $body->close();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
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
                
                // WICHTIG: OpenAI erwartet ein Object für parameters, NICHT null
                // Ein leeres Schema ist { "type": "object", "properties": {} }
                $parameters = $fn['parameters'] ?? null;
                if ($parameters === null || (is_array($parameters) && empty($parameters))) {
                    $parameters = ['type' => 'object', 'properties' => new \stdClass()];
                }

                $out[] = [
                    'type' => 'function',
                    'name' => $openAiName,
                    'description' => $fn['description'] ?? ($tool['description'] ?? ''),
                    'parameters' => $parameters,
                ];
            } else {
                $out[] = $tool;
            }
        }
        return $out;
    }

    /**
     * Apply optional sampling params only if DB says they are supported for this model.
     * If the DB field is NULL (unknown), we do NOT send that param automatically.
     */
    private function applySupportedSamplingParams(array $payload, array $options): array
    {
        $model = (string)($payload['model'] ?? '');
        if ($model === '') {
            return $payload;
        }

        // temperature
        if (array_key_exists('temperature', $options)) {
            if ($this->isParamSupportedByDb($model, 'temperature') === true) {
                $payload['temperature'] = $options['temperature'];
            } else {
                unset($payload['temperature']);
            }
        }

        // top_p
        if (array_key_exists('top_p', $options)) {
            if ($this->isParamSupportedByDb($model, 'top_p') === true) {
                $payload['top_p'] = $options['top_p'];
            } else {
                unset($payload['top_p']);
            }
        }

        // presence_penalty
        if (array_key_exists('presence_penalty', $options)) {
            if ($this->isParamSupportedByDb($model, 'presence_penalty') === true) {
                $payload['presence_penalty'] = $options['presence_penalty'];
            } else {
                unset($payload['presence_penalty']);
            }
        }

        // frequency_penalty
        if (array_key_exists('frequency_penalty', $options)) {
            if ($this->isParamSupportedByDb($model, 'frequency_penalty') === true) {
                $payload['frequency_penalty'] = $options['frequency_penalty'];
            } else {
                unset($payload['frequency_penalty']);
            }
        }

        return $payload;
    }

    /**
     * Map OpenAI error JSON { error: { param, message } } and return a payload with that param removed.
     * Returns null if we cannot confidently strip.
     */
    private function stripUnsupportedParamFromError(array $payload, string $errorBody): ?array
    {
        try {
            $err = json_decode($errorBody, true);
            $param = $err['error']['param'] ?? null;
            $msg = $err['error']['message'] ?? null;
            $code = $err['error']['code'] ?? null;
            if (!is_string($param) || $param === '') {
                return null;
            }

            // 1) Top-level unsupported parameters (classic case)
            if (is_string($msg) && str_contains($msg, 'Unsupported parameter') && array_key_exists($param, $payload)) {
                $copy = $payload;
                unset($copy[$param]);
                return $copy;
            }

            // 2) Nested param: reasoning.summary can require org verification (OpenAI returns code=unsupported_value).
            if ($param === 'reasoning.summary' && isset($payload['reasoning']) && is_array($payload['reasoning'])) {
                // Only strip summary; keep effort if present.
                $copy = $payload;
                unset($copy['reasoning']['summary']);
                // If reasoning becomes empty after stripping, drop it entirely.
                if (empty($copy['reasoning'])) {
                    unset($copy['reasoning']);
                }
                return $copy;
            }

            // 3) Generic: if OpenAI complains about reasoning.* and code indicates unsupported value, drop reasoning.
            if (is_string($code) && $code === 'unsupported_value' && str_starts_with($param, 'reasoning.') && array_key_exists('reasoning', $payload)) {
                $copy = $payload;
                unset($copy['reasoning']);
                return $copy;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * DB-driven parameter support flags (core_ai_models.*).
     * Returns:
     * - true: supported
     * - false: not supported
     * - null: unknown (do not send automatically)
     */
    private function isParamSupportedByDb(string $modelId, string $param): ?bool
    {
        $providerKey = 'openai';
        $field = match ($param) {
            'temperature' => 'supports_temperature',
            'top_p' => 'supports_top_p',
            'presence_penalty' => 'supports_presence_penalty',
            'frequency_penalty' => 'supports_frequency_penalty',
            default => null,
        };
        if ($field === null) {
            return null;
        }

        try {
            $row = CoreAiModel::query()
                ->where('model_id', $modelId)
                ->whereHas('provider', fn($q) => $q->where('key', $providerKey))
                ->first([$field]);
            if (!$row) {
                return null;
            }
            // When column is NULL: unknown
            $v = $row->{$field};
            return is_bool($v) ? $v : null;
        } catch (\Throwable $e) {
            return null;
        }
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
        $shouldAbort = $options['should_abort'] ?? null;
        while (!$body->eof()) {
            // Abort even if the OpenAI stream is currently silent (no events) – important for "Stop" UX.
            if (is_callable($shouldAbort)) {
                try {
                    if ($shouldAbort()) {
                        throw new \RuntimeException('__CLIENT_ABORTED__');
                    }
                } catch (\RuntimeException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    // ignore abort callback errors
                }
            }
            $chunk = $body->read(8192); if ($chunk === '' || $chunk === false) { usleep(10000); continue; }
            $buffer .= str_replace(["\r\n","\r"], "\n", $chunk);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos); $buffer = substr($buffer, $pos + 1);
                if ($line === '') { continue; }
                if (is_callable($shouldAbort)) {
                    try {
                        if ($shouldAbort()) {
                            throw new \RuntimeException('__CLIENT_ABORTED__');
                        }
                    } catch (\RuntimeException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
                
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

                // FIX: Verwende 'type' aus Payload falls vorhanden (robuster als nur event: Header).
                // OpenAI sendet in jedem Event-Payload ein 'type'-Feld, das den echten Event-Typ angibt.
                // Wenn der event: Header fehlt oder falsch ist, verhindert dies das Leaken von
                // Tool-Arguments in den Text-Stream.
                if (isset($decoded['type']) && is_string($decoded['type']) && $decoded['type'] !== '') {
                    $currentEvent = $decoded['type'];
                }

                // FIX: Zusätzliche Absicherung - erkenne Tool-Call-Payloads anhand ihrer Struktur,
                // auch wenn der Event-Name falsch geroutet wurde.
                // Tool-Call-Payloads haben typischerweise 'call_id', 'name' + 'arguments', oder 'item_id' mit fc_ Präfix.
                $looksLikeToolCall = (
                    isset($decoded['call_id']) ||
                    (isset($decoded['name']) && isset($decoded['arguments'])) ||
                    (isset($decoded['item_id']) && is_string($decoded['item_id']) && str_starts_with($decoded['item_id'], 'fc_'))
                );

                // Wenn der aktuelle Event als Text-Delta geroutet werden würde, aber wie ein Tool-Call aussieht,
                // korrigiere das Routing auf function_call_arguments.delta
                if ($looksLikeToolCall && in_array($currentEvent, ['response.output_text.delta', 'response.output.delta', 'output_text.delta', 'output.delta'], true)) {
                    Log::warning('[OpenAI Stream] Prevented tool-call leak into text stream', [
                        'original_event' => $currentEvent,
                        'decoded_keys' => array_keys($decoded),
                        'call_id' => $decoded['call_id'] ?? null,
                        'item_id' => $decoded['item_id'] ?? null,
                    ]);
                    // Reroute zu function_call_arguments.delta, damit es korrekt akkumuliert wird
                    $currentEvent = 'response.function_call_arguments.delta';
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

                        // FIX: Letzte Absicherung - erkenne Tool-Arguments anhand des Inhalts.
                        // Tool-Arguments sind JSON-Objekte, die mit '{' beginnen und typische
                        // Schema-Felder enthalten (module, search, limit, etc.).
                        // Normale Text-Deltas beginnen selten mit '{' direkt gefolgt von '"'.
                        if ($delta !== '' && str_starts_with(ltrim($delta), '{"')) {
                            // Prüfe ob es wie ein Tool-Argument-JSON aussieht
                            $trimmedDelta = ltrim($delta);
                            // Typische Tool-Schema-Felder (aus den registrierten Tools)
                            $toolSchemaIndicators = ['"module":', '"search":', '"limit":', '"offset":', '"id":', '"name":', '"query":'];
                            $looksLikeToolArgs = false;
                            foreach ($toolSchemaIndicators as $indicator) {
                                if (str_contains($trimmedDelta, $indicator)) {
                                    $looksLikeToolArgs = true;
                                    break;
                                }
                            }
                            // Zusätzlich: Wenn es valides JSON ist und die typischen Tool-Felder hat
                            if ($looksLikeToolArgs) {
                                $parsed = @json_decode($trimmedDelta, true);
                                if (is_array($parsed) && (isset($parsed['module']) || isset($parsed['id']) || isset($parsed['query']))) {
                                    Log::warning('[OpenAI Stream] Blocked tool-arguments from text stream (content inspection)', [
                                        'delta_preview' => substr($delta, 0, 100),
                                        'parsed_keys' => is_array($parsed) ? array_keys($parsed) : null,
                                    ]);
                                    // Akkumuliere in toolArguments statt an Text-Stream senden
                                    $toolArguments .= $delta;
                                    break; // Nicht an onDelta senden
                                }
                            }
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
                        // More tolerant parsing: OpenAI can emit additional reasoning event names.
                        // We route any "*reasoning*.(delta|done)" events to the callbacks.
                        if (
                            is_string($currentEvent)
                            && str_contains($currentEvent, 'reasoning')
                            && (str_ends_with($currentEvent, '.delta') || str_ends_with($currentEvent, '.done'))
                        ) {
                            $txt = $decoded['delta'] ?? ($decoded['text'] ?? ($decoded['content'] ?? ''));
                            if (is_string($txt) && $txt !== '') {
                                $isSummary = str_contains($currentEvent, 'summary');
                                if ($isSummary && is_callable($onReasoningDelta)) {
                                    try { $onReasoningDelta($txt); } catch (\Throwable $e) {}
                                } elseif (!$isSummary && is_callable($onThinkingDelta)) {
                                    try { $onThinkingDelta($txt); } catch (\Throwable $e) {}
                                }
                            }
                            break;
                        }
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

        $headers = [
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'Content-Type' => 'application/json',
            'User-Agent' => 'Glowkit-Core/1.0 (+Laravel)',
        ];

        // Optional scoping headers (can affect quota/billing):
        // - OpenAI-Organization (legacy but still supported in some setups)
        // - OpenAI-Project (projects are the modern billing boundary; missing/wrong project can yield insufficient_quota)
        $org = config('services.openai.organization');
        if (is_string($org) && trim($org) !== '') {
            $headers['OpenAI-Organization'] = trim($org);
        }
        $project = config('services.openai.project');
        if (is_string($project) && trim($project) !== '') {
            $headers['OpenAI-Project'] = trim($project);
        }

        $request = Http::withHeaders($headers)
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
            $status === 400 => 'BAD_REQUEST',
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
                
                // DISCOVERY-LAYER (skalierbar):
                // Standardmäßig senden wir nur Entry-Point/Discovery-Tools.
                // Das LLM nutzt tools.GET, um gezielt weitere Tools zu entdecken und anzufordern.
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
                    
                    // Entry-Points/Discovery:
                    // - Core discovery tools (tools.GET, core.*)
                    // - Module overview tools (z.B. planner.overview.GET) damit das LLM Entitäten/Beziehungen versteht
                    $isDiscoveryTool = in_array($toolName, [
                        'tools.GET',           // Tool-Liste anfordern (wichtigste Discovery-Tool)
                        'tools.request',       // Fehlende Tools anmelden
                        'core.modules.GET',    // Verfügbare Module sehen
                        'core.context.GET',    // Aktuellen Kontext sehen
                        'core.user.GET',       // Aktuellen User sehen
                        'core.teams.GET',      // Verfügbare Teams sehen
                    ]) || str_ends_with($toolName, '.overview.GET')
                      || (($metadata['category'] ?? null) === 'overview')
                      || (is_array($metadata['tags'] ?? null) && in_array('overview', $metadata['tags'], true));
                    
                    // NUR Discovery-Tools senden.
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
        // WICHTIG: Behandle sowohl Array als auch stdClass (für leere properties)
        $properties = $schema['properties'] ?? null;
        $isValidProperties = is_array($properties) || $properties instanceof \stdClass;

        if ($isValidProperties) {
            // Konvertiere stdClass zu Array für die Iteration
            $propertiesArray = is_array($properties) ? $properties : (array) $properties;

            $compressedProperties = [];
            foreach ($propertiesArray as $key => $property) {
                // Skip wenn property kein Array ist (ungültiges Schema)
                if (!is_array($property)) {
                    continue;
                }

                $compressedProperty = [
                    'type' => $property['type'] ?? 'string',
                ];

                // WICHTIG: Für Arrays muss 'items' erhalten bleiben (OpenAI-Requirement)
                if (($property['type'] ?? '') === 'array' && isset($property['items'])) {
                    $compressedProperty['items'] = $this->compressSchema($property['items']);
                }

                // WICHTIG: enum-Werte IMMER behalten (für strict mode erforderlich)
                if (isset($property['enum'])) {
                    $compressedProperty['enum'] = $property['enum'];
                }

                // Descriptions für required fields behalten (oder kürzen)
                if (isset($schema['required']) && in_array($key, $schema['required'])) {
                    if (isset($property['description']) && mb_strlen($property['description']) > 50) {
                        $compressedProperty['description'] = mb_substr($property['description'], 0, 47) . '...';
                    } elseif (isset($property['description'])) {
                        $compressedProperty['description'] = $property['description'];
                    }
                } else {
                    // Für optionale fields: description nur bei Arrays behalten
                    if (($property['type'] ?? '') === 'array' && isset($property['description'])) {
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
            // Wenn leer, konvertiere zu stdClass, damit es zu {} in JSON wird, nicht []
            if (empty($compressedProperties)) {
                $compressed['properties'] = new \stdClass();
            } else {
                $compressed['properties'] = $compressedProperties;
            }
        } elseif (($schema['type'] ?? 'object') === 'object') {
            // Wenn kein properties im Schema, aber type ist "object", füge leeres Objekt hinzu
            $compressed['properties'] = new \stdClass();
        }

        // WICHTIG für strict mode: additionalProperties: false hinzufügen
        if (($compressed['type'] ?? 'object') === 'object') {
            $compressed['additionalProperties'] = false;
        }

        // Required fields behalten - auch leeres Array für strict mode
        if (isset($schema['required']) && is_array($schema['required'])) {
            $compressed['required'] = $schema['required'];
        } else {
            // Für strict mode: leeres required Array wenn nicht vorhanden
            $compressed['required'] = [];
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

