<?php

namespace Platform\Core\Health\Services;

/**
 * Berechnet einen Confidence-Score aus einer Datenebenen-Verfuegbarkeits-Map.
 *
 * Beispiel:
 *   $calc->compute([
 *       'canvas' => true,
 *       'planned_period' => false,
 *       'planned_minutes' => false,
 *       'tasks' => true,
 *   ])
 *   → ['score' => 50, 'reason' => 'missing:planned_period,planned_minutes']
 *
 * Score = (vorhandene_ebenen / gesamt_ebenen) * 100.
 * Reason = "missing:layer1,layer2" oder null wenn alle vorhanden.
 */
class ConfidenceCalculator
{
    /**
     * @param array<string,bool> $hasData  Datenebenen-Name → vorhanden?
     * @return array{score:int,reason:string|null}
     */
    public function compute(array $hasData): array
    {
        if (empty($hasData)) {
            return ['score' => 0, 'reason' => 'no_layers_defined'];
        }

        $total = count($hasData);
        $present = count(array_filter($hasData));
        $score = (int) round(($present / $total) * 100);

        $missing = array_keys(array_filter($hasData, fn ($v) => ! $v));
        $reason = empty($missing) ? null : 'missing:' . implode(',', $missing);

        return ['score' => $score, 'reason' => $reason];
    }
}
