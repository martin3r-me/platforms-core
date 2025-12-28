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
                $payload['tools'] = $this->normalizeToolsForResponses($tools);
                $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
            }
            
            // Debug: Log Payload
            Log::debug('[OpenAI Chat] Sending request', [
                'url' => $this->baseUrl . '/responses',
                'payload_keys' => array_keys($payload),
                'input_count' => count($payload['input'] ?? []),
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
                $this->logApiError('OpenAI API Error (responses)', $response->status(), $response->body());
                Log::error('[OpenAI Chat] Request failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                throw new \Exception($this->formatApiErrorMessage($response->status(), $response->body()));
            }
            $data = $response->json();
            
            // Debug: Log Response Data - zeige vollständige Response
            Log::debug('[OpenAI Chat] Response data', [
                'has_output_text' => isset($data['output_text']),
                'has_content' => isset($data['content']),
                'content_type' => isset($data['content']) ? gettype($data['content']) : 'not set',
                'has_usage' => isset($data['usage']),
                'keys' => array_keys($data),
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
            
            // Format 1: output[0].content[0].text (Responses API Format)
            if (isset($data['output']) && is_array($data['output']) && isset($data['output'][0])) {
                $outputItem = $data['output'][0];
                if (isset($outputItem['content']) && is_array($outputItem['content']) && isset($outputItem['content'][0])) {
                    $contentItem = $outputItem['content'][0];
                    if (isset($contentItem['text']) && is_string($contentItem['text'])) {
                        $content = $contentItem['text'];
                    } elseif (isset($contentItem['type']) && $contentItem['type'] === 'output_text' && isset($contentItem['text'])) {
                        $content = $contentItem['text'];
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
            
            // Fallback: Wenn content leer, aber output_tokens > 0, dann ist was schiefgelaufen
            if ($content === '' && isset($data['usage']['output_tokens']) && $data['usage']['output_tokens'] > 0) {
                Log::warning('[OpenAI Chat] Content ist leer, aber output_tokens > 0', [
                    'data' => $data,
                ]);
            }
            return [
                'content' => $content,
                'usage' => $data['usage'] ?? [],
                'model' => $data['model'] ?? $model,
                'tool_calls' => $data['tool_calls'] ?? null,
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
        }
        $response = $this->http(withStream: true)->post($this->baseUrl . '/responses', $payload);
        if ($response->failed()) {
            $this->logApiError('OpenAI API Error (responses stream)', $response->status(), $response->body());
            throw new \Exception($this->formatApiErrorMessage($response->status(), $response->body()));
        }
        $this->parseResponsesStream($response->toPsrResponse()->getBody(), $onDelta, $messages, $options);
    }

    private function normalizeToolsForResponses(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            if (isset($tool['function']) && is_array($tool['function'])) {
                $fn = $tool['function'];
                $out[] = [
                    'type' => 'function',
                    'name' => $fn['name'] ?? null,
                    'description' => $fn['description'] ?? ($tool['description'] ?? null),
                    'parameters' => $fn['parameters'] ?? null,
                ];
            } else {
                $out[] = $tool;
            }
        }
        return $out;
    }

    private function parseResponsesStream($body, callable $onDelta, array $messages, array $options): void
    {
        $buffer = '';
        $currentEvent = null; $currentToolCall = null; $toolArguments = '';
        $onToolStart = $options['on_tool_start'] ?? null; $toolExecutor = $options['tool_executor'] ?? null;
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
                    case 'response.tool_call.created':
                    case 'tool_call.created':
                        $currentToolCall = $decoded['name'] ?? ($decoded['tool_name'] ?? null);
                        if ($currentToolCall && is_callable($onToolStart)) { try { $onToolStart($currentToolCall); } catch (\Throwable $e) {} }
                        break;
                    case 'response.tool_call.delta':
                    case 'tool_call.delta':
                        $toolArguments .= $decoded['arguments_delta'] ?? ($decoded['arguments'] ?? '');
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
            $arguments = json_decode($toolArguments, true);
            $result = null;
            if ($arguments && is_callable($toolExecutor)) { try { $result = $toolExecutor($toolName, $arguments); } catch (\Throwable $e) { Log::error('tool_executor failed: '.$e->getMessage()); } }
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
            $prompt = $context['data']['system_prompt'] ?? 'Antworte kurz, präzise und auf Deutsch.';
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
        foreach ($messages as $m) {
            $text = is_array($m['content'] ?? null) ? json_encode($m['content']) : ($m['content'] ?? '');
            $input[] = [ 'role' => $m['role'] ?? 'user', 'content' => $text ];
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
            
            // Wichtiger Hinweis
            $info .= "Tipp: Nutze das Tool 'tools.list', um alle verfügbaren Tools und ihre Funktionen zu sehen.\n";
            $info .= "Beispiel: 'Welche Tools stehen mir zur Verfügung?' oder 'Zeige mir alle Planner-Tools'.\n";
            
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
                
                // Konvertiere alle registrierten Tools zu OpenAI-Format
                foreach ($allTools as $tool) {
                    try {
                        $toolDef = $this->convertToolToOpenAiFormat($tool);
                        if ($toolDef) {
                            $tools[] = $toolDef;
                        }
                    } catch (\Throwable $e) {
                        // Einzelnes Tool-Fehler - überspringen
                    }
                }
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
    private function convertToolToOpenAiFormat(\Platform\Core\Contracts\ToolContract $tool): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getSchema(),
            ]
        ];
    }
}
