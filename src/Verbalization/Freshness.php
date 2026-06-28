<?php

namespace Platform\Core\Verbalization;

use DateTimeImmutable;
use Platform\Core\Verbalization\Enums\DataSource;

/**
 * Wann das Subject gueltig ist und woher es kommt.
 *
 * Damit der Verbalizer ehrlich formulieren kann:
 *  - LIVE                       → "aktuell ..."
 *  - SNAPSHOT (8h alt)          → "Stand heute frueh ..."
 *  - SNAPSHOT_WITH_LIVE_TOPUP   → "Stand letzter Nacht, seither ..."
 *
 * stalenessSeconds darf null sein, wird vom Sammler optional gesetzt
 * (bequemer als wenn der Verbalizer es jedesmal selbst rechnen muss).
 */
final class Freshness
{
    public function __construct(
        public readonly DateTimeImmutable $asOf,
        public readonly DataSource $source,
        public readonly ?int $stalenessSeconds = null,
    ) {}
}
