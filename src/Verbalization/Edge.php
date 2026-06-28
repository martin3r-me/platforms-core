<?php

namespace Platform\Core\Verbalization;

use Platform\Core\Verbalization\Enums\FactPriority;

/**
 * Typisierte Kante vom Subject zu einem anderen Knoten — MIT Geltung.
 *
 * relation ist die Verb-Form ("verantwortet_von", "gehoert_zu", "weckt", "serviert").
 * Der Verbalizer mappt sie ueber Semantic Layer in natuerliche Sprache.
 *
 * weight steuert ob die Kante in den ersten Satz gehoert (CORE),
 * spaeter erwaehnt wird (QUALIFYING) oder evtl. weggelassen werden darf (CONTEXT).
 */
final class Edge
{
    public function __construct(
        public readonly string $relation,
        public readonly string $targetType,
        public readonly ?string $targetId,
        public readonly string $targetLabel,
        public readonly Claim $claim,
        public readonly FactPriority $weight = FactPriority::QUALIFYING,
    ) {}
}
