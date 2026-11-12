<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\LLMProviderContract;

/**
 * Anthropic Claude Provider — direkter HTTP-Wrapper auf die Messages-API.
 *
 * Bewusst schlank: keine Tool-Loop (das macht ClaudeToolLoopRunner),
 * nur ein chat()-Call fuer Text-Generierung (optional mit serverseitiger
 * Websuche). Streaming V1 noch nicht.
 */
class AnthropicProvider implements LLMProviderContract
{
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';
    public const FAST_MODEL = 'claude-haiku-4-5-20251001';
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

        $model = $this->resolveModel($options['model'] ?? null);
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

        // Aktuelle Modellgeneration denkt adaptiv — das Denken teilt sich max_tokens
        // mit der Antwort. Bei knappem Budget bleibt sonst kein Text übrig.
        if (preg_match('/(sonnet-5|opus-4-7|opus-4-8|opus-5|fable-5|mythos-5)/', $model)) {
            $maxTokens = max($maxTokens, 8192);
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $cleanMessages,
        ];
        if ($system) {
            $payload['system'] = $system;
        }
        // Aktuelle Modellgeneration lehnt temperature mit HTTP 400 ab — dort weglassen
        // (gleiche Regel wie ClaudeToolLoopRunner::modelAcceptsSampling()).
        if (isset($options['temperature'])
            && ! preg_match('/(sonnet-5|opus-4-7|opus-4-8|opus-5|fable-5|mythos-5)/', $model)) {
            $payload['temperature'] = $options['temperature'];
        }

        // Denktiefe (output_config.effort) — nur auf Modellen, die es kennen
        // (gleiche Liste wie ClaudeToolLoopRunner::modelSupportsEffort()).
        if (isset($options['effort'])
            && preg_match('/(sonnet-5|sonnet-4-6|opus-4-5|opus-4-6|opus-4-7|opus-4-8|fable-5|mythos-5)/', $model)) {
            $payload['output_config'] = ['effort' => $options['effort']];
        }

        // Websuche als serverseitiges Tool (Anthropic führt die Suche selbst aus).
        $webSearch = (bool) ($options['web_search'] ?? $options['include_web_search'] ?? false);
        if ($webSearch) {
            $payload['tools'] = [self::webSearchTool((int) ($options['web_search_max_uses'] ?? 5))];
        }

        $body = $this->messages($payload, [
            'timeout' => (int) ($options['timeout'] ?? self::TIMEOUT_SECONDS),
            'max_retries' => 2,
        ]);
        $content = $body['content'] ?? [];

        // Lange Server-Tool-Läufe (Websuche) pausieren mit pause_turn — dann fortsetzen.
        for ($i = 0; $i < 3 && ($body['stop_reason'] ?? null) === 'pause_turn'; $i++) {
            $payload['messages'][] = ['role' => 'assistant', 'content' => $body['content'] ?? []];
            $body = $this->messages($payload, ['timeout' => (int) ($options['timeout'] ?? self::TIMEOUT_SECONDS), 'max_retries' => 2]);
            $content = array_merge($content, $body['content'] ?? []);
        }

        $text = collect($content)
            ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
            ->pluck('text')
            ->implode('');

        if (trim($text) === '') {
            Log::warning('AnthropicProvider chat returned no text', [
                'model' => $body['model'] ?? $model,
                'stop_reason' => $body['stop_reason'] ?? null,
                'usage' => $body['usage'] ?? [],
            ]);
        }

        return [
            'content' => $text,
            'usage' => $body['usage'] ?? [],
            'model' => $body['model'] ?? $model,
            'tool_calls' => null,
            'sources' => $webSearch ? $this->extractSources($content) : [],
        ];
    }

    /** Serverseitiges Websuche-Tool der Messages-API. */
    public static function webSearchTool(int $maxUses = 5): array
    {
        return ['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => max(1, $maxUses)];
    }

    /**
     * Modell auflösen: 'fast' = kleines Modell (ai.anthropic.fast_model), Claude-IDs
     * unverändert, alles andere (z. B. alte OpenAI-IDs aus Konfiguration/DB) → Default.
     */
    public function resolveModel(?string $model): string
    {
        if ($model === 'fast') {
            return (string) config('ai.anthropic.fast_model', self::FAST_MODEL);
        }
        if (is_string($model) && str_starts_with($model, 'claude-')) {
            return $model;
        }

        return $this->getDefaultModel();
    }

    /** @return array<int, array{url:string,title:?string}> */
    protected function extractSources(array $content): array
    {
        $sources = [];
        foreach ($content as $block) {
            if (($block['type'] ?? null) !== 'web_search_tool_result' || ! is_array($block['content'] ?? null)) {
                continue;
            }
            foreach ($block['content'] as $r) {
                if (! empty($r['url'])) {
                    $sources[$r['url']] = ['url' => $r['url'], 'title' => $r['title'] ?? null];
                }
            }
        }

        return array_values($sources);
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
