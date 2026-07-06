<?php

namespace Platform\Core\KeyResult;

/**
 * Ergebnis eines KeyResultMetricProvider. Enthält den ROHWERT — Normalisierung
 * auf Zielerreichung macht die OKR-Engine.
 */
class MetricValue
{
    /**
     * @param float|null  $value      Rohwert der Quelle (null wenn keine Daten)
     * @param bool        $available  Daten vorhanden? false → Engine wertet als N/A (nicht 0!)
     * @param string|null $asOf       Messzeitpunkt (ISO)
     * @param array|null  $detail     Optional: Aufschlüsselung für Drill-down/Verbalizer (z.B. ['done'=>45,'total'=>50])
     * @param string|null $label      Optional: plakative Kurzform ("45 von 50 erledigt")
     */
    public function __construct(
        public readonly ?float $value,
        public readonly bool $available = true,
        public readonly ?string $asOf = null,
        public readonly ?array $detail = null,
        public readonly ?string $label = null,
    ) {
    }

    public static function of(float $value, ?array $detail = null, ?string $label = null, ?string $asOf = null): self
    {
        return new self(value: $value, available: true, asOf: $asOf, detail: $detail, label: $label);
    }

    public static function unavailable(?string $reason = null): self
    {
        return new self(value: null, available: false, detail: $reason !== null ? ['reason' => $reason] : null);
    }
}
