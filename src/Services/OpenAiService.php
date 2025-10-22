<?php

namespace Platform\Core\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Tools\CoreContextTool;
use Platform\Core\Tools\CoreDataReadTool;
use Platform\Core\Tools\ToolBroker;

class OpenAiService
{
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private string $baseUrl = 'https://api.openai.com/v1';

    private function getApiKey(): string
    {
        // Bevorzugt aus Config lesen (kompatibel mit config:cache); unterstütze beide Keys
        $key = config('services.openai.api_key');
        if (!is_string($key) || $key === '') {
            $key = config('services.openai.key') ?? '';
        }
        if ($key === '') {
            $key = env('OPENAI_API_KEY') ?? '';
        }

        if ($key === '') {
            // Klare, semantische Fehlermeldung statt TypeError durch null-Return
            throw new \RuntimeException('AUTHENTICATION_FAILED: OPENAI_API_KEY fehlt oder ist leer.');
        }

        return $key;
    }

    /**
     * Perform a non-streaming chat completion request.
     */
    public function chat(array $messages, string $model = self::DEFAULT_MODEL, array $options = []): array
    {
        $messagesWithContext = $this->buildMessagesWithContext($messages, $options);
        try {
            $payload = [
                'model' => $model,
                'messages' => $messagesWithContext,
                'max_tokens' => $options['max_tokens'] ?? 1000,
                'temperature' => $options['temperature'] ?? 0.7,
                'stream' => false,
            ];

            // Add data_read tool if not disabled
            if (!isset($options['tools']) || $options['tools'] !== false) {
                $payload['tools'] = $this->getAvailableTools();
                $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
            }

            $response = $this->http()->post($this->baseUrl . '/chat/completions', $payload);

            if ($response->failed()) {
                $this->logApiError('OpenAI API Error', $response->status(), $response->body());
                throw new \Exception($this->formatApiErrorMessage($response->status(), $response->body()));
            }

            $data = $response->json();
            
            return [
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'usage' => $data['usage'] ?? [],
                'model' => $data['model'] ?? $model,
                'tool_calls' => $data['choices'][0]['message']['tool_calls'] ?? null,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Perform a streaming chat completion. Calls $onDelta with each content token chunk.
     * Note: For true real-time UX, forward chunks via SSE/WebSockets to the client.
     */
    public function streamChat(array $messages, callable $onDelta, string $model = self::DEFAULT_MODEL, array $options = []): void
    {
        $messagesWithContext = $this->buildMessagesWithContext($messages, $options);
        $payload = [
            'model' => $model,
            'messages' => $messagesWithContext,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => true,
        ];

        // Add data_read tool if not disabled
        if (!isset($options['tools']) || $options['tools'] !== false) {
            $payload['tools'] = $this->getAvailableTools();
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        $response = $this->http(withStream: true)
            ->post($this->baseUrl . '/chat/completions', $payload);

        if ($response->failed()) {
            $this->logApiError('OpenAI API Error (stream)', $response->status(), $response->body());
            throw new \Exception($this->formatApiErrorMessage($response->status(), $response->body()));
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $toolCalls = [];
        $currentToolCall = null;
        $toolArguments = '';
        
        Log::info('[OpenAI Stream] Starting stream processing');
        
        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '' || $chunk === false) { 
                usleep(10000); 
                continue; 
            }
            
            Log::debug('[OpenAI Stream] Received chunk', ['size' => strlen($chunk)]);
            $buffer .= $chunk;

            // Normalisiere Zeilenenden auf \n
            $buffer = str_replace("\r\n", "\n", $buffer);
            $buffer = str_replace("\r", "\n", $buffer);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') { continue; }
                
                Log::debug('[OpenAI Stream] Processing line', ['line' => $line]);
                
                // Erwartet 'data:' Präfix je SSE-Zeile
                if (strncmp($line, 'data:', 5) !== 0) { 
                    Log::debug('[OpenAI Stream] Skipping non-data line', ['line' => $line]);
                    continue; 
                }
                
                $data = ltrim(substr($line, 5));
                Log::debug('[OpenAI Stream] Data content', ['data' => $data]);
                
                if ($data === '[DONE]') { 
                    Log::info('[OpenAI Stream] Stream completed');
                    return; 
                }

                $decoded = json_decode($data, true);
                if (!is_array($decoded)) { 
                    Log::debug('[OpenAI Stream] Invalid JSON', ['data' => $data]);
                    continue; 
                }
                
                Log::debug('[OpenAI Stream] Decoded data', ['decoded' => $decoded]);
                
                // Check for tool calls
                if (isset($decoded['choices'][0]['delta']['tool_calls'])) {
                    $toolCallDelta = $decoded['choices'][0]['delta']['tool_calls'];
                    Log::info('[OpenAI Stream] Tool call delta', ['tool_calls' => $toolCallDelta]);
                    
                    foreach ($toolCallDelta as $toolCall) {
                        if (isset($toolCall['function']['name'])) {
                            $currentToolCall = $toolCall['function']['name'];
                            $toolArguments = '';
                            Log::info('[OpenAI Stream] Starting tool call', ['tool' => $currentToolCall]);
                        }
                        
                        if (isset($toolCall['function']['arguments'])) {
                            $toolArguments .= $toolCall['function']['arguments'];
                            Log::debug('[OpenAI Stream] Tool arguments chunk', ['chunk' => $toolCall['function']['arguments']]);
                        }
                    }
                    continue;
                }
                
                // Check for tool call completion
                if (isset($decoded['choices'][0]['finish_reason']) && $decoded['choices'][0]['finish_reason'] === 'tool_calls') {
                    if ($currentToolCall && $toolArguments) {
                        Log::info('[OpenAI Stream] Executing tool call', [
                            'tool' => $currentToolCall, 
                            'arguments' => $toolArguments
                        ]);
                        
                        try {
                            $arguments = json_decode($toolArguments, true);
                            if ($arguments && $currentToolCall === 'data_read') {
                                $dataReadTool = app(\Platform\Core\Tools\CoreDataReadTool::class);
                                $result = $dataReadTool->handle($arguments);
                                
                                Log::info('[OpenAI Stream] Tool result', ['result' => $result]);
                                
                                // Build a concise natural-language summary via a follow-up non-streaming call
                                $lastUser = '';
                                foreach (array_reverse($messages) as $m) {
                                    if (($m['role'] ?? '') === 'user' && is_string($m['content'] ?? null)) {
                                        $lastUser = $m['content'];
                                        break;
                                    }
                                }

                                $summarySystem = 'Formuliere eine kurze, präzise, deutschsprachige Antwort für den Nutzer basierend auf dem folgenden Tool-Ergebnis. Gib eine verständliche Zusammenfassung (Anzahl, wichtigste Felder wie Titel/Fälligkeit), vermeide Roh-JSON und halte dich knapp.';
                                $summaryUser = (
                                    ($lastUser !== '' ? ("Frage: " . $lastUser . "\n\n") : '') .
                                    "Tool: " . $currentToolCall . "\nErgebnis (JSON):\n" . json_encode($result, JSON_UNESCAPED_UNICODE)
                                );

                                try {
                                    $summary = $this->chat([
                                        ['role' => 'system', 'content' => $summarySystem],
                                        ['role' => 'user', 'content' => $summaryUser],
                                    ], model: self::DEFAULT_MODEL, options: [
                                        'with_context' => false,
                                        'tools' => false,
                                        'max_tokens' => 300,
                                        'temperature' => 0.2,
                                    ]);

                                    $text = $summary['content'] ?? '';
                                    if ($text !== '') {
                                        $onDelta("\n" . $text . "\n");
                                    }
                                } catch (\Throwable $e) {
                                    Log::error('[OpenAI Stream] Summary generation failed', ['error' => $e->getMessage()]);
                                    // Fallback: sehr knappe Textausgabe
                                    $fallback = $result['ok'] ? 'Ergebnis wurde ermittelt.' : ('Fehler: ' . ($result['error']['message'] ?? 'Unbekannt'));
                                    $onDelta("\n" . $fallback . "\n");
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error('[OpenAI Stream] Tool execution failed', ['error' => $e->getMessage()]);
                            $onDelta("\n\n**Tool-Fehler:** " . $e->getMessage() . "\n");
                        }
                    }
                    
                    $currentToolCall = null;
                    $toolArguments = '';
                    continue;
                }
                
                $delta = $decoded['choices'][0]['delta']['content'] ?? '';
                if ($delta !== '') { 
                    Log::debug('[OpenAI Stream] Content delta', ['delta' => $delta]);
                    $onDelta($delta); 
                }
            }
        }
        
        Log::info('[OpenAI Stream] Stream ended');
    }

    /** Build a robust HTTP client with consistent headers, timeouts, and retry. */
    private function http(bool $withStream = false): PendingRequest
    {
        $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
                'User-Agent' => 'Glowkit-Core/1.0 (+Laravel)'
            ])
            ->timeout(20)
            ->connectTimeout(5)
            ->retry(1, random_int(250, 500), function ($exception, $request) {
                return $exception instanceof ConnectionException;
            });

        if ($withStream) {
            $request = $request->withOptions(['stream' => true]);
        }

        return $request;
    }

    private function buildMessagesWithContext(array $messages, array $options): array
    {
        $withContext = $options['with_context'] ?? true;
        if (!$withContext) {
            return $messages;
        }

        try {
            $context = app(CoreContextTool::class)->getContext();
            // Fallback/Override: nutze explizite Route/Modul/URL falls vom Aufrufer mitgegeben
            if (!empty($options['source_route'])) { $context['data']['route'] = $options['source_route']; }
            if (!empty($options['source_module'])) { $context['data']['module'] = $options['source_module']; }
            if (!empty($options['source_url'])) { $context['data']['url'] = $options['source_url']; }
            $prompt = $context['data']['system_prompt'] ?? 'Antworte kurz, präzise und auf Deutsch.';
            $u = $context['data']['user'] ?? null;
            $t = $context['data']['team'] ?? null;
            $module = $context['data']['module'] ?? null;
            $route = $context['data']['route'] ?? null;
            $url = $context['data']['url'] ?? null;
            $time = $context['data']['current_time'] ?? null;
            $tz = $context['data']['timezone'] ?? null;
            $naturalCtx = trim(implode(' ', array_filter([
                $u ? 'Nutzer: ' . ($u['name'] ?? ('#'.$u['id'])) : null,
                $t ? 'Team: ' . ($t['name'] ?? ('#'.$t['id'])) : null,
                $module ? 'Modul: ' . $module : null,
                $route ? 'Route: ' . $route : null,
                $url ? 'URL: ' . $url : null,
                ($time && $tz) ? ('Zeit: ' . $time . ' ' . $tz) : null,
            ])));

            array_unshift($messages, [
                'role' => 'system',
                'content' => $prompt . ($naturalCtx !== '' ? (' ' . $naturalCtx) : ''),
            ]);
        } catch (\Throwable $e) {
            // Fallback: ohne Kontext weiter
        }

        return $messages;
    }

    private function logApiError(string $message, int $status, string $body): void
    {
        Log::error($message, [ 'status' => $status, 'body' => $body ]);
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

            if ($response->failed()) {
                throw new \Exception($this->formatApiErrorMessage($response->status(), $response->body()));
            }

            return $response->json()['data'] ?? [];

        } catch (\Exception $e) {
            Log::error('OpenAI Models Error', [
                'message' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    private function getAvailableTools(): array
    {
        $toolBroker = app(ToolBroker::class);
        $capabilities = $toolBroker->getAvailableCapabilities();
        
        Log::info('[OpenAI Tools] Available capabilities', ['capabilities' => $capabilities]);
        
        $tools = [];
        foreach ($capabilities['available_entities'] as $entity) {
            foreach ($capabilities['available_operations'] as $operation) {
                $toolDef = $toolBroker->getToolDefinition($entity, $operation);
                if ($toolDef) {
                    $tools[] = $toolDef;
                    Log::debug('[OpenAI Tools] Added tool definition', ['entity' => $entity, 'operation' => $operation]);
                }
            }
        }
        
        Log::info('[OpenAI Tools] Final tools array', ['tools' => $tools]);
        return $tools;
    }
}
