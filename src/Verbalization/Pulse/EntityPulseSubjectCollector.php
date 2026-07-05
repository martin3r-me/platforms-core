<?php

namespace Platform\Core\Verbalization\Pulse;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Platform\Core\Verbalization\Enums\DataSource;
use Platform\Core\Verbalization\Enums\FactNature;
use Platform\Core\Verbalization\Enums\FactPriority;
use Platform\Core\Verbalization\Enums\SubjectKind;
use Platform\Core\Verbalization\Fact;
use Platform\Core\Verbalization\Freshness;
use Platform\Core\Verbalization\Identity;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorInterface;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorRegistry;

/**
 * Aggregierender Sammler: Entity-Pulse.
 *
 * Nimmt eine Organization-Entity und ruft die passenden Sub-Collector auf
 * (planner_project fuer alle angelinkten Projekte, organization_signals fuer die
 * Entity selbst; spaeter Contract/Helpdesk/...). Aus deren Facts werden
 * Highlights ausgewaehlt und in ein einzelnes Subject verdichtet.
 *
 * Design:
 *  - Sub-Collectoren werden ueber die SubjectCollectorRegistry aufgeloest, damit
 *    Module lose gekoppelt bleiben.
 *  - Aus jedem Sub-Subject werden CORE-Facts uebernommen (das Wesentliche);
 *    QUALIFYING-Facts nur, wenn Recipe->sources es zulaesst (verbose=true).
 *  - Facts werden mit dem Namen des Sub-Subjects praefixiert, damit die Prosa
 *    weiss, welches Projekt / welche Quelle gemeint ist.
 *  - Nature bleibt beim Fact erhalten — der Recipe-Filter (include_natures) im
 *    Verbalizer entscheidet weiterhin, was in die Prosa rutscht.
 */
class EntityPulseSubjectCollector implements SubjectCollectorInterface
{
    private const DEFAULT_SOURCES = [
        'descend' => false,          // Rekursion durch den Entity-Baum. false | true | int (max depth).
                                     // true = alle Descendants; int = bis zu N Ebenen tief.
                                     // Wirkt auf ALLE Sub-Sammlungen (Signals, Planner, Registry-Metriken).
        'signals' => true,           // Signal-Sub-Subject einbeziehen
        'planner_projects' => [      // Alle angelinkten Planner-Projekte (qualitative Text-Facts)
            'enabled' => true,
            'top_n' => 8,            // max. wie viele Projekte einbeziehen
            'skip_if_no_movement' => false, // wenn true: bei $since Projekte ohne CORE-Movement-Facts skippen
        ],
        'entity_link_providers' => [ // Quantitative KPI-Facts aus EntityLinkRegistry
            'enabled' => true,
            'include' => null,       // null = alle registrierten. Sonst weisse Liste von morph-Aliasen.
            'exclude' => [],         // schwarze Liste zusaetzlich zu include.
            'per_alias' => [],       // per-alias Overrides (aktuell ungenutzt, reserviert).
            'metrics' => [
                'dimensions' => null,   // null = alle. z.B. ['throughput', 'quality'] fuer Wochenbericht.
                'types' => null,        // null = alle. z.B. ['flow', 'modulator'] fuer Bewegungs-Report.
            ],
            'skip_zero' => true,     // Metriken mit Wert 0 nicht als Fact aufnehmen (Kompaktheit).
        ],
        'verbose' => false,          // wenn true: auch QUALIFYING-Facts aus Sub-Collectoren uebernehmen
    ];

    public function __construct(protected SubjectCollectorRegistry $registry) {}

    public function handles(): string
    {
        return 'entity_pulse';
    }

