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
        'planner_projects' => [      // Alle angelinkten Planner-Projekte
            'enabled' => true,
            'top_n' => 8,            // max. wie viele Projekte einbeziehen
            'skip_if_no_movement' => false, // wenn true: bei $since Projekte ohne CORE-Movement-Facts skippen
        ],
        'verbose' => false,          // wenn true: auch QUALIFYING-Facts uebernehmen
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
}
