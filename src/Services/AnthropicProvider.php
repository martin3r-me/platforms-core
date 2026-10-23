<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\LLMProviderContract;

/**
 * Anthropic Claude Provider — direkter HTTP-Wrapper auf die Messages-API.
 *
 * Bewusst schlank: keine Tool-Loop (das macht ClaudeToolLoopRunner),
 * nur ein chat()-Call fuer reine Text-Generierung. Streaming V1 noch nicht.
 */
class AnthropicProvider implements LLMProviderContract
{
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';
    protected const BASE_URL = 'https://api.anthropic.com/v1';
    protected const API_VERSION = '2023-06-01';
    protected const DEFAULT_MAX_TOKENS = 4096;
    protected const TIMEOUT_SECONDS = 60;

    public function getName(): string
    {
        return 'anthropic';
    }

    public function chat(array $messages, array $options = []): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('AnthropicProvider: ANTHROPIC_API_KEY ist nicht gesetzt.');
        }

        $model = $options['model'] ?? $this->getDefaultModel();
        $maxTokens = $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS;

        // Anthropic Messages-API trennt system aus messages heraus.
        [$system, $cleanMessages] = $this->splitSystem($messages);
        if (isset($options['system']) && $options['system'] !== '') {
            $system = trim(($system ?? '') . "\n\n" . $options['system']);
        }

        // Structured Output: Anthropic kennt kein response_format. Wir hängen das
        // JSON-Schema als System-Instruktion an (best effort) — derselbe Port wie
        // bei OpenAI/Local, nur im Anthropic-Dialekt.
        if (isset($options['json_schema']) && is_array($options['json_schema'])) {
            $schemaJson = json_encode(
                $options['json_schema'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            $system = trim(($system ?? '')
                . "\n\nDeine Antwort MUSS ein einzelnes gültiges JSON-Objekt sein, das exakt "
                . "diesem JSON-Schema entspricht (keine Zusatzfelder, keine Markdown-Fences):\n"
                . $schemaJson);
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $cleanMessages,
        ];
        if ($system) {
            $payload['system'] = $system;
        }
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders([
                'x-api-key' => config('ai.anthropic.api_key'),
                'anthropic-version' => self::API_VERSION,
                'content-type' => 'application/json',
            ])
            ->post(self::BASE_URL . '/messages', $payload);

        if (! $response->successful()) {
            Log::warning('AnthropicProvider chat failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException(
                'Anthropic API error ' . $response->status() . ': ' . $response->body()
            );
        }

        $body = $response->json();
        $text = collect($body['content'] ?? [])
            ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
            ->pluck('text')
            ->implode('');

        return [
            'content' => $text,
            'usage' => $body['usage'] ?? [],
            'model' => $body['model'] ?? $model,
            'tool_calls' => null,
            'sources' => [], // include_web_search wird von diesem Provider ignoriert
        ];
    }

    public function streamChat(array $messages, callable $onDelta, array $options = []): void
    {
        // V1: nicht implementiert. Verbalizer braucht es nicht.
        throw new \BadMethodCallException('AnthropicProvider::streamChat ist in V1 nicht implementiert.');
    }

    /**
     * Roh-Transport auf die Anthropic Messages-API.
     *
     * Nimmt einen bereits fertig gebauten Payload (model/messages/system/tools/
     * thinking/output_config …) und gibt den dekodierten Response-Body 1:1 zurück.
     * Das ist die EINZIGE Stelle, die Key, Endpoint, API-Version und Retry besitzt —
     * der agentische {@see ClaudeToolLoopRunner} baut nur noch Payload + Loop und
     * ruft hierüber, statt selbst HTTP an api.anthropic.com zu machen.
     *
     * Bewusst NICHT im LLMProviderContract: die native Tool-Use-/Content-Block-Form
     * ist Anthropic-spezifisch; ein geteiltes Interface dafür wäre Überabstraktion
     * (heute hat nur dieser Provider einen Tool-Loop-Konsumenten).
     *
     * @param array<string, mixed> $payload  Vollständiger Messages-API-Payload.
     * @param array{timeout?: int, max_retries?: int} $options
     * @return array<string, mixed>  Dekodierter Response-Body (content/usage/stop_reason …).
     */
    public function messages(array $payload, array $options = []): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('AnthropicProvider: ANTHROPIC_API_KEY ist nicht gesetzt.');
        }

        $timeout = (int) ($options['timeout'] ?? self::TIMEOUT_SECONDS);
        $maxRetries = max(1, (int) ($options['max_retries'] ?? 3));

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'x-api-key' => config('ai.anthropic.api_key'),
                    'anthropic-version' => self::API_VERSION,
                    'content-type' => 'application/json',
                ])
                ->post(self::BASE_URL . '/messages', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            $status = $response->status();
            $body = $response->json();
            $errorMessage = $body['error']['message'] ?? $response->body();
            $errorType = $body['error']['type'] ?? 'api_error';

            // Rate limit (429) / overloaded (529) → exponentielles Backoff.
            if (in_array($status, [429, 529], true) && $attempt < $maxRetries) {
                $retryAfter = (int) ($response->header('retry-after') ?: 0);
                $waitSeconds = max($retryAfter, $attempt * 30);

                Log::warning('AnthropicProvider messages rate limited, retrying', [
                    'status' => $status,
                    'attempt' => $attempt,
                    'wait_seconds' => $waitSeconds,
                ]);

                sleep($waitSeconds);

                continue;
            }

            Log::warning('AnthropicProvider messages failed', [
                'status' => $status,
                'error_type' => $errorType,
                'error_message' => mb_substr((string) $errorMessage, 0, 500),
                'attempt' => $attempt,
            ]);

            throw new \RuntimeException(
                "Anthropic API error ({$status}): [{$errorType}] {$errorMessage}"
            );
        }

        throw new \RuntimeException('Anthropic API: max retries exhausted');
    }

    public function getAvailableModels(): array
    {
        return [
            'claude-opus-4-7',
            'claude-sonnet-4-6',
            'claude-haiku-4-5',
            'claude-sonnet-4-5',
            'claude-opus-4-5',
        ];
    }

    public function getDefaultModel(): string
    {
        return config('ai.anthropic.inference_model', self::DEFAULT_MODEL);
    }

    public function isAvailable(): bool
    {
        return ! empty(config('ai.anthropic.api_key'));
    }

    /**
     * Anthropic erwartet system als separates Top-Level-Feld; aus messages rausziehen.
     *
     * @return array{0: ?string, 1: array}
     */
    protected function splitSystem(array $messages): array
    {
        $system = null;
        $clean = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? null) === 'system') {
                $system = trim(($system ?? '') . "\n\n" . ($msg['content'] ?? ''));
                continue;
            }
            $clean[] = $msg;
        }
        return [$system, $clean];
    }
}