    public function collectState(
        mixed $subject,
        ?CollectionRecipe $recipe = null,
        ?\DateTimeInterface $since = null,
    ): Subject {
        $entityId = $this->resolveEntityId($subject);
        $entityName = $this->resolveEntityName($entityId);

        $sources = $recipe ? $recipe->sources : self::DEFAULT_SOURCES;
        $isOn = fn (string $key) => $this->sourceOn($sources, $key);
        $cfg = fn (string $key) => $this->sourceCfg($sources, $key);

        // Rekursions-Scope: Root + optional Descendants. Wirkt auf alle Sub-Sammlungen.
        $descend = $sources['descend'] ?? false;
        $entityScope = $this->collectEntityScope($entityId, $descend);

        $facts = [];

        // Signal-Sub-Subject aggregieren — nutzt Entity-Scope wenn descend gesetzt.
        if ($isOn('signals')) {
            $signalsSubject = $this->safeCollect(
                'organization_signals',
                count($entityScope) > 1 ? $entityScope : $entityId,
                null,
                $since,
            );
            if ($signalsSubject) {
                $facts = array_merge($facts, $this->extractHighlights(
                    $signalsSubject,
                    labelPrefix: 'Signale',
                    verbose: (bool) ($sources['verbose'] ?? false),
                ));
            }
        }

        // Planner-Projekte aggregieren — sammelt IDs ueber das gesamte Entity-Scope.
        if ($isOn('planner_projects')) {
            $ppCfg = $cfg('planner_projects');
            $topN = (int) ($ppCfg['top_n'] ?? 8);
            $skipIdle = (bool) ($ppCfg['skip_if_no_movement'] ?? false);

            foreach ($this->plannerProjectIdsForEntities($entityScope, $topN) as $projectId) {
                $projectSubject = $this->safeCollect('planner_project', $projectId, null, $since);
                if (! $projectSubject) {
                    continue;
                }
                if ($skipIdle && $since && ! $this->hasMovementFacts($projectSubject)) {
                    continue;
                }
                $facts = array_merge($facts, $this->extractHighlights(
                    $projectSubject,
                    labelPrefix: 'Projekt "' . $projectSubject->identity->primaryName . '"',
                    verbose: (bool) ($sources['verbose'] ?? false),
                ));
            }
        }

        // Registry-Metriken aggregieren (14+ Provider) — ueber das gesamte Entity-Scope.
        // Liefert Facts UND den aggregierten Metric-Bag; letzterer wird fuer
        // Delta-Facts (vs. Baseline) und den Snapshot nach dem Refresh gebraucht.
        $metricsBag = [];
        if ($isOn('entity_link_providers')) {
            [$metricFacts, $metricsBag] = $this->collectMetricFacts(
                $entityId,
                $entityScope,
                $cfg('entity_link_providers'),
            );
            $facts = array_merge($facts, $metricFacts);
        }

        // Delta-Facts gegen Baseline — nur wenn die Recipe ein Fenster deklariert.
        // Beispiel: Recipe mit since_window="7d" → Delta gegen den Snapshot vor 7 Tagen.
        if ($recipe && $recipe->sinceWindowDays() > 0 && ! empty($metricsBag)) {
            $facts = array_merge(
                $facts,
                $this->factsFromDelta((string) $entityId, $metricsBag, $recipe),
            );
        }

        // Wenn nach Filter nichts uebrig: sag es ehrlich statt zu halluzinieren.
        if (empty($facts) && $since) {
            $facts[] = new Fact(
                FactPriority::CORE,
                'Seit dem letzten Bericht keine berichtenswerten Bewegungen oder Signale.',
                'pulse.no_movement',
                FactNature::DERIVATION,
            );
        }

        $now = new DateTimeImmutable();
        return new Subject(
            kind: SubjectKind::STATE,
            type: 'entity_pulse',
            id: (string) $entityId,
            identity: new Identity(
                primaryName: 'Pulse: ' . $entityName,
                shortLabel: $entityName,
                slug: (string) $entityId,
            ),
            facts: $facts,
            edges: [],
            freshness: new Freshness(source: DataSource::LIVE, asOf: $now),
            // Metric-Bag im meta mitliefern — der FeedService nutzt ihn nach dem
            // erfolgreichen Output fuer BaselineService::snapshot().
            meta: [
                'metrics_bag' => $metricsBag,
            ],
        );
    }

    protected function resolveEntityId(mixed $subject): int
    {
        if (is_int($subject)) return $subject;
        if (is_string($subject)) return (int) $subject;
        if (is_object($subject) && isset($subject->id)) return (int) $subject->id;
        throw new \InvalidArgumentException('EntityPulseSubjectCollector: entity_id nicht auflösbar.');
    }

    protected function resolveEntityName(int $entityId): string
    {
        if (! \Schema::hasTable('organization_entities')) {
            return 'Entity #' . $entityId;
        }
        $row = DB::table('organization_entities')->where('id', $entityId)->first(['name']);
        return $row?->name ?? ('Entity #' . $entityId);
    }

