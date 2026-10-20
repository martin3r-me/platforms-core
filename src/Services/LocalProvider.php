<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\LLMProviderContract;

/**
 * Lokaler / selbst gehosteter LLM-Provider über einen OpenAI-kompatiblen
 * Endpunkt (Ollama, vLLM, llama.cpp, LM Studio …). Unabhängig von externen
 * Anbietern — gedacht für eng umrissene Transformationen (Enrichment,
 * Klassifikation, Triage), bei denen kleine/offene Modelle genügen.
 *
 * Die Engine läuft als EIGENER Dienst; dieser Adapter spricht sie nur per HTTP
 * an (/v1/chat/completions). Backend/Modell wechselt man allein über die
 * Config-URL — kein Code-Change. Streaming/Tools sind bewusst (noch) nicht
 * implementiert; für die Transform-Jobs nicht nötig.
 *
 * Config (core/config/ai.php → ai.local.*):
 *   base_url  …/v1 des Servers (leer = Provider nicht verfügbar)
 *   api_key   optional (vLLM); Ollama braucht keinen
 *   model     Default-Modell-ID
 *   timeout   Sekunden
 */
class LocalProvider implements LLMProviderContract
{
    protected const DEFAULT_MAX_TOKENS = 4096;

    public function getName(): string
    {
        return 'local';
    }

    public function chat(array $messages, array $options = []): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('LocalProvider: ai.local.base_url ist nicht gesetzt.');
        }

        $model = $options['model'] ?? $this->getDefaultModel();
        $maxTokens = $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS;

        // system aus options wie bei den anderen Providern als führende
        // system-Message einhängen (OpenAI-Chat-Format).
        if (isset($options['system']) && is_string($options['system']) && $options['system'] !== '') {
            $messages = array_merge(
                [['role' => 'system', 'content' => $options['system']]],
                $messages,
            );
        }

        $payload = [
            'model' => $model,
            'messages' => array_values($messages),
            'max_tokens' => $maxTokens,
        ];
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        // JSON-/Schema-Zwang nur durchreichen, wenn der Aufrufer ihn setzt
        // (vLLM: response_format/guided_json; Ollama: format). Backend-spezifisch
        // — daher weiterreichen, nicht erzwingen.
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $request = Http::timeout((int) config('ai.local.timeout', 120))->asJson()->acceptJson();
        $apiKey = (string) config('ai.local.api_key', '');
        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->post($this->endpoint('/chat/completions'), $payload);

        if (! $response->successful()) {
            Log::warning('LocalProvider chat failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'model' => $model,
            ]);
            throw new \RuntimeException(
                'Local LLM API error ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300)
            );
        }

        $body = $response->json();
        $text = (string) ($body['choices'][0]['message']['content'] ?? '');

        return [
            'content' => $text,
            'usage' => $body['usage'] ?? [],
            'model' => $body['model'] ?? $model,
            'tool_calls' => $body['choices'][0]['message']['tool_calls'] ?? null,
            'sources' => [],
        ];
    }

    public function streamChat(array $messages, callable $onDelta, array $options = []): void
    {
        throw new \BadMethodCallException('LocalProvider::streamChat ist noch nicht implementiert.');
    }

    public function getAvailableModels(): array
    {
        $model = $this->getDefaultModel();
        return $model !== '' ? [$model] : [];
    }

    public function getDefaultModel(): string
    {
        return (string) config('ai.local.model', '');
    }

    public function isAvailable(): bool
    {
        return ! empty(config('ai.local.base_url'));
    }

    protected function endpoint(string $path): string
    {
        $base = rtrim((string) config('ai.local.base_url'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}
