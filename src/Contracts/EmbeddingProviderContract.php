<?php

namespace Platform\Core\Contracts;

/**
 * Contract für Embedding-Provider
 *
 * Erzeugt Vektor-Repräsentationen aus Text. Storage erfolgt separat
 * über EmbeddingStoreContract — Provider und Store sind orthogonal.
 */
interface EmbeddingProviderContract
{
    /**
     * Provider-Name (z.B. 'openai', 'gemini', 'voyage')
     */
    public function getName(): string;

    /**
     * Aktuell verwendetes Modell (z.B. 'text-embedding-3-large')
     */
    public function getModel(): string;

    /**
     * Vektor-Dimension dieses Providers/Modells (z.B. 3072, 768, 1024)
     */
    public function getDimensions(): int;

    /**
     * Liefert der Provider L2-normalisierte Vektoren?
     * Wichtig für Cosine == Dot-Product-Äquivalenz.
     */
    public function isNormalized(): bool;

    /**
     * Maximale Anzahl Texte pro Batch-Request (Provider-Limit).
     */
    public function getMaxBatchSize(): int;

    /**
     * Erzeugt Embeddings für die übergebenen Texte.
     *
     * @param string[] $texts
     * @param string $type 'document' oder 'query' — manche Provider
     *                     (Voyage, Cohere, Gemini) unterscheiden beim Encoding.
     * @return float[][] Array von Vektoren in derselben Reihenfolge wie $texts.
     */
    public function embed(array $texts, string $type = 'document'): array;

    /**
     * Prüft ob Provider verfügbar/konfiguriert ist.
     */
    public function isAvailable(): bool;
}
