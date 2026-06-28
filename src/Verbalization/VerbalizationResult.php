<?php

namespace Platform\Core\Verbalization;

/**
 * Output des Verbalizers.
 *
 * prose = das LLM-veredelte Endergebnis (was nach aussen geht)
 * factSheet = die deterministische Faktenbasis (was das LLM bekommen hat)
 * Beides exposed, damit man im Debug-Modus die Schichten getrennt sehen kann.
 */
final class VerbalizationResult
{
    public function __construct(
        public readonly string $prose,
        public readonly string $factSheet,
        public readonly string $model,
        public readonly array $usage = [],
        public readonly array $meta = [],
    ) {}
}
