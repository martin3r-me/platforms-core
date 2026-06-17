<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\EmbeddingProviderContract;
use Platform\Core\Contracts\EmbeddingStoreContract;
use Platform\Core\Jobs\GenerateEmbeddingJob;
use RuntimeException;

/**
 * Facade-artige Convenience-API für Module.
 *
 * Module rufen embedAndStore() / search() / delete() — der Service kümmert sich
 * um Provider-Auflösung, Source-Hash-basiertes Skip-if-unchanged, Batch-Chunking
 * und das Zusammenspiel von Provider und Store.
 */
class EmbeddingService
{
    public function __construct(
        private readonly EmbeddingProviderRegistry $providers,
        private readonly EmbeddingStoreContract $store,
    ) {}

    /**
     * Embedet einen einzelnen Text und speichert ihn ab.
     * Skip-if-unchanged: gleicher source_hash → kein API-Call, kein DB-Write.
     */
    public function embedAndStore(
        int $teamId,
        string $entityType,
        int|string $entityId,
        string $text,
        ?string $providerName = null,
        ?array $metadata = null,
    ): void {
        $provider = $this->resolveProvider($providerName);
        $hash = $this->hashText($text);

        $existing = $this->store->getSourceHash(
            $teamId, $entityType, $entityId,
            $provider->getName(), $provider->getModel(),
        );

        if ($existing === $hash) {
            return;
        }

        $vectors = $provider->embed([$text], 'document');
        if (count($vectors) === 0) {
            throw new RuntimeException('Embedding provider returned no vectors.');
        }

        $this->store->store(
            teamId: $teamId,
            entityType: $entityType,
            entityId: $entityId,
            vector: $vectors[0],
            provider: $provider->getName(),
            model: $provider->getModel(),
            sourceHash: $hash,
            metadata: $metadata,
        );
    }

    /**
     * Embedet und speichert mehrere Einträge in einem Batch (effizient bei großen Korpora).
     *
     * @param array<int, array{id: int|string, text: string, metadata?: ?array}> $entries
     */
    public function embedAndStoreBatch(
        int $teamId,
        string $entityType,
        array $entries,
        ?string $providerName = null,
    ): void {
        if (count($entries) === 0) {
            return;
        }

        $provider = $this->resolveProvider($providerName);
        $providerKey = $provider->getName();
        $modelKey = $provider->getModel();

        // Skip-if-unchanged: vorhandene Hashes vorab holen, identische überspringen
        $toProcess = [];
        foreach ($entries as $entry) {
            $text = (string) ($entry['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $hash = $this->hashText($text);
            $existing = $this->store->getSourceHash(
                $teamId, $entityType, $entry['id'],
                $providerKey, $modelKey,
            );
            if ($existing === $hash) {
                continue;
            }
            $toProcess[] = [
                'id' => $entry['id'],
                'text' => $text,
                'hash' => $hash,
                'metadata' => $entry['metadata'] ?? null,
            ];
        }

        if (count($toProcess) === 0) {
            return;
        }

        $batchSize = max(1, $provider->getMaxBatchSize());
        foreach (array_chunk($toProcess, $batchSize) as $chunk) {
            $texts = array_map(fn($e) => $e['text'], $chunk);
            $vectors = $provider->embed($texts, 'document');

            if (count($vectors) !== count($chunk)) {
                throw new RuntimeException(
                    'Embedding provider returned ' . count($vectors)
                    . ' vectors for ' . count($chunk) . ' inputs.'
                );
            }

            foreach ($chunk as $i => $entry) {
                $this->store->store(
                    teamId: $teamId,
                    entityType: $entityType,
                    entityId: $entry['id'],
                    vector: $vectors[$i],
                    provider: $providerKey,
                    model: $modelKey,
                    sourceHash: $entry['hash'],
                    metadata: $entry['metadata'],
                );
            }
        }
    }

    /**
     * Semantische Suche.
     *
     * @param string[]|null $entityTypes Optional auf bestimmte Entity-Types einschränken.
     * @return array<int, array{entity_type: string, entity_id: string, score: float, metadata: ?array}>
     */
    public function search(
        int $teamId,
        string $queryText,
        ?array $entityTypes = null,
        int $limit = 10,
        float $minScore = 0.0,
        ?string $providerName = null,
    ): array {
        $provider = $this->resolveProvider($providerName);
        $vectors = $provider->embed([$queryText], 'query');
        if (count($vectors) === 0) {
            return [];
        }

        return $this->store->search(
            teamId: $teamId,
            queryVector: $vectors[0],
            provider: $provider->getName(),
            model: $provider->getModel(),
            entityTypes: $entityTypes,
            limit: $limit,
            minScore: $minScore,
        );
    }

    /**
     * Löscht alle Embeddings einer Entität (alle Provider, alle Modelle).
     */
    public function delete(int $teamId, string $entityType, int|string $entityId): void
    {
        $this->store->delete($teamId, $entityType, $entityId);
    }

    /**
     * Dispatcht einen Embed-Vorgang asynchron auf die Queue.
     */
    public function queueEmbedAndStore(
        int $teamId,
        string $entityType,
        int|string $entityId,
        string $text,
        ?string $providerName = null,
        ?array $metadata = null,
    ): void {
        GenerateEmbeddingJob::dispatch(
            $teamId,
            $entityType,
            (string) $entityId,
            $text,
            $providerName,
            $metadata,
        );
    }

    private function resolveProvider(?string $name): EmbeddingProviderContract
    {
        if ($name !== null) {
            $provider = $this->providers->get($name);
            if ($provider === null) {
                throw new RuntimeException("Embedding provider '{$name}' is not registered.");
            }
            if (!$provider->isAvailable()) {
                throw new RuntimeException("Embedding provider '{$name}' is registered but not available (missing API key?).");
            }
            return $provider;
        }

        $default = $this->providers->getDefaultProvider();
        if ($default === null) {
            Log::error('[EmbeddingService] No embedding provider available', [
                'registered' => array_keys($this->providers->all()),
            ]);
            throw new RuntimeException('No embedding provider is available — configure at least one (e.g. OPENAI_API_KEY).');
        }
        return $default;
    }

    private function hashText(string $text): string
    {
        return hash('sha256', $text);
    }
}
