<?php

namespace Platform\Core\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\EmbeddingStoreContract;
use Platform\Core\Exceptions\EmbeddingDimensionMismatchException;
use RuntimeException;

/**
 * Qdrant-Implementation des EmbeddingStoreContract für große Korpora (100k+ Vektoren).
 *
 * Spricht die Qdrant-REST-API direkt via Http:: — bewusst ohne externe Client-Lib,
 * orthogonal zu den Providern (siehe OpenAiEmbeddingProvider).
 *
 * Datenmodell-Mapping (MySQL → Qdrant):
 *  - (provider, model)                 → eine Collection pro Modell (fixe Dimension,
 *                                        Cosine-Distanz). openai/3072 und gemini/768
 *                                        landen dadurch automatisch getrennt.
 *  - vector                            → Point-Vektor
 *  - (team_id, entity_type, entity_id) → deterministische Point-ID (UUID aus md5),
 *                                        damit store() idempotent upsertet.
 *  - team_id, entity_type, entity_id,
 *    source_hash, metadata, dimensions → Payload (team_id + entity_type indiziert,
 *                                        damit gefilterte ANN-Suche schnell bleibt).
 *
 * Umschaltung gegen MySqlJsonEmbeddingStore erfolgt per config('embeddings.store')
 * im CoreServiceProvider — Service, Job und Provider bleiben unangetastet.
 */
class QdrantEmbeddingStore implements EmbeddingStoreContract
{
    /**
     * Collections, deren Existenz in diesem Request bereits sichergestellt wurde.
     *
     * @var array<string, true>
     */
    private array $ensured = [];

    public function __construct(
        private readonly string $url,
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 30,
        private readonly ?string $quantization = null,
        private readonly string $prefix = 'emb',
    ) {}

    public function store(
        int $teamId,
        string $entityType,
        int|string $entityId,
        array $vector,
        string $provider,
        string $model,
        ?string $sourceHash = null,
        ?array $metadata = null,
    ): void {
        $collection = $this->collectionName($provider, $model);
        $this->ensureCollection($collection, count($vector));

        $point = [
            'id' => $this->pointId($teamId, $entityType, $entityId),
            'vector' => array_values($vector),
            'payload' => [
                'team_id' => $teamId,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
                'dimensions' => count($vector),
                'source_hash' => $sourceHash,
                'metadata' => $metadata,
            ],
        ];

        $resp = $this->http()->put("/collections/{$collection}/points?wait=true", [
            'points' => [$point],
        ]);
        $this->throwUnlessOk($resp, "upsert point into {$collection}");
    }

    public function search(
        int $teamId,
        array $queryVector,
        string $provider,
        string $model,
        ?array $entityTypes = null,
        int $limit = 10,
        float $minScore = 0.0,
    ): array {
        $queryDim = count($queryVector);
        if ($queryDim === 0) {
            return [];
        }

        $collection = $this->collectionName($provider, $model);

        $info = $this->http()->get("/collections/{$collection}");
        if ($info->status() === 404) {
            return [];
        }
        $this->throwUnlessOk($info, "inspect collection {$collection}");

        $storedDim = (int) $info->json('result.config.params.vectors.size');
        if ($storedDim !== 0 && $storedDim !== $queryDim) {
            throw new EmbeddingDimensionMismatchException(
                expected: $storedDim,
                got: $queryDim,
                provider: $provider,
                model: $model,
            );
        }

        $must = [
            ['key' => 'team_id', 'match' => ['value' => $teamId]],
        ];
        if ($entityTypes !== null && count($entityTypes) > 0) {
            $must[] = ['key' => 'entity_type', 'match' => ['any' => array_values($entityTypes)]];
        }

        $resp = $this->http()->post("/collections/{$collection}/points/search", [
            'vector' => array_values($queryVector),
            'filter' => ['must' => $must],
            'limit' => $limit,
            'with_payload' => true,
            'score_threshold' => $minScore,
        ]);
        $this->throwUnlessOk($resp, "search {$collection}");

        $hits = $resp->json('result');
        if (!is_array($hits)) {
            return [];
        }

        $results = [];
        foreach ($hits as $hit) {
            $payload = $hit['payload'] ?? [];
            $results[] = [
                'entity_type' => (string) ($payload['entity_type'] ?? ''),
                'entity_id' => (string) ($payload['entity_id'] ?? ''),
                'score' => (float) ($hit['score'] ?? 0.0),
                'metadata' => $payload['metadata'] ?? null,
            ];
        }

        return $results;
    }

    public function delete(int $teamId, string $entityType, int|string $entityId): void
    {
        // Entität über alle Provider/Modelle hinweg löschen → alle unsere Collections.
        $filter = ['must' => [
            ['key' => 'team_id', 'match' => ['value' => $teamId]],
            ['key' => 'entity_type', 'match' => ['value' => $entityType]],
            ['key' => 'entity_id', 'match' => ['value' => (string) $entityId]],
        ]];

        foreach ($this->listCollections() as $collection) {
            $resp = $this->http()->post("/collections/{$collection}/points/delete?wait=true", [
                'filter' => $filter,
            ]);
            $this->throwUnlessOk($resp, "delete points from {$collection}");
        }
    }

