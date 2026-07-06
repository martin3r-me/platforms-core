<?php

namespace Platform\Core\KeyResult;

/**
 * Anfrage an einen KeyResultMetricProvider. Von der OKR-Sync-Engine gefüllt und
 * fertig aufgelöst übergeben — der Provider muss keinen Scope/Window mehr ableiten.
 */
class MetricRequest
{
    /**
     * @param string      $metricKey
     * @param array       $selector  Selector-Werte laut selector_schema (z.B. ['project_id' => 193]); leer bei kr_entity/team
     * @param array       $scope     ['root_team_id'=>int, 'team_ids'=>int[], 'entity_id'=>?int, 'entity_subtree_ids'=>int[]]
     * @param array|null  $window    ['mode'=>'cumulative'|'period', 'from'=>?string, 'to'=>?string] — null = kein Zeitfenster
     * @param string|null $asOf      Messzeitpunkt (ISO), i.d.R. now; für Backfill setzbar
     * @param mixed       $ref       Korrelations-Referenz des Aufrufers (z.B. measure_id) — vom Provider unangetastet
     */
    public function __construct(
        public readonly string $metricKey,
        public readonly array $selector = [],
        public readonly array $scope = [],
        public readonly ?array $window = null,
        public readonly ?string $asOf = null,
        public readonly mixed $ref = null,
    ) {
    }
}
