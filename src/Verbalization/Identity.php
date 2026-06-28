<?php

namespace Platform\Core\Verbalization;

/**
 * Identitaet eines Knotens — was ihn benennt.
 *
 * Trennt absichtlich primaerName (was im ersten Satz steht) von
 * shortLabel (was im fortlaufenden Text genuegt) und aliases
 * (was alternativ erkannt werden darf).
 */
final class Identity
{
    /**
     * @param  string[]  $aliases
     */
    public function __construct(
        public readonly string $primaryName,
        public readonly ?string $shortLabel = null,
        public readonly ?string $slug = null,
        public readonly array $aliases = [],
    ) {}
}
