<?php

namespace Platform\Core\Exceptions;

use RuntimeException;

/**
 * Wird vom EmbeddingStore geworfen, wenn ein Query-Vektor eine andere Dimension hat
 * als die im Store hinterlegten Vektoren für (provider, model).
 *
 * Tritt typischerweise nach einem Provider-/Modell-Wechsel auf und signalisiert,
 * dass Re-Indexing notwendig ist (z.B. via purgeProvider() + Re-Embedding-Job).
 */
class EmbeddingDimensionMismatchException extends RuntimeException
{
    public function __construct(
        public readonly int $expected,
        public readonly int $got,
        public readonly string $provider,
        public readonly string $model,
    ) {
        parent::__construct(
            "Embedding dimension mismatch for provider='{$provider}' model='{$model}': "
            . "stored vectors have {$expected} dimensions, query has {$got}. "
            . "Re-indexing required."
        );
    }
}
