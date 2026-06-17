<?php

namespace Platform\Core\Contracts;

/**
 * Contract für Embedding-Storage
 *
 * Persistiert und durchsucht Vektoren. Default-Implementation nutzt MySQL+JSON;
 * spätere Implementationen (Qdrant, pgvector) sind drop-in austauschbar,
 * solange dieser Contract erfüllt wird.
 */
interface EmbeddingStoreContract
{
    /**
     * Speichert oder aktualisiert einen Vektor für eine Entität.
     *
     * Identifizierende Felder: (team_id, entity_type, entity_id, provider, model).
     * Bei wiederholtem store mit gleichem source_hash → Skip möglich (Caller entscheidet).
     *
     * @param float[] $vector
     */
    public function store(
        int $teamId,
        string $entityType,
        int|string $entityId,
        array $vector,
        string $provider,
        string $model,
        ?string $sourceHash = null,
        ?array $metadata = null,
    ): void;

    /**
     * Ähnlichkeitssuche.
     *
     * Filtert vor der Distanzberechnung auf (team_id, provider, model) und optional
     * auf $entityTypes. Wirft EmbeddingDimensionMismatchException, wenn die Query-
     * Dimension nicht zur gespeicherten passt.
     *
     * @param float[] $queryVector
     * @param string[]|null $entityTypes  Optional auf bestimmte Entity-Types einschränken.
     * @return array<int, array{entity_type: string, entity_id: string, score: float, metadata: ?array}>
     */
    public function search(
        int $teamId,
        array $queryVector,
        string $provider,
        string $model,
        ?array $entityTypes = null,
        int $limit = 10,
        float $minScore = 0.0,
    ): array;

    /**
     * Löscht alle Embeddings einer Entität (über alle Provider/Modelle hinweg).
     */
    public function delete(int $teamId, string $entityType, int|string $entityId): void;

    /**
     * Holt einen bestehenden source_hash für eine Entität (für Skip-if-unchanged).
     */
    public function getSourceHash(
        int $teamId,
        string $entityType,
        int|string $entityId,
        string $provider,
        string $model,
    ): ?string;

    /**
     * Entfernt alle Vektoren eines Provider/Modell-Paares für ein Team
     * (z.B. nach Modell-Wechsel, vor Re-Index).
     */
    public function purgeProvider(int $teamId, string $provider, string $model): int;
}
