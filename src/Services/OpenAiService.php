<?php

namespace Platform\Core\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    private const DEFAULT_MODEL = 'gpt-3.5-turbo';
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
        
        try {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $options['max_tokens'] ?? 1000,
                'temperature' => $options['temperature'] ?? 0.7,
                'stream' => false,
            ];

            if (!empty($options['tools'])) {
                $payload['tools'] = $options['tools'];
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
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'stream' => true,
        ];

        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
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
        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '' || $chunk === false) { usleep(10000); continue; }
            $buffer .= $chunk;

            // Normalisiere Zeilenenden auf \n
            $buffer = str_replace("\r\n", "\n", $buffer);
            $buffer = str_replace("\r", "\n", $buffer);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') { continue; }
                // Erwartet 'data:' Präfix je SSE-Zeile
                if (strncmp($line, 'data:', 5) !== 0) { continue; }
                $data = ltrim(substr($line, 5));
                if ($data === '[DONE]') { return; }

                $decoded = json_decode($data, true);
                if (!is_array($decoded)) { continue; }
                $delta = $decoded['choices'][0]['delta']['content'] ?? '';
                if ($delta !== '') { $onDelta($delta); }
            }
        }
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
                if ($exception instanceof ConnectionException) { return true; }
                $status = optional($request->response())->status();
                return in_array($status, [429, 500, 502, 503, 504], true);
            });

        if ($withStream) {
            $request = $request->withOptions(['stream' => true]);
        }

        return $request;
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
}
