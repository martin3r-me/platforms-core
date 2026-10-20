<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\LLMProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Provider Implementation
 * 
 * Wrapper um OpenAiService für LLMProviderContract
 */
class OpenAiProvider implements LLMProviderContract
{
    public const DEFAULT_MODEL = 'gpt-5.5';

    public function __construct(
        private OpenAiService $openAiService
    ) {}

    public function getName(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? self::DEFAULT_MODEL;

        // OpenAI erwartet system als inline-Message mit role=system; das LLMProviderContract
        // erlaubt aber auch options['system'] (so wie Anthropic). Wir mappen hier um.
        if (isset($options['system']) && is_string($options['system']) && $options['system'] !== '') {
            $messages = array_merge(
                [['role' => 'system', 'content' => $options['system']]],
                $messages,
            );
        }

        // Structured Outputs (json_schema/strict) laufen über die Chat-Completions-API —
        // OpenAiService nutzt die Responses-API und lässt response_format fallen. Nur
        // dann weichen wir auf den direkten Call aus; alles andere bleibt wie gehabt.
        if (isset($options['json_schema']) && is_array($options['json_schema'])) {
            return $this->chatStructured($messages, $model, $options);
        }

        $result = $this->openAiService->chat($messages, $model, $options);

        return [
            'content' => $result['content'] ?? '',
            'usage' => $result['usage'] ?? [],
            'model' => $result['model'] ?? $model,
            'tool_calls' => $result['tool_calls'] ?? null,
            'sources' => $result['sources'] ?? [],
            'web_search_calls' => $result['web_search_calls'] ?? [],
        ];
    }

    /**
     * Direkter Chat-Completions-Call mit erzwungenem JSON-Schema (Structured
     * Outputs). Nur für den Enrichment-Fall; die Responses-API des OpenAiService
     * kann response_format nicht sauber abbilden.
     *
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $options
     * @return array{content: string, usage: array, model: string, tool_calls: ?array, sources: array}
     */
    protected function chatStructured(array $messages, string $model, array $options): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAiProvider: services.openai.api_key ist nicht gesetzt.');
        }

        $body = [
            'model' => $model,
            'messages' => array_values($messages),
            'temperature' => $options['temperature'] ?? 0.2,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => preg_replace('/[^A-Za-z0-9_]+/', '_', (string) ($options['json_schema_name'] ?? 'enrichment')),
                    'schema' => $this->adaptSchemaForStrict($options['json_schema']),
                    'strict' => true,
                ],
            ],
        ];
        if (isset($options['max_tokens'])) {
            $body['max_tokens'] = $options['max_tokens'];
        }

        $response = Http::withToken($apiKey)->asJson()->acceptJson()
            ->timeout((int) ($options['timeout'] ?? 60))
            ->post('https://api.openai.com/v1/chat/completions', $body);

        if (! $response->successful()) {
            Log::warning('OpenAiProvider structured chat failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'model' => $model,
            ]);
            throw new \RuntimeException(
                'OpenAI API error ' . $response->status() . ': ' . mb_substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();

        return [
            'content' => (string) ($data['choices'][0]['message']['content'] ?? ''),
            'usage' => $data['usage'] ?? [],
            'model' => $data['model'] ?? $model,
            'tool_calls' => $data['choices'][0]['message']['tool_calls'] ?? null,
            'sources' => [],
        ];
    }

    /**
     * Macht ein JSON-Schema strict-Mode-konform für OpenAI Structured Outputs:
     * additionalProperties:false und alle Properties required an jeder Objektebene;
     * optionale Felder werden nullable gewidet. Rekursiv über properties/items.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    protected function adaptSchemaForStrict(array $schema): array
    {
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['additionalProperties'] = false;

            $allProps = array_keys($schema['properties']);
            $required = $schema['required'] ?? [];
            $optional = array_values(array_diff($allProps, $required));

            foreach ($optional as $prop) {
                $propType = $schema['properties'][$prop]['type'] ?? null;
                if (is_string($propType) && $propType !== 'null') {
                    $schema['properties'][$prop]['type'] = [$propType, 'null'];
                } elseif (is_array($propType) && !in_array('null', $propType, true)) {
                    $schema['properties'][$prop]['type'] = array_merge($propType, ['null']);
                }
            }
            $schema['required'] = $allProps;

            foreach ($schema['properties'] as $k => $v) {
                if (is_array($v)) {
                    $schema['properties'][$k] = $this->adaptSchemaForStrict($v);
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->adaptSchemaForStrict($schema['items']);
        }

        return $schema;
    }

    public function streamChat(array $messages, callable $onDelta, array $options = []): void
    {
        $model = $options['model'] ?? self::DEFAULT_MODEL;
        $this->openAiService->streamChat($messages, $onDelta, $model, $options);
    }

    public function getAvailableModels(): array
    {
        return [
            'gpt-5.5',
            'gpt-5.2',
            'gpt-5.2-chat-latest',
            'gpt-5.2-pro',
            'gpt-4o-2024-08-06',
            'gpt-4o-mini-2024-07-18',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
        ];
    }

    public function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    public function isAvailable(): bool
    {
        // Prüfe ob API-Key konfiguriert ist
        $apiKey = config('services.openai.api_key');
        return !empty($apiKey);
    }
}

