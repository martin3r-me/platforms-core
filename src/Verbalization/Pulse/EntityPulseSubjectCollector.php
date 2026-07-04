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

        $facts = [];

        // Signal-Sub-Subject aggregieren
        if ($isOn('signals')) {
            $signalsSubject = $this->safeCollect('organization_signals', $entityId, null, $since);
            if ($signalsSubject) {
                $facts = array_merge($facts, $this->extractHighlights(
                    $signalsSubject,
                    labelPrefix: 'Signale',
                    verbose: (bool) ($sources['verbose'] ?? false),
                ));
            }
        }

        // Planner-Projekte aggregieren
        if ($isOn('planner_projects')) {
            $ppCfg = $cfg('planner_projects');
            $topN = (int) ($ppCfg['top_n'] ?? 8);
            $skipIdle = (bool) ($ppCfg['skip_if_no_movement'] ?? false);

            foreach ($this->plannerProjectIdsForEntity($entityId, $topN) as $projectId) {
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

        // Registry-Metriken aggregieren (14+ Provider, ohne pro-Modul-Code).
        if ($isOn('entity_link_providers')) {
            $facts = array_merge($facts, $this->factsFromEntityLinkMetrics(
                $entityId,
                $cfg('entity_link_providers'),
            ));
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
     * IDs aller planner_project-Objekte, die per dimension_link an dieser Entity haengen.
     *
     * @return int[]
     */
    protected function plannerProjectIdsForEntity(int $entityId, int $limit): array
    {
        if (! \Schema::hasTable('organization_dimension_links')
            || ! \Schema::hasTable('organization_dimension_values')) {
            return [];
        }
        $rows = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->where('v.metadata->source_entity_id', $entityId)
            ->whereIn('l.linkable_type', ['project', 'planner_project'])
            ->select('l.linkable_id')
            ->distinct()
            ->limit($limit)
            ->pluck('l.linkable_id')
            ->all();
        return array_map('intval', $rows);
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
     * @return Fact[]
     */
    protected function factsFromEntityLinkMetrics(int $entityId, array $cfg): array
    {
        $registry = $this->entityLinkRegistry();
        if (! $registry) {
            return [];
        }

        // Alle DimensionLinks der Entity gruppiert nach linkable_type.
        $linksByAlias = $this->linksByAliasForEntity($entityId);
        if (empty($linksByAlias)) {
            return [];
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
        foreach ($linksByAlias as $alias => $ids) {
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
                $metrics = $provider->metrics($alias, [$entityId => array_values($ids)]);
            } catch (\Throwable $e) {
                continue; // Provider-Fehler killt den Pulse-Bericht nicht.
            }
            $values = $metrics[$entityId] ?? [];
            if (empty($values)) {
                continue;
            }

            $aliasLabel = $linkTypeConfig[$alias]['label'] ?? $alias;

            foreach ($values as $key => $value) {
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

        return $facts;
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
     * @return array<string, int[]>  morph-Alias → [linkable_id, ...]
     */
    protected function linksByAliasForEntity(int $entityId): array
    {
        if (! \Schema::hasTable('organization_dimension_links')
            || ! \Schema::hasTable('organization_dimension_values')) {
            return [];
        }
        $rows = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->where('v.metadata->source_entity_id', $entityId)
            ->select('l.linkable_type', 'l.linkable_id')
            ->distinct()
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->linkable_type][] = (int) $row->linkable_id;
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
