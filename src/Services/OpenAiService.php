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
            if (!isset($options['tools']) || $options['tools'] !== false) {
                $tools = $this->getAvailableTools();
                $payload['tools'] = $this->normalizeToolsForResponses($tools);
                $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
            }
            $response = $this->http()->post($this->baseUrl . '/responses', $payload);
            if ($response->failed()) {
                $this->logApiError('OpenAI API Error (responses)', $response->status(), $response->body());
                throw new \Exception($this->formatApiErrorMessage($response->status(), $response->body()));
            }
            $data = $response->json();
            $content = $data['output_text'] ?? ($data['content'][0]['text'] ?? '');
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
        if (!isset($options['tools']) || $options['tools'] !== false) {
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
        Log::info('[OpenAI Responses Stream] Starting');
        while (!$body->eof()) {
            $chunk = $body->read(8192); if ($chunk === '' || $chunk === false) { usleep(10000); continue; }
            $buffer .= str_replace(["\r\n","\r"], "\n", $chunk);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos); $buffer = substr($buffer, $pos + 1);
                if ($line === '') { continue; }
                if (strncmp($line, 'event:', 6) === 0) { $currentEvent = trim(substr($line, 6)); continue; }
                if (strncmp($line, 'data:', 5) !== 0) { continue; }
                $data = ltrim(substr($line, 5)); if ($data === '[DONE]') { return; }
                $decoded = json_decode($data, true); if (!is_array($decoded)) { continue; }
                switch ($currentEvent) {
                    case 'response.output_text.delta':
                        $delta = $decoded['delta'] ?? ($decoded['text'] ?? ''); if ($delta !== '') { $onDelta($delta); }
                        break;
                    case 'response.tool_call.created':
                        $currentToolCall = $decoded['name'] ?? ($decoded['tool_name'] ?? null);
                        if ($currentToolCall && is_callable($onToolStart)) { try { $onToolStart($currentToolCall); } catch (\Throwable $e) {} }
                        break;
                    case 'response.tool_call.delta':
                        $toolArguments .= $decoded['arguments_delta'] ?? ($decoded['arguments'] ?? '');
                        break;
                    case 'response.tool_call.completed':
                        $this->executeToolIfReady($currentToolCall, $toolArguments, $toolExecutor, $onDelta, $messages);
                        $currentToolCall = null; $toolArguments = '';
                        break;
                    case 'response.completed':
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
            array_unshift($messages, [ 'role' => 'system', 'content' => $prompt . ($naturalCtx !== '' ? (' ' . $naturalCtx) : '') ]);
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
        $toolBroker = app(ToolBroker::class);
        $toolRegistry = app(ToolRegistry::class);
        
        $capabilities = $toolBroker->getAvailableCapabilities();
        Log::info('[OpenAI Tools] Available capabilities', ['capabilities' => $capabilities]);
        
        $tools = [];
        
        // 1. Entity-basierte Tools aus ToolBroker (bestehende Logik)
        foreach ($capabilities['available_entities'] as $entity) {
            foreach ($capabilities['available_operations'] as $operation) {
                $toolDef = $toolBroker->getToolDefinition($entity, $operation);
                if ($toolDef) { 
                    $tools[] = $toolDef; 
                    Log::debug('[OpenAI Tools] Added tool definition', ['entity' => $entity, 'operation' => $operation]); 
                }
            }
        }
        $tools[] = $toolBroker->getWriteToolDefinition();
        
        // 2. Tools aus ToolRegistry hinzufügen
        foreach ($toolRegistry->all() as $tool) {
            $toolDef = $this->convertToolToOpenAiFormat($tool);
            if ($toolDef) {
                $tools[] = $toolDef;
                Log::debug('[OpenAI Tools] Added tool from registry', ['tool' => $tool->getName()]);
            }
        }
        
        Log::info('[OpenAI Tools] Final tools array', ['tools' => $tools, 'count' => count($tools)]);
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
