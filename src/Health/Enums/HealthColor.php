<?php

namespace Platform\Core\Health\Enums;

/**
 * Health-Ampel-Farben fuer Composite-Scores.
 *
 * Schwellwerte (Standard, von Modulen ueberschreibbar via Konstanten):
 *  - >= 70  → GREEN
 *  - >= 40  → YELLOW
 *  - sonst  → RED
 *  - null   → GRAY (nicht berechenbar / Confidence-Gate)
 */
enum HealthColor: string
{
    case GREEN = 'green';
    case YELLOW = 'yellow';
    case RED = 'red';
    case GRAY = 'gray';

    public const THRESHOLD_GREEN = 70;
    public const THRESHOLD_YELLOW = 40;

    /**
     * Mappt einen Score auf eine Ampel-Farbe.
     * Null → GRAY.
     */
    public static function fromScore(?int $score): self
    {
        if ($score === null) {
            return self::GRAY;
        }
        if ($score >= self::THRESHOLD_GREEN) {
            return self::GREEN;
        }
        if ($score >= self::THRESHOLD_YELLOW) {
            return self::YELLOW;
        }
        return self::RED;
    }

    /**
     * Severity-Rang fuer "worst-of"-Vergleiche: niedriger = schlimmer.
     */
    public function severityRank(): int
    {
        return match ($this) {
            self::RED => 0,
            self::YELLOW => 1,
            self::GRAY => 2,
            self::GREEN => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GREEN => 'Stabil',
            self::YELLOW => 'Achtung',
            self::RED => 'Brennt',
            self::GRAY => 'Keine Daten',
        };
    }
}
