<?php

namespace Platform\Core\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Tools\CoreContextTool;
use Platform\Core\Tools\ToolBroker;
use Platform\Core\Tools\ToolRegistry;

class OpenAiService
{
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private string $baseUrl = 'https://api.openai.com/v1';

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
        $messagesWithContext = $this->buildMessagesWithContext($messages, $options);
        try {
            $payload = [
                'model' => $model,
                'input' => $this->buildResponsesInput($messagesWithContext),
                'stream' => false,
                'max_output_tokens' => $options['max_tokens'] ?? 1000,
                'temperature' => $options['temperature'] ?? 0.7,
            ];
            if (isset($options['tools']) && $options['tools'] === false) {
                // Tools explizit deaktiviert - nichts hinzufügen
            } else {
                // Tools aktivieren (Standard)
                $tools = $this->getAvailableTools();
                if (!empty($tools)) {
                    $payload['tools'] = $this->normalizeToolsForResponses($tools);
                    // tool_choice ist optional - nur setzen wenn explizit angegeben
                    if (isset($options['tool_choice'])) {
                        $payload['tool_choice'] = $options['tool_choice'];
                    }
                }
            }
            
            // Debug: Log Payload
            Log::debug('[OpenAI Chat] Sending request', [
                'url' => $this->baseUrl . '/responses',
                'payload_keys' => array_keys($payload),
                'input_count' => count($payload['input'] ?? []),
                'has_tools' => isset($payload['tools']),
                'tools_count' => isset($payload['tools']) ? count($payload['tools']) : 0,
                'tool_names' => isset($payload['tools']) ? array_map(function($t) {
                    return $t['name'] ?? ($t['function']['name'] ?? 'unknown');
                }, $payload['tools']) : [],
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
                
                Log::error('[OpenAI Chat] Request failed', [
                    'status' => $response->status(),
                    'body' => substr($errorBody, 0, 500),
                    'error_message' => $errorMessage,
                ]);
                
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
            
            // Format 1: output[0] (Responses API Format)
            if (isset($data['output']) && is_array($data['output']) && isset($data['output'][0])) {
                $outputItem = $data['output'][0];
                
                // WICHTIG: Responses API gibt function_call direkt in output[0] zurück!
                // Format: {"type":"function_call","name":"core_teams_list","arguments":"{...}","call_id":"..."}
                if (isset($outputItem['type']) && $outputItem['type'] === 'function_call') {
                    // Function-Call gefunden - konvertiere zu Tool-Call-Format
                    if ($toolCalls === null) {
                        $toolCalls = [];
                    }
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
                }
                
                // Prüfe auf Tool-Calls direkt in output[0] (Legacy-Format)
                elseif (isset($outputItem['tool_calls']) && is_array($outputItem['tool_calls'])) {
                    $toolCalls = $outputItem['tool_calls'];
                }
                
                // Prüfe ob content Tool-Calls enthält
                if (isset($outputItem['content']) && is_array($outputItem['content'])) {
                    foreach ($outputItem['content'] as $contentItem) {
                        // Tool-Call in content?
                        if (isset($contentItem['type'])) {
                            if ($contentItem['type'] === 'tool_call' || $contentItem['type'] === 'function_call') {
                                // Tool-Call gefunden
                                if ($toolCalls === null) {
                                    $toolCalls = [];
                                }
                                $toolCalls[] = [
                                    'id' => $contentItem['id'] ?? ($contentItem['tool_call_id'] ?? $contentItem['call_id'] ?? null),
                                    'type' => 'function',
                                    'function' => [
                                        'name' => $contentItem['name'] ?? ($contentItem['function_name'] ?? $contentItem['function']['name'] ?? null),
                                        'arguments' => isset($contentItem['arguments']) 
                                            ? (is_string($contentItem['arguments']) ? $contentItem['arguments'] : json_encode($contentItem['arguments']))
                                            : (isset($contentItem['function_arguments']) 
                                                ? (is_string($contentItem['function_arguments']) ? $contentItem['function_arguments'] : json_encode($contentItem['function_arguments']))
                                                : (isset($contentItem['function']['arguments']) 
                                                    ? (is_string($contentItem['function']['arguments']) ? $contentItem['function']['arguments'] : json_encode($contentItem['function']['arguments']))
                                                    : '{}')),
                                    ],
                                ];
                                continue; // Überspringe Text-Extraktion für Tool-Calls
                            }
                        }
                        
                        // Text-Content
                        if (isset($contentItem['text']) && is_string($contentItem['text'])) {
                            $content = $contentItem['text'];
                        } elseif (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text'])) {
                            $content = $contentItem['text'];
                        }
                    }
                }
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
            
            // Fallback: Wenn content leer, aber output_tokens > 0, dann ist was schiefgelaufen
            if ($content === '' && isset($data['usage']['output_tokens']) && $data['usage']['output_tokens'] > 0) {
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
        $messagesWithContext = $this->buildMessagesWithContext($messages, $options);
        $payload = [
            'model' => $model,
            'input' => $this->buildResponsesInput($messagesWithContext),
            'stream' => true,
            'max_output_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
        ];
        if (isset($options['tools']) && $options['tools'] === false) {
            // Tools explizit deaktiviert - nichts hinzufügen
        } else {
            // Tools aktivieren (Standard)
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
        $onToolStart = $options['on_tool_start'] ?? null; $toolExecutor = $options['tool_executor'] ?? null;
        $onDebug = $options['on_debug'] ?? null; // Optional: Debug-Callback für detailliertes Logging
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
                                if ($currentToolCall && is_callable($onToolStart)) { 
                                    try { 
                                        $onToolStart($currentToolCall); 
                                    } catch (\Throwable $e) {} 
                                }
                                Log::debug('[OpenAI Stream] MCP call created', [
                                    'item_id' => $decoded['item']['id'] ?? null,
                                    'tool_name' => $currentToolCall,
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
                        // Führe Tool sofort aus, da Argumente vollständig sind
                        if ($currentToolCall && $toolArguments !== '') {
                            $this->executeToolIfReady($currentToolCall, $toolArguments, $toolExecutor, $onDelta, $messages);
                            $currentToolCall = null; 
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
                        if ($currentToolCall && $toolArguments !== '') {
                            // Falls noch nicht ausgeführt, jetzt ausführen
                            $this->executeToolIfReady($currentToolCall, $toolArguments, $toolExecutor, $onDelta, $messages);
                        }
                        Log::debug('[OpenAI Stream] MCP call completed', [
                            'item_id' => $decoded['item_id'] ?? null,
                            'output_index' => $decoded['output_index'] ?? null,
                        ]);
                        $currentToolCall = null; 
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
                        // MCP: Tool-Liste wurde gesendet (optional - für Debugging)
                        Log::debug('[OpenAI Stream] MCP list tools event', [
                            'output_index' => $decoded['output_index'] ?? null,
                            'has_tools' => isset($decoded['tools']),
                            'tools_count' => isset($decoded['tools']) ? count($decoded['tools']) : 0,
                        ]);
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

    private function executeToolIfReady(?string $toolName, string $toolArguments, $toolExecutor, callable $onDelta, array $messages): void
    {
        if (!$toolName || $toolArguments === '') { return; }
        try {
            // WICHTIG: Tool-Name zurückmappen (von OpenAI-Format zu internem Format)
            // OpenAI: "planner_projects_create" -> Intern: "planner.projects.create"
            $internalToolName = $this->denormalizeToolNameFromOpenAi($toolName);
            
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
        $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
                'User-Agent' => 'Glowkit-Core/1.0 (+Laravel)'
            ])
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(1, random_int(250, 500), function ($exception, $request) { return $exception instanceof ConnectionException; });
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
            $defaultPrompt = 'Du bist ein hilfreicher Assistent für eine Plattform. Antworte kurz, präzise und auf Deutsch.

WICHTIG - Tool-Namen folgen REST-Pattern:
- Tools haben Namen wie "module.entity.GET", "module.entity.POST", "module.entity.PUT", "module.entity.DELETE"
- GET = Lesen/Abrufen (read-only, keine Änderungen)
- POST = Erstellen/Anlegen (write-Operation)
- PUT = Aktualisieren/Bearbeiten (write-Operation)
- DELETE = Löschen/Entfernen (write-Operation)
- Wenn der Nutzer etwas lesen möchte, nutze Tools mit ".GET"
- Wenn der Nutzer etwas erstellen möchte, nutze Tools mit ".POST"
- Wenn der Nutzer etwas ändern möchte, nutze Tools mit ".PUT"
- Wenn der Nutzer etwas löschen möchte, nutze Tools mit ".DELETE"

WICHTIG - Tool-Nutzung:
- Prüfe die verfügbaren Tools, wenn der Nutzer eine Frage stellt oder eine Aufgabe gibt
- Wenn ein Tool in seiner Beschreibung sagt, dass es für die aktuelle Situation passt, rufe es auf
- Nutze Tools proaktiv - warte nicht darauf, dass der Nutzer explizit nach einem Tool fragt
- Wenn ein Tool Parameter benötigt, die der Nutzer nicht angegeben hat, nutze Hilfs-Tools um die Optionen zu bekommen
- WICHTIG: Sage NICHT "Ich werde X tun" oder "Einen Moment bitte" - FÜHRE die Aktion DIREKT aus! Rufe das Tool sofort auf, ohne vorher anzukündigen, was du tun wirst

WICHTIG - Mehrere Tool-Calls:
- Du kannst MEHRERE Tool-Calls in EINER Runde machen - das System unterstützt das
- Wenn der Nutzer mehrere Items erstellt oder löschen möchte, kannst du das entsprechende Tool mehrfach aufrufen
- Wenn kritische Informationen fehlen, frage nach - aber nur einmal für alle Items, nicht für jedes einzeln

WICHTIG - Tool-Results verarbeiten:
- Nach jedem Tool-Result solltest du das ERGEBNIS verwenden und das NÄCHSTE Tool aufrufen
- Wenn ein Tool-Result bereits die benötigten Informationen enthält (z.B. Team-ID), rufe das NÄCHSTE Tool auf - nicht das gleiche Tool nochmal!
- Beispiel: Wenn "core.teams.GET" das aktuelle Team (ID 9) zurückgibt, rufe direkt "planner.projects.GET" auf - nicht "core.teams.GET" nochmal!
- Wenn du die benötigten Informationen hast, FÜHRE die nächste Aktion aus - keine Endlosschleifen!

WICHTIG - Tool-Discovery:
- Standardmäßig siehst du NUR Discovery-Tools (tools.GET, tools.request, core.context.GET, etc.)
- Wenn du Tools benötigst, nutze "tools.GET" um sie gezielt anzufordern
- Beispiel: Wenn du etwas löschen musst, nutze tools.GET mit filters: module="planner", read_only=false, um DELETE-Tools zu sehen
- Beispiel: Wenn du etwas lesen musst, nutze tools.GET mit filters: module="planner", read_only=true, um GET-Tools zu sehen
- Du kannst mehrere Module kombinieren: "Ich brauche read-Tools für core und write-Tools für planner" → nutze tools.GET mehrfach mit entsprechenden Filtern
- Das Tool "tools.GET" ermöglicht es dir, gezielt Tools anzufordern, die du für eine Aufgabe benötigst

WICHTIG - User-IDs und Kontext:
- Die User-ID des aktuellen Nutzers ist IMMER im Kontext verfügbar - du musst sie NICHT vom Nutzer erfragen
- Wenn ein Tool einen Parameter wie "owner_user_id", "user_id" oder "user_in_charge_id" benötigt und der Nutzer sagt "nimm mich selbst", "nimm nur mich" oder "ich selbst", dann LASS DIESEN PARAMETER WEG oder setze ihn auf null
- Die Tools verwenden automatisch die User-ID des aktuellen Nutzers aus dem Kontext, wenn der Parameter nicht angegeben ist
- Verwende NIEMALS hardcoded User-IDs wie 1, 0 oder andere Zahlen - diese sind nicht gültig und führen zu Fehlern
- Wenn der Nutzer sagt "nimm nur mich mit in das Team" oder "nimm nur mich selbst", dann LASS "owner_user_id" und "members" WEG - das Tool verwendet automatisch die richtige User-ID
- Wenn du unsicher bist, welche User-ID zu verwenden ist, LASS DEN PARAMETER WEG - das Tool verwendet dann automatisch die richtige ID

WICHTIG - Grenzen erkennen:
- Wenn du KEIN passendes Tool hast, um eine Aufgabe zu lösen, kommuniziere das KLAR
- Sage dem Nutzer: "Ich kann diese Aufgabe nicht ausführen, weil mir das Tool [Tool-Name] fehlt"
- Nutze dann das Tool "tools.request", um den Bedarf anzumelden
- RATE NICHT und führe NICHT falsch aus - es ist besser, klar zu sagen, dass du es nicht kannst
- Wenn du unsicher bist, ob du die Aufgabe richtig verstanden hast, frage nach: "Habe ich das richtig verstanden: Du möchtest [Zusammenfassung]?"';
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
            
            $info = "Verfügbare Funktionen:\n\n";
            
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
            
            // Wichtiger Hinweis (LOOSE & GENERISCH)
            $info .= "WICHTIG - Tool-Namen folgen REST-Pattern:\n";
            $info .= "- Tools haben Namen wie 'module.entity.GET', 'module.entity.POST', 'module.entity.PUT', 'module.entity.DELETE'\n";
            $info .= "- GET = Lesen/Abrufen (read-only, keine Änderungen)\n";
            $info .= "- POST = Erstellen/Anlegen (write-Operation)\n";
            $info .= "- PUT = Aktualisieren/Bearbeiten (write-Operation)\n";
            $info .= "- DELETE = Löschen/Entfernen (write-Operation)\n";
            $info .= "- Wenn der Nutzer etwas lesen möchte, nutze Tools mit '.GET'\n";
            $info .= "- Wenn der Nutzer etwas erstellen möchte, nutze Tools mit '.POST'\n";
            $info .= "- Wenn der Nutzer etwas ändern möchte, nutze Tools mit '.PUT'\n";
            $info .= "- Wenn der Nutzer etwas löschen möchte, nutze Tools mit '.DELETE'\n";
            $info .= "\n";
            $info .= "WICHTIG - Tool-Nutzung:\n";
            $info .= "- Prüfe die verfügbaren Tools, wenn der Nutzer eine Frage stellt oder eine Aufgabe gibt\n";
            $info .= "- Wenn ein Tool in seiner Beschreibung sagt, dass es für die aktuelle Situation passt, rufe es auf\n";
            $info .= "- Nutze Tools proaktiv - warte nicht darauf, dass der Nutzer explizit nach einem Tool fragt\n";
            $info .= "- Wenn du unsicher bist, welche Tools verfügbar sind, nutze 'tools.GET' um alle Tools zu sehen\n";
            $info .= "- WICHTIG: Sage NICHT 'Ich werde X tun' oder 'Einen Moment bitte' - FÜHRE die Aktion DIREKT aus! Rufe das Tool sofort auf, ohne vorher anzukündigen, was du tun wirst\n";
            $info .= "- Wenn du ein Tool aufrufen musst, rufe es sofort auf - keine Ankündigungen, keine 'Ich werde...'-Sätze\n";
            $info .= "\n";
            $info .= "DISCOVERY-LAYER & TOOL-CLUSTERING:\n";
            $info .= "- Standardmäßig siehst du NUR Discovery-Tools (tools.GET, tools.request, core.context.GET, etc.)\n";
            $info .= "- Wenn du Tools benötigst, nutze 'tools.GET' um sie gezielt anzufordern\n";
            $info .= "- Beispiel: Wenn du etwas löschen musst, nutze 'tools.GET' mit read_only=false und module='planner', um DELETE-Tools zu sehen\n";
            $info .= "- Beispiel: Wenn du etwas lesen musst, nutze 'tools.GET' mit read_only=true, um GET-Tools zu sehen\n";
            $info .= "- Du entscheidest selbst, welche Tools du brauchst und forderst sie gezielt an\n";
            $info .= "\n";
            $info .= "Tool-Details: Nutze das Tool 'tools.GET', um alle verfügbaren Tools und ihre Funktionen detailliert zu sehen.\n";
            
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
        return $prefix . ': OpenAI request failed (HTTP ' . $status . ').';
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
            
            // Versuche die Registry zu bekommen - sollte bereits geladen sein
            // WICHTIG: Verwende app() Helper, da $this->app nicht existiert
            try {
                $container = app();
                if ($container->bound(ToolRegistry::class)) {
                    $toolRegistry = $container->make(ToolRegistry::class);
                }
            } catch (\Throwable $e) {
                // Container-Zugriff fehlgeschlagen - kein Problem, erstelle neue Instanz
            }
            
            // Falls Registry nicht gebunden, erstelle neue Instanz (ohne Callbacks)
            if ($toolRegistry === null) {
                try {
                    $toolRegistry = new ToolRegistry();
                } catch (\Throwable $e) {
                    // Auch direkte Instanziierung fehlgeschlagen - ohne Tools weiter
                    return $tools; // Leeres Array zurückgeben
                }
            }
            
            // Tools aus Registry holen
            try {
                $allTools = $toolRegistry->all();
                $totalToolCount = count($allTools);
                
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
                
                Log::info('[OpenAI Tools] Discovery-Layer aktiviert (nur Discovery-Tools)', [
                    'total_tools' => $totalToolCount,
                    'read_only_tools' => $readOnlyCount,
                    'write_tools' => $writeCount,
                    'discovery_tools_sent' => count($tools),
                    'note' => 'LLM kann tools.GET aufrufen, um weitere Tools bei Bedarf zu sehen',
                ]);
            } catch (\Throwable $e) {
                // Registry-Zugriff fehlgeschlagen - ohne Tools weiter
            }
        } catch (\Throwable $e) {
            // Kompletter Registry-Zugriff fehlgeschlagen - ohne Tools weiter
            // Chat funktioniert auch ohne Tools
        }
        
        // 2. Legacy: Entity-basierte Tools aus ToolBroker (optional, falls verfügbar)
        // Diese können später entfernt werden, wenn alle Module auf ToolRegistry umgestellt sind
        try {
            // Prüfe ob ToolBroker verfügbar ist (optional)
            $container = app();
            if (!$container->bound(ToolBroker::class)) {
                // ToolBroker nicht verfügbar - kein Problem, wir verwenden nur ToolRegistry
                return $tools;
            }
            $toolBroker = $container->make(ToolBroker::class);
            $capabilities = $toolBroker->getAvailableCapabilities();
            
            // Entity-basierte Tools
            foreach ($capabilities['available_entities'] ?? [] as $entity) {
                foreach ($capabilities['available_operations'] ?? [] as $operation) {
                    try {
                $toolDef = $toolBroker->getToolDefinition($entity, $operation);
                        if ($toolDef) { 
                            $tools[] = $toolDef; 
                            Log::debug('[OpenAI Tools] Added legacy tool', ['entity' => $entity, 'operation' => $operation]); 
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[OpenAI Tools] Legacy tool failed', [
                            'entity' => $entity,
                            'operation' => $operation,
                            'error' => $e->getMessage()
                        ]);
                    }
            }
        }
            
            // Write Tool
            try {
                $writeTool = $toolBroker->getWriteToolDefinition();
                if ($writeTool) {
                    $tools[] = $writeTool;
                }
            } catch (\Throwable $e) {
                Log::warning('[OpenAI Tools] Write tool failed', ['error' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            // ToolBroker nicht verfügbar - kein Problem, wir verwenden nur ToolRegistry
            Log::debug('[OpenAI Tools] ToolBroker not available', ['error' => $e->getMessage()]);
        }
        
        Log::info('[OpenAI Tools] Final tools', ['count' => count($tools)]);
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
            $compressed['properties'] = $compressedProperties;
        }
        
        // Required fields behalten
        if (isset($schema['required']) && is_array($schema['required'])) {
            $compressed['required'] = $schema['required'];
        }
        
        return $compressed;
    }
}
