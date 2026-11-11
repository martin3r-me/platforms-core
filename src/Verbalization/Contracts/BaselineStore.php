<?php

namespace Platform\Core\Verbalization\Contracts;

/**
 * BaselineStore — Zeitreihe der Metriken je Subject, aus der Collectoren Deltas
 * ziehen ("seit letzter Woche +3 offene Aufgaben").
 *
 * Bedient vom reporting-Modul. Ist reporting nicht installiert, ist der Contract
 * nicht gebunden — Collectoren prüfen per app()->bound() und lassen die Deltas weg.
 */
interface BaselineStore
{
    /**
     * Deltas gegen den jüngsten Snapshot, der mindestens $daysBack Tage alt ist.
     *
     * @param  array<string, int|float> $currentMetrics
     * @return array<string, array{current: float, baseline: float, delta: float, delta_pct: float|null}>
     */
    public function deltaFor(string $subjectType, string $subjectId, int $daysBack, array $currentMetrics): array;
}