    public function getSourceHash(
        int $teamId,
        string $entityType,
        int|string $entityId,
        string $provider,
        string $model,
    ): ?string {
        $collection = $this->collectionName($provider, $model);
        $id = $this->pointId($teamId, $entityType, $entityId);

        $resp = $this->http()->get("/collections/{$collection}/points/{$id}");
        if ($resp->status() === 404) {
            // Collection oder Point existiert (noch) nicht → wie "kein Hash".
            return null;
        }
        $this->throwUnlessOk($resp, "get point from {$collection}");

        $hash = $resp->json('result.payload.source_hash');

        return is_string($hash) ? $hash : null;
    }

    public function purgeProvider(int $teamId, string $provider, string $model): int
    {
        $collection = $this->collectionName($provider, $model);
        $filter = ['must' => [
            ['key' => 'team_id', 'match' => ['value' => $teamId]],
        ]];

        // Delete-by-Filter liefert keine Trefferzahl → vorher exakt zählen.
        $countResp = $this->http()->post("/collections/{$collection}/points/count", [
            'filter' => $filter,
            'exact' => true,
        ]);
        if ($countResp->status() === 404) {
            return 0;
        }
        $this->throwUnlessOk($countResp, "count points in {$collection}");

        $count = (int) $countResp->json('result.count');
        if ($count === 0) {
            return 0;
        }

        $del = $this->http()->post("/collections/{$collection}/points/delete?wait=true", [
            'filter' => $filter,
        ]);
        $this->throwUnlessOk($del, "purge points from {$collection}");

        return $count;
    }

    /**
     * Stellt sicher, dass die Collection existiert (Cosine, gewünschte Dimension,
     * optional Quantisierung) und die Filter-Payload-Felder indiziert sind.
     */
    private function ensureCollection(string $collection, int $dim): void
    {
        if (isset($this->ensured[$collection])) {
            return;
        }

        $info = $this->http()->get("/collections/{$collection}");

        if ($info->status() === 404) {
            $body = [
                'vectors' => ['size' => $dim, 'distance' => 'Cosine'],
            ];
            if ($this->quantization === 'scalar') {
                $body['quantization_config'] = [
                    'scalar' => ['type' => 'int8', 'always_ram' => true],
                ];
            }

            $create = $this->http()->put("/collections/{$collection}", $body);
            $this->throwUnlessOk($create, "create collection {$collection}");

            // Payload-Indizes für schnelle gefilterte ANN-Suche (best effort).
            $this->http()->put("/collections/{$collection}/index?wait=true", [
                'field_name' => 'team_id',
                'field_schema' => 'integer',
            ]);
            $this->http()->put("/collections/{$collection}/index?wait=true", [
                'field_name' => 'entity_type',
                'field_schema' => 'keyword',
            ]);
        } elseif ($info->failed()) {
            $this->throwUnlessOk($info, "inspect collection {$collection}");
        }

        $this->ensured[$collection] = true;
    }

    /**
     * @return string[] Namen aller Collections mit unserem Prefix.
     */
    private function listCollections(): array
    {
        $resp = $this->http()->get('/collections');
        $this->throwUnlessOk($resp, 'list collections');

        $collections = $resp->json('result.collections');
        if (!is_array($collections)) {
            return [];
        }

        $names = [];
        foreach ($collections as $c) {
            $name = $c['name'] ?? null;
            if (is_string($name) && str_starts_with($name, $this->prefix . '_')) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Collection-Name pro (provider, model). Qdrant-Collections haben eine feste
     * Vektor-Dimension, daher ist die Trennung nach Modell auch fachlich korrekt.
     */
    private function collectionName(string $provider, string $model): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', "{$provider}_{$model}");
        $slug = trim((string) $slug, '_');

        return $this->prefix . '_' . $slug;
    }

    /**
     * Deterministische, gültige UUID aus (team_id, entity_type, entity_id).
     * Enthält team_id → kollisionsfrei über Tenants. Provider/Modell stecken bereits
     * in der Collection, daher hier bewusst nicht Teil der ID (gleiche Entität teilt
     * dieselbe ID über alle Modell-Collections → einfacher Cross-Collection-Delete).
     */
    private function pointId(int $teamId, string $entityType, int|string $entityId): string
    {
        $raw = md5("{$teamId}:{$entityType}:{$entityId}");

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($raw, 0, 8),
            substr($raw, 8, 4),
            substr($raw, 12, 4),
            substr($raw, 16, 4),
            substr($raw, 20, 12),
        );
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl(rtrim($this->url, '/'))
            ->timeout($this->timeout)
            ->acceptJson();

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $request = $request->withHeaders(['api-key' => $this->apiKey]);
        }

        return $request;
    }

    private function throwUnlessOk(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        Log::error('[QdrantEmbeddingStore] Request failed', [
            'action' => $action,
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 1000),
        ]);

        throw new RuntimeException(
            "Qdrant request failed ({$action}): HTTP {$response->status()} — "
            . substr($response->body(), 0, 500)
        );
    }
}
