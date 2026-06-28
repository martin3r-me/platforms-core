<?php

namespace Platform\Core\Verbalization;

use DateTimeImmutable;
use Platform\Core\Verbalization\Enums\ClaimType;
use Platform\Core\Verbalization\Enums\Confidence;

/**
 * Geltungsanspruch auf einer Kante.
 *
 * Dasselbe Werkzeug fuer drei Faelle:
 *  - Systemfakt        → SYSTEM_VERIFIED + VERIFIED
 *  - User-Eintrag      → SELF_REPORTED  + (VERIFIED|PLAUSIBLE|ROUGH)
 *  - Fremdaussage      → THIRD_PARTY    + Level je nach Bestaetigungen
 *  - Inferenz          → INFERRED       + Level je nach Konfidenz
 *
 * sourceName ist nur fuer non-system claims relevant (welcher User).
 * assertedAt ist nur fuer non-system claims relevant (wann gesetzt).
 */
final class Claim
{
    public function __construct(
        public readonly ClaimType $type,
        public readonly Confidence $level = Confidence::VERIFIED,
        public readonly ?string $sourceName = null,
        public readonly ?DateTimeImmutable $assertedAt = null,
    ) {}

    public static function systemVerified(): self
    {
        return new self(ClaimType::SYSTEM_VERIFIED, Confidence::VERIFIED);
    }
}
