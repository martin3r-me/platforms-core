<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\EmbeddingStoreContract;
use Platform\Core\Exceptions\EmbeddingDimensionMismatchException;
use Platform\Core\Models\Embedding;

/**
 * Default-Implementation des EmbeddingStoreContract auf MySQL+JSON.
 *
 * Strategie: SQL-Pre-Filter auf (team_id, entity_type, provider, model) reduziert
 * die Kandidatenmenge, danach Cosine in PHP. Bei wenigen tausend Vektoren pro
 * Tenant schneller als ein ANN-Roundtrip — Skalierungsgrenze ist erreicht, wenn
 * ein einzelner Tenant deutlich über ~50k Vektoren pro (entity_type, provider, model)
 * geht. Ab dort: QdrantEmbeddingStore implementieren und im Service-Provider tauschen.
 */
class MySqlJsonEmbeddingStore implements EmbeddingStoreContract
{
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
        Embedding::updateOrCreate(
            [
                'team_id' => $teamId,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
                'provider' => $provider,
                'model' => $model,
            ],
            [
                'dimensions' => count($vector),
                'vector' => array_values($vector),
                'metadata' => $metadata,
                'source_hash' => $sourceHash,
            ],
        );
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

        $query = Embedding::query()
            ->where('team_id', $teamId)
            ->where('provider', $provider)
            ->where('model', $model);

        if ($entityTypes !== null && count($entityTypes) > 0) {
            $query->whereIn('entity_type', $entityTypes);
        }

        $candidates = $query->select(['id', 'entity_type', 'entity_id', 'dimensions', 'vector', 'metadata'])
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $firstDim = (int) $candidates->first()->dimensions;
        if ($firstDim !== $queryDim) {
            throw new EmbeddingDimensionMismatchException(
                expected: $firstDim,
                got: $queryDim,
                provider: $provider,
                model: $model,
            );
        }

        $queryNorm = $this->norm($queryVector);
        if ($queryNorm === 0.0) {
            return [];
        }

        $results = [];
        foreach ($candidates as $row) {
            $vec = $row->vector;
            if (!is_array($vec) || count($vec) !== $queryDim) {
                continue;
            }

            $score = $this->cosine($queryVector, $vec, $queryNorm);
            if ($score < $minScore) {
                continue;
            }

            $results[] = [
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'score' => $score,
                'metadata' => $row->metadata,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    public function delete(int $teamId, string $entityType, int|string $entityId): void
    {
        Embedding::query()
            ->where('team_id', $teamId)
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId)
            ->delete();
    }

    public function getSourceHash(
        int $teamId,
        string $entityType,
        int|string $entityId,
        string $provider,
        string $model,
    ): ?string {
        return Embedding::query()
            ->where('team_id', $teamId)
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId)
            ->where('provider', $provider)
            ->where('model', $model)
            ->value('source_hash');
    }

    public function purgeProvider(int $teamId, string $provider, string $model): int
    {
        return Embedding::query()
            ->where('team_id', $teamId)
            ->where('provider', $provider)
            ->where('model', $model)
            ->delete();
    }

    /**
     * Cosine-Similarity zwischen Query- und Document-Vektor.
     * $queryNorm wird einmalig vom Caller vorberechnet, um Wiederholungen
     * über die Kandidatenschleife zu vermeiden.
     */
    private function cosine(array $a, array $b, float $aNorm): float
    {
        $dot = 0.0;
        $bSq = 0.0;
        $n = count($a);
        for ($i = 0; $i < $n; $i++) {
            $av = (float) $a[$i];
            $bv = (float) $b[$i];
            $dot += $av * $bv;
            $bSq += $bv * $bv;
        }
        $bNorm = sqrt($bSq);
        if ($bNorm === 0.0) {
            return 0.0;
        }
        return $dot / ($aNorm * $bNorm);
    }

    private function norm(array $v): float
    {
        $sq = 0.0;
        foreach ($v as $x) {
            $f = (float) $x;
            $sq += $f * $f;
        }
        return sqrt($sq);
    }
}
