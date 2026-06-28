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
        ];
    }

    public function streamChat(array $messages, callable $onDelta, array $options = []): void
    {
        // V1: nicht implementiert. Verbalizer braucht es nicht.
        throw new \BadMethodCallException('AnthropicProvider::streamChat ist in V1 nicht implementiert.');
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
