<?php

namespace Platform\Core\Health\Services;

use Platform\Core\Health\Enums\HealthColor;

/**
 * Composite-Health-Berechnung aus mehreren Achsen.
 *
 * Liefert score (gewichteter Mittelwert ueber vorhandene Achsen), color
 * (worst-of-Color ueber alle Achsen, mit optionalem Confidence-Gate)
 * und worst_axis (die schwaechste Achse, sofern rot oder gelb).
 *
 * Vorhanden-Achsen sind die, die im $axes-Array auftauchen — fehlende
 * Achsen werden weder im Score noch in der Color beruecksichtigt.
 *
 * Confidence-Gate: liegt confidence < threshold, wird color = GRAY und
 * score = null (ehrlicher als ein erlogener Wert ohne Datenbasis).
 */
class HealthCompositor
{
    public const DEFAULT_CONFIDENCE_THRESHOLD = 50;

    /**
     * @param array<string,int> $axes       achsKey → score (0..100). Nur vorhandene Achsen!
     * @param array<string,int> $weights    achsKey → gewicht. Sum sollte 100 sein.
     * @param int|null          $confidence 0..100, optional fuer Gate. Null = kein Gate.
     * @param int               $confidenceThreshold Score unter dem das Gate greift.
     *
     * @return array{score:int|null,color:string|null,worst_axis:string|null,axis_scores:array<string,int>}
     */
    public function compose(
        array $axes,
        array $weights,
        ?int $confidence = null,
        int $confidenceThreshold = self::DEFAULT_CONFIDENCE_THRESHOLD,
    ): array {
        if (empty($axes)) {
            return [
                'score' => null,
                'color' => null,
                'worst_axis' => null,
                'axis_scores' => [],
            ];
        }

        // Confidence-Gate: nicht genug Daten → ehrlich grau, score null
        if ($confidence !== null && $confidence < $confidenceThreshold) {
            return [
                'score' => null,
                'color' => HealthColor::GRAY->value,
                'worst_axis' => $this->resolveWorstAxis($axes),
                'axis_scores' => $axes,
            ];
        }

        $score = $this->weightedScore($axes, $weights);
        $color = $this->worstColor($axes);
        $worst = $this->resolveWorstAxis($axes);

        return [
            'score' => $score,
            'color' => $color?->value,
            'worst_axis' => $worst,
            'axis_scores' => $axes,
        ];
    }

    /**
     * Gewichteter Durchschnitt — Gewichte werden nur fuer vorhandene Achsen
     * gezaehlt (totalWeight = sum aller weights deren Achse präsent ist).
     * Damit ist der Score auch bei fehlenden Achsen valide 0..100.
     */
    public function weightedScore(array $axes, array $weights): ?int
    {
        $totalWeight = 0;
        $weightedSum = 0;
        foreach ($axes as $key => $val) {
            $w = $weights[$key] ?? 25;
            $totalWeight += $w;
            $weightedSum += $w * $val;
        }
        return $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : null;
    }

    /**
     * Worst-of-Color: die schwaechste Achse dominiert die Gesamtampel.
     */
    public function worstColor(array $axes): ?HealthColor
    {
        if (empty($axes)) {
            return null;
        }
        $worst = HealthColor::GREEN;
        foreach ($axes as $val) {
            $c = HealthColor::fromScore((int) $val);
            if ($c->severityRank() < $worst->severityRank()) {
                $worst = $c;
            }
        }
        return $worst;
    }

    /**
     * Die schwaechste Achse — zuerst rote, dann gelbe, bei Gleichstand
     * niedrigster Score. Null wenn alle Achsen gruen sind oder leer.
     */
    public function resolveWorstAxis(array $axes): ?string
    {
        if (empty($axes)) {
            return null;
        }
        $bestRank = 9;
        $bestScore = PHP_INT_MAX;
        $bestKey = null;
        foreach ($axes as $key => $val) {
            $color = HealthColor::fromScore((int) $val);
            $rank = $color->severityRank();
            if ($rank < $bestRank || ($rank === $bestRank && $val < $bestScore)) {
                $bestRank = $rank;
                $bestScore = (int) $val;
                $bestKey = $key;
            }
        }
        // Nur reporten wenn schwaechste Achse rot oder gelb — gruene Achsen
        // sind keine "worst", sie sind okay.
        if ($bestRank > HealthColor::YELLOW->severityRank()) {
            return null;
        }
        return $bestKey;
    }
}
