<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\EmbeddingProviderContract;
use RuntimeException;

/**
 * Google Gemini Embedding Provider
 *
 * Drop-in-kompatibel mit der Cooking-Jarvis-Vorgängerlösung
 * (gemini-embedding-001, 768 Dim, L2-normalisiert). Nutzt die batch-fähige
 * batchEmbedContents-Methode der Generative Language API.
 */
class GeminiEmbeddingProvider implements EmbeddingProviderContract
{
    public const DEFAULT_MODEL = 'gemini-embedding-001';
    public const DEFAULT_DIMENSIONS = 768;

    private const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const MAX_BATCH = 100;

    public function __construct(
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly int $dimensions = self::DEFAULT_DIMENSIONS,
    ) {}

    public function getName(): string
    {
        return 'gemini';
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
        return true;
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
        $taskType = $this->mapTaskType($type);

        $requests = [];
        foreach ($texts as $text) {
            $requests[] = [
                'model' => "models/{$this->model}",
                'content' => [
                    'parts' => [['text' => (string) $text]],
                ],
                'taskType' => $taskType,
                'outputDimensionality' => $this->dimensions,
            ];
        }

        $url = self::ENDPOINT_BASE . "/{$this->model}:batchEmbedContents?key=" . urlencode($apiKey);

        $response = Http::timeout(60)->post($url, [
            'requests' => $requests,
        ]);

        if ($response->failed()) {
            Log::error('[GeminiEmbeddingProvider] Request failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 1000),
                'model' => $this->model,
                'input_count' => count($texts),
            ]);
            throw new RuntimeException(
                "Gemini embeddings request failed: HTTP {$response->status()} — "
                . substr($response->body(), 0, 500)
            );
        }

        $embeddings = $response->json('embeddings');
        if (!is_array($embeddings)) {
            throw new RuntimeException('Gemini embeddings response missing "embeddings" array.');
        }

        $vectors = [];
        foreach ($embeddings as $item) {
            $values = $item['values'] ?? null;
            if (!is_array($values)) {
                throw new RuntimeException('Gemini embeddings response item missing "values" array.');
            }
            $vectors[] = $values;
        }

        return $vectors;
    }

    public function isAvailable(): bool
    {
        return !empty(config('embeddings.gemini.api_key'))
            || !empty(env('GEMINI_API_KEY'));
    }

    private function resolveApiKey(): string
    {
        $key = config('embeddings.gemini.api_key');
        if (!is_string($key) || $key === '') {
            $key = env('GEMINI_API_KEY') ?? '';
        }
        if ($key === '') {
            throw new RuntimeException('GEMINI_API_KEY missing — cannot create embeddings.');
        }
        return $key;
    }

    private function mapTaskType(string $type): string
    {
        return match ($type) {
            'query' => 'RETRIEVAL_QUERY',
            default => 'RETRIEVAL_DOCUMENT',
        };
    }
}
