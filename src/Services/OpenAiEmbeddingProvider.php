<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\EmbeddingProviderContract;
use RuntimeException;

/**
 * OpenAI Embedding Provider
 *
 * Ruft den /v1/embeddings-Endpunkt direkt via Http:: auf — bewusst orthogonal
 * zur Chat-Logik in OpenAiService (die nur die Responses-API kennt).
 *
 * OpenAI text-embedding-3-large liefert NICHT L2-normalisiert.
 */
class OpenAiEmbeddingProvider implements EmbeddingProviderContract
{
    public const DEFAULT_MODEL = 'text-embedding-3-large';
    public const DEFAULT_DIMENSIONS = 3072;

    private const ENDPOINT = 'https://api.openai.com/v1/embeddings';
    private const MAX_BATCH = 2048;

    public function __construct(
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly int $dimensions = self::DEFAULT_DIMENSIONS,
    ) {}

    public function getName(): string
    {
        return 'openai';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    public function isNormalized(): bool
    {
        return false;
    }

    public function getMaxBatchSize(): int
    {
        return self::MAX_BATCH;
    }

    public function embed(array $texts, string $type = 'document'): array
    {
        if (count($texts) === 0) {
            return [];
        }

        $apiKey = $this->resolveApiKey();

        $payload = [
            'model' => $this->model,
            'input' => array_values($texts),
        ];

        if ($this->dimensions !== self::DEFAULT_DIMENSIONS) {
            $payload['dimensions'] = $this->dimensions;
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post(self::ENDPOINT, $payload);

        if ($response->failed()) {
            Log::error('[OpenAiEmbeddingProvider] Request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 1000),
                'model' => $this->model,
                'input_count' => count($texts),
            ]);
            throw new RuntimeException(
                "OpenAI embeddings request failed: HTTP {$response->status()} — "
                . substr($response->body(), 0, 500)
            );
        }

        $data = $response->json('data');
        if (!is_array($data)) {
            throw new RuntimeException('OpenAI embeddings response missing "data" array.');
        }

        $vectors = [];
        foreach ($data as $item) {
            if (!isset($item['embedding']) || !is_array($item['embedding'])) {
                throw new RuntimeException('OpenAI embeddings response item missing "embedding" array.');
            }
            $vectors[] = $item['embedding'];
        }

        return $vectors;
    }

    public function isAvailable(): bool
    {
        return !empty(config('services.openai.api_key'))
            || !empty(env('OPENAI_API_KEY'));
    }

    private function resolveApiKey(): string
    {
        $key = config('services.openai.api_key');
        if (!is_string($key) || $key === '') {
            $key = env('OPENAI_API_KEY') ?? '';
        }
        if ($key === '') {
            throw new RuntimeException('OPENAI_API_KEY missing — cannot create embeddings.');
        }
        return $key;
    }
}