    /**
     * IDs aller planner_project-Objekte, die per dimension_link an mindestens einer
     * der uebergebenen Entities haengen (Root + optional Descendants).
     *
     * @param int[] $entityIds
     * @return int[]
     */
    protected function plannerProjectIdsForEntities(array $entityIds, int $limit): array
    {
        if (empty($entityIds)
            || ! \Schema::hasTable('organization_dimension_links')
            || ! \Schema::hasTable('organization_dimension_values')) {
            return [];
        }
        $rows = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(v.metadata, '$.source_entity_id')) AS UNSIGNED)"), $entityIds)
            ->whereIn('l.linkable_type', ['project', 'planner_project'])
            ->select('l.linkable_id')
            ->distinct()
            ->limit($limit)
            ->pluck('l.linkable_id')
            ->all();
        return array_map('intval', $rows);
    }

    /**
     * Traversiert den Entity-Baum breitenfirst und liefert alle Entity-IDs im Scope.
     * $descend: false = nur Root; true = alle Descendants; int = bis zu N Ebenen tief.
     *
     * @return int[]  Root + Descendants (dedupliziert).
     */
    protected function collectEntityScope(int $rootId, mixed $descend): array
    {
        if ($descend === false || $descend === null) {
            return [$rootId];
        }
        if (! \Schema::hasTable('organization_entities')) {
            return [$rootId];
        }
        $maxDepth = ($descend === true) ? null : max(0, (int) $descend);

        $visited = [$rootId => true];
        $result = [$rootId];
        $queue = [[$rootId, 0]];

        while (! empty($queue)) {
            [$id, $depth] = array_shift($queue);
            if ($maxDepth !== null && $depth >= $maxDepth) {
                continue;
            }
            $children = DB::table('organization_entities')
                ->where('parent_entity_id', $id)
                ->pluck('id')
                ->all();
            foreach ($children as $cid) {
                $cid = (int) $cid;
                if (isset($visited[$cid])) {
                    continue;
                }
                $visited[$cid] = true;
                $result[] = $cid;
                $queue[] = [$cid, $depth + 1];
            }
        }
        return $result;
    }

    /**
     * Ruft einen Sub-Collector auf; schluckt Fehler (fehlende Registry / broken Model),
     * damit der Pulse-Bericht auch bei partieller Fehlfunktion durchgeht.
     */
    protected function safeCollect(string $type, mixed $id, ?CollectionRecipe $recipe, ?\DateTimeInterface $since): ?Subject
    {
        try {
            $collector = $this->registry->resolve($type);
            if (! $collector) {
                return null;
            }
            return $collector->collectState($id, $recipe, $since);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return Fact[]
     */
    protected function extractHighlights(Subject $sub, string $labelPrefix, bool $verbose): array
    {
        $out = [];
        foreach ($sub->facts as $f) {
            if ($f->priority === FactPriority::CORE || ($verbose && $f->priority === FactPriority::QUALIFYING)) {
                $out[] = new Fact(
                    priority: $f->priority,
                    text: $labelPrefix . ' — ' . $f->text,
                    sourceCode: 'pulse:' . $sub->type . '#' . $sub->id . '/' . ($f->sourceCode ?? ''),
                    nature: $f->nature,
                );
            }
        }
        return $out;
    }

    protected function hasMovementFacts(Subject $sub): bool
    {
        foreach ($sub->facts as $f) {
            if ($f->nature === FactNature::MOVEMENT) {
                return true;
            }
        }
        return false;
    }

    protected function sourceOn(array $sources, string $key): bool
    {
        $v = $sources[$key] ?? null;
        if (is_bool($v)) return $v;
        if (is_array($v)) return (bool) ($v['enabled'] ?? true);
        return false;
    }

    protected function sourceCfg(array $sources, string $key): array
    {
        $v = $sources[$key] ?? null;
        return is_array($v) ? $v : [];
    }

    /**
     * Baut Facts aus den `metrics()`-Ausgaben aller EntityLinkProvider, die per
     * DimensionLink an der Entity haengen. Provider ohne registrierten Handler
     * werden geskippt (keine Metriken verfuegbar). Metriken werden pro Alias
     * berechnet, dann pro Key ein Fact — mit Nature-Mapping aus dem `basis`-Feld
     * der Metric-Definition:
     *   - basis=window_* oder cumulative_since_start → MOVEMENT
     *   - basis=modulator_factor              → DERIVATION
     *   - basis=stichtag oder unbekannt       → STATE
     *
     * Priority folgt der `direction`:
     *   - down (Warnsignal)   → CORE
     *   - up   (Erfolg)       → QUALIFYING
     *   - neutral / unbekannt → CONTEXT
     *
     * Recipe filtert per include/exclude, sowie `metrics.dimensions` und
     * `metrics.types`. Standard: alle Aliases, alle Dimensionen, alle Typen.
     * Werte == 0 werden per `skip_zero=true` uebersprungen (Kompaktheit).
     *
     * Aggregiert Metriken pro Provider und liefert BOTH Facts UND den globalen
     * Metric-Bag (Key → aggregierter Wert). Der Bag wird fuer Delta-Rechnung
     * gegen die Baseline und fuer den nachfolgenden Snapshot gebraucht.
     *
     * @param int[] $entityScope
     * @return array{0: Fact[], 1: array<string, float|int>}
     */
    protected function collectMetricFacts(int $rootEntityId, array $entityScope, array $cfg): array
    {
        return $this->factsFromEntityLinkMetrics($rootEntityId, $entityScope, $cfg);
    }

    /**
     * @param int[] $entityScope
     * @return array{0: Fact[], 1: array<string, float|int>}
     */
    protected function factsFromEntityLinkMetrics(int $rootEntityId, array $entityScope, array $cfg): array
    {
        $registry = $this->entityLinkRegistry();
        if (! $registry) {
            return [[], []];
        }

        // Links pro Alias, aufgeschluesselt nach Entity — damit der Provider pro
        // Sub-Entity rechnet und wir das Ergebnis anschliessend gem. der
        // roll_up_function der Metric aggregieren (sum / avg / max / min).
        $linksByAliasAndEntity = $this->linksByAliasByEntityForEntities($entityScope);
        if (empty($linksByAliasAndEntity)) {
            return [[], []];
        }

        $include = $cfg['include'] ?? null;
        $exclude = (array) ($cfg['exclude'] ?? []);
        $metricsCfg = (array) ($cfg['metrics'] ?? []);
        $allowDims = $metricsCfg['dimensions'] ?? null;
        $allowTypes = $metricsCfg['types'] ?? null;
        $skipZero = (bool) ($cfg['skip_zero'] ?? true);

        $allDefs = $this->safeAllMetricDefinitions($registry);
        $linkTypeConfig = $this->safeLinkTypeConfig($registry);

        $facts = [];
        $bag = [];
        foreach ($linksByAliasAndEntity as $alias => $idsPerEntity) {
            if ($include !== null && ! in_array($alias, (array) $include, true)) {
                continue;
            }
            if (in_array($alias, $exclude, true)) {
                continue;
            }

            $provider = $registry->getProvider($alias);
            if (! $provider) {
                continue;
            }

            try {
                // Provider rechnet pro Entity — wir aggregieren danach ehrlich per
                // roll_up_function der Metric-Definition (sum / avg / max / min).
                // Modulatoren (Raten, Modulator-Faktoren) landen so nicht in einer
                // Bag-Rechnung, sondern als Mittelwert ueber die Sub-Entities.
                $metrics = $provider->metrics($alias, $idsPerEntity);
            } catch (\Throwable $e) {
                continue; // Provider-Fehler killt den Pulse-Bericht nicht.
            }
            if (empty($metrics)) {
                continue;
            }

            $rolledUp = $this->rollUpMetrics($metrics, $allDefs);
            if (empty($rolledUp)) {
                continue;
            }

            $aliasLabel = $linkTypeConfig[$alias]['label'] ?? $alias;

            foreach ($rolledUp as $key => $value) {
                // Bag befuellen — unabhaengig von skip_zero/Filter, damit Baseline
                // vollstaendig snapshot-baar bleibt.
                if (is_numeric($value)) {
                    $bag[$alias . '.' . $key] = (float) $value;
                }

                if ($skipZero && (is_numeric($value) && (float) $value === 0.0)) {
                    continue;
                }
                $def = $allDefs[$key] ?? null;
                if (! $def) {
                    continue; // Keine Metric-Definition → skip (keine Semantik).
                }
                if ($allowDims && ! in_array($def['dimension'] ?? null, (array) $allowDims, true)) {
                    continue;
                }
                if ($allowTypes && ! in_array($def['type'] ?? null, (array) $allowTypes, true)) {
                    continue;
                }

                $facts[] = new Fact(
                    priority: $this->metricPriority($def),
                    text: $this->formatMetricFactText($aliasLabel, $def, $value),
                    sourceCode: 'pulse:metrics:' . $alias . '.' . $key,
                    nature: $this->metricNature($def),
                );
            }
        }

        return [$facts, $bag];
    }

    /**
     * Delta-Facts aus dem aktuellen Metric-Bag gegen die letzte Baseline.
     * Nur Metriken mit signifikantem Delta (0.5% oder ganze Zaehler) fliessen ein.
     *
     * @param  array<string, float> $currentBag
     * @return Fact[]
     */
    protected function factsFromDelta(string $subjectId, array $currentBag, CollectionRecipe $recipe): array
    {
        $days = $recipe->sinceWindowDays();
        if ($days <= 0 || empty($currentBag)) {
            return [];
        }

        try {
            /** @var \Platform\Core\Verbalization\Baseline\BaselineService $svc */
            $svc = app(\Platform\Core\Verbalization\Baseline\BaselineService::class);
            $deltas = $svc->deltaFor('entity_pulse', $subjectId, $days, $currentBag);
        } catch (\Throwable $e) {
            return [];
        }
        if (empty($deltas)) {
            return [];
        }

        $window = $recipe->sinceWindow;
        $facts = [];
        foreach ($deltas as $key => $d) {
            $delta = (float) $d['delta'];
            $baseline = (float) $d['baseline'];
            // Skip triviale Nullen und minimale Rauschbewegungen.
            if ($delta === 0.0) {
                continue;
            }
            if ($baseline != 0.0 && abs($delta) < 0.5 && abs($d['delta_pct'] ?? 0) < 0.5) {
                continue;
            }

            $arrow = $delta > 0 ? '↑' : '↓';
            $pct = $d['delta_pct'] !== null ? ' (' . sprintf('%+.1f', $d['delta_pct']) . ' %)' : '';
            $facts[] = new Fact(
                priority: FactPriority::CORE,
                text: sprintf(
                    'Delta %s (%s): %s %s → %s%s',
                    $key,
                    $window,
                    $this->formatNumber($baseline),
                    $arrow,
                    $this->formatNumber((float) $d['current']),
                    $pct,
                ),
                sourceCode: 'pulse:delta:' . $key,
                nature: FactNature::MOVEMENT,
            );
        }
        return $facts;
    }

    protected function formatNumber(float $v): string
    {
        if ((float) (int) $v === $v) {
            return (string) (int) $v;
        }
        return number_format($v, 1, ',', '.');
    }

    protected function entityLinkRegistry(): ?\Platform\Organization\Services\EntityLinkRegistry
    {
        try {
            return app(\Platform\Organization\Services\EntityLinkRegistry::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Erweiterte Variante: Links pro Alias UND pro Sub-Entity aufgeschluesselt,
     * damit der Provider pro Entity rechnet und wir die Metriken korrekt
     * roll-up-en (sum vs avg vs max ...).
     *
     * @param int[] $entityIds
     * @return array<string, array<int, int[]>>  morph-Alias → [entity_id → [linkable_ids]]
     */
    protected function linksByAliasByEntityForEntities(array $entityIds): array
    {
        if (empty($entityIds)
            || ! \Schema::hasTable('organization_dimension_links')
            || ! \Schema::hasTable('organization_dimension_values')) {
            return [];
        }
        $rows = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(v.metadata, '$.source_entity_id')) AS UNSIGNED)"), $entityIds)
            ->select(
                'l.linkable_type',
                'l.linkable_id',
                DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(v.metadata, '$.source_entity_id')) AS UNSIGNED) as source_entity_id"),
            )
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $eid = (int) $row->source_entity_id;
            $out[$row->linkable_type][$eid][] = (int) $row->linkable_id;
        }
        // Dedup pro (alias, entity_id).
        foreach ($out as $alias => $byEntity) {
            foreach ($byEntity as $eid => $ids) {
                $out[$alias][$eid] = array_values(array_unique($ids));
            }
        }
        return $out;
    }

    /**
     * Aggregiert pro-Entity-Metrik-Werte gemaess der roll_up_function der
     * jeweiligen Metric-Definition:
     *   - sum (default)   → einfache Summe
     *   - avg             → Mittelwert (fuer Modulatoren wie Raten)
     *   - max / min       → Extrem-Werte
     *
     * @param array<int, array<string, mixed>> $metricsByEntity
     * @param array<string, array>              $defs
     * @return array<string, float|int>
     */
    protected function rollUpMetrics(array $metricsByEntity, array $defs): array
    {
        $bucket = [];
        foreach ($metricsByEntity as $entityId => $values) {
            if (! is_array($values)) {
                continue;
            }
            foreach ($values as $key => $value) {
                if (! is_numeric($value)) {
                    continue;
                }
                $bucket[$key][] = (float) $value;
            }
        }

        $result = [];
        foreach ($bucket as $key => $values) {
            $fn = $defs[$key]['roll_up_function'] ?? 'sum';
            $result[$key] = match ($fn) {
                'avg' => count($values) > 0 ? round(array_sum($values) / count($values), 2) : 0,
                'max' => ! empty($values) ? max($values) : 0,
                'min' => ! empty($values) ? min($values) : 0,
                default => array_sum($values), // sum
            };
        }
        return $result;
    }

    /**
     * @param int[] $entityIds
     * @return array<string, int[]>  morph-Alias → [linkable_id, ...] (dedupliziert ueber Baum).
     */
    protected function linksByAliasForEntities(array $entityIds): array
    {
        if (empty($entityIds)
            || ! \Schema::hasTable('organization_dimension_links')
            || ! \Schema::hasTable('organization_dimension_values')) {
            return [];
        }
        $rows = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(v.metadata, '$.source_entity_id')) AS UNSIGNED)"), $entityIds)
            ->select('l.linkable_type', 'l.linkable_id')
            ->distinct()
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->linkable_type][] = (int) $row->linkable_id;
        }
        // Deduplizieren pro Alias (falls dieselbe ID von mehreren Sub-Entities gefunden wird).
        foreach ($out as $alias => $ids) {
            $out[$alias] = array_values(array_unique($ids));
        }
        return $out;
    }

    protected function safeAllMetricDefinitions($registry): array
    {
        try {
            return $registry->allMetricDefinitions();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function safeLinkTypeConfig($registry): array
    {
        try {
            return $registry->allLinkTypeConfig();
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function metricNature(array $def): FactNature
    {
        $basis = $def['basis'] ?? null;
        if (is_string($basis) && str_starts_with($basis, 'window_')) {
            return FactNature::MOVEMENT;
        }
        return match ($basis) {
            'modulator_factor' => FactNature::DERIVATION,
            'cumulative_since_start' => FactNature::MOVEMENT,
            default => FactNature::STATE,
        };
    }

    protected function metricPriority(array $def): FactPriority
    {
        return match ($def['direction'] ?? 'neutral') {
            'down' => FactPriority::CORE,     // Warnsignal — prominent
            'up' => FactPriority::QUALIFYING, // positiver Erfolg — sekundaer
            default => FactPriority::CONTEXT, // neutral — Kontext
        };
    }

    protected function formatMetricFactText(string $aliasLabel, array $def, mixed $value): string
    {
        $label = $def['label'] ?? '';
        $unit = $def['unit'] ?? null;
        $formatted = $this->formatMetricValue($value, $unit);
        return "{$aliasLabel} — {$label}: {$formatted}";
    }

    protected function formatMetricValue(mixed $value, ?string $unit): string
    {
        if (is_bool($value)) {
            return $value ? 'ja' : 'nein';
        }
        if (! is_numeric($value)) {
            return (string) $value;
        }
        return match ($unit) {
            'count', 'points' => (string) (int) $value,
            'percentage' => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',') . ' %',
            'days' => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',') . ' Tage',
            'minutes' => (string) (int) $value . ' Min.',
            'score' => (string) round((float) $value, 1),
            default => is_int($value + 0) && (float) $value === (float) (int) $value
                ? (string) (int) $value
                : number_format((float) $value, 1, ',', '.'),
        };
    }
}
