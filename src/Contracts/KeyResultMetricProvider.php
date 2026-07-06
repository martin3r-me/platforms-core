<?php

namespace Platform\Core\Contracts;

use Platform\Core\KeyResult\MetricRequest;
use Platform\Core\KeyResult\MetricValue;

/**
 * Contract für Module, die messbare Metriken für Key Results bereitstellen.
 *
 * Jedes Modul (planner, helpdesk, organization, datawarehouse, …) registriert
 * einen Provider in der KeyResultMetricRegistry. Ein Provider ist bewusst DUMM:
 * er liest nur den Rohwert einer Quelle. Zielerreichung (Baseline, Target,
 * Polarität, Rolle, Aggregation) besitzt die OKR-Engine, NICHT der Provider.
 */
interface KeyResultMetricProvider
{
    /**
     * Katalog der angebotenen Metriken (für Discovery / okr.kr_metrics.GET).
     *
     * Jeder Eintrag:
     *   metric_key        string  z.B. "planner.tasks_done_ratio" (global eindeutig, module-prefixed)
     *   module            string
     *   label             string
     *   description       ?string
     *   value_type        string  ratio|count|boolean|number   (WIE normalisiert wird)
     *   unit              ?string
     *   default_polarity  string  up|down                      (Richtung; von der Engine überschreibbar)
     *   supported_roles   string[] score|gate|cap|info
     *   binding           string  instance|kr_entity|team
     *   selector_schema   array   Feld-Deskriptoren [{field,type,required,label?,lookup_tool?}]
     *   supports_window   bool
     *
     * @return array<int, array<string, mixed>>
     */
    public function metricDefinitions(): array;

    /**
     * Batch-Auflösung EINER Metrik für viele Requests (N+1-frei).
     *
     * Read-only, seiteneffektfrei. Bei fehlenden Daten NIE 0 zurückgeben,
     * sondern MetricValue::unavailable() (→ Engine behandelt als N/A, nicht rot).
     *
     * @param  string          $metricKey
     * @param  MetricRequest[] $requests   erhaltene Keys werden gespiegelt
     * @return array<int|string, MetricValue> gleiche Keys wie $requests
     */
    public function resolveBatch(string $metricKey, array $requests): array;
}
