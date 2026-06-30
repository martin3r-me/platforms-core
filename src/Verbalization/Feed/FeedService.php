<?php

namespace Platform\Core\Verbalization\Feed;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;
use Platform\Core\Verbalization\GuardRails;
use Platform\Core\Verbalization\Recipe\RecipeResolver;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Core\Verbalization\SubjectCollector\SubjectCollectorRegistry;
use Platform\Core\Verbalization\Verbalizer;

/**
 * Orchestriert die Feed-Pipeline:
 *
 *   1. Subjects aufloesen aus subject_selector (single / list / entity)
 *   2. Pro Subject die passende Recipe aus feed.recipes[subject_type] holen
 *   3. Den passenden Collector aus der Registry rufen
 *   4. Verbalizer aufrufen (mit Recipe, Style, Guards, ggf. Provider/Modell-Override)
 *   5. Output in verbalization_outputs persistieren (mit feed_id, timestamps)
 *
 * Entity-Selektor-Aufloesung ist optional gekoppelt — der Service nutzt
 * organization-Tabellen direkt via DB-Query (kein harter Modul-Zwang per
 * Class-Reference, sondern nur Tabellen-Existenz-Check).
 */
class FeedService
{
    public function __construct(
        protected SubjectCollectorRegistry $collectors,
        protected RecipeResolver $recipes,
        protected Verbalizer $verbalizer,
    ) {}

    /**
     * Resolviert die Subjects fuer einen Feed.
     * Output: Collection<array{type:string, id:string|int}>
     */
    public function resolveSubjects(VerbalizationFeed $feed): Collection
    {
        $selector = $feed->subject_selector ?? [];
        $mode = $selector['mode'] ?? null;
        $explicitType = $feed->subject_type;

        return match ($mode) {
            'single' => $this->resolveSingle($selector, $explicitType),
            'list' => $this->resolveList($selector, $explicitType),
            'entity' => $this->resolveByEntity($selector, $feed->recipes ?? []),
            default => collect(),
        };
    }

    protected function resolveSingle(array $selector, ?string $type): Collection
    {
        $id = $selector['id'] ?? null;
        if (! $id || ! $type) {
            return collect();
        }
        return collect([['type' => $type, 'id' => $id]]);
    }

    protected function resolveList(array $selector, ?string $type): Collection
    {
        $ids = $selector['ids'] ?? [];
        if (empty($ids) || ! $type) {
            return collect();
        }
        return collect($ids)->map(fn ($id) => ['type' => $type, 'id' => $id])->values();
    }

    /**
     * Entity-Mode: alle dimension_links der Organization-Entity, gefiltert auf
     * jene linkable_types, fuer die der Feed eine Recipe definiert hat.
     *
     * linkable_type-Mapping zu subject_type:
     *   "project"          → "planner_project"   (Planner nutzt 'project' als morph-map alias)
     *   "helpdesk_board"   → "helpdesk_board"
     *   ...
     * Wir gehen pragmatisch davon aus, dass linkable_type == subject_type, mit
     * einer kleinen Aliase-Map fuer Sonderfaelle.
     */
    protected function resolveByEntity(array $selector, array $recipesMap): Collection
    {
        $entityId = $selector['entity_id'] ?? null;
        if (! $entityId || empty($recipesMap)) {
            return collect();
        }

        // Sicherheit: Tabelle existiert?
        if (! \Schema::hasTable('organization_dimension_links')) {
            return collect();
        }

        // Welche linkable_types sind im Feed konfiguriert?
        $configuredTypes = array_keys($recipesMap);
        $aliases = $this->linkableTypeAliases();
        // Erweitere mit Aliasen — beide Richtungen, damit DB-Query stimmt.
        $dbTypes = [];
        foreach ($configuredTypes as $subjectType) {
            $dbTypes[] = $subjectType;
            $alias = array_search($subjectType, $aliases, true);
            if ($alias !== false) {
                $dbTypes[] = $alias;
            }
        }
        $dbTypes = array_values(array_unique($dbTypes));

        // dimension_links → dimension_value → metadata.source_entity_id.
        // Es gibt kein FK-Feld v.entity_id; die Entity-Verknuepfung steht im
        // JSON-Feld metadata. Wir lesen sie ueber den JSON-Pfad.
        if (! \Schema::hasTable('organization_dimension_values')) {
            return collect();
        }

        $links = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->where('v.metadata->source_entity_id', $entityId)
            ->whereIn('l.linkable_type', $dbTypes)
            ->select('l.linkable_type', 'l.linkable_id')
            ->distinct()
            ->get();

        // Fallback: alte organization_entity_links-Tabelle (deprecated, fuer Rollback noch da).
        if ($links->isEmpty() && \Schema::hasTable('organization_entity_links')) {
            $links = DB::table('organization_entity_links')
                ->where('entity_id', $entityId)
                ->whereIn('linkable_type', $dbTypes)
                ->select('linkable_type', 'linkable_id')
                ->distinct()
                ->get();
        }

        return $links->map(function ($row) use ($aliases) {
            $subjectType = $aliases[$row->linkable_type] ?? $row->linkable_type;
            return ['type' => $subjectType, 'id' => $row->linkable_id];
        })->values();
    }

    /**
     * @return array<string, string>  linkable_type → subject_type
     */
    protected function linkableTypeAliases(): array
    {
        return [
            'project' => 'planner_project',   // Planner nutzt 'project' im Morph-Map
        ];
    }

    /**
     * Generiert die Verbalisierungen fuer alle Subjects des Feeds und persistiert sie.
     *
     * @return array{outputs_created:int, subjects_resolved:int, errors:array}
     */
    public function refresh(VerbalizationFeed $feed, ?StyleProfile $baseStyle = null): array
    {
        $subjects = $this->resolveSubjects($feed);
        $errors = [];
        $created = 0;

        foreach ($subjects as $s) {
            try {
                $collector = $this->collectors->resolve($s['type']);
                if (! $collector) {
                    $errors[] = "Kein Collector fuer subject_type '{$s['type']}'.";
                    continue;
                }

                $recipeKey = $feed->recipes[$s['type']] ?? null;
                if (! $recipeKey) {
                    $errors[] = "Keine Recipe fuer subject_type '{$s['type']}' in Feed konfiguriert.";
                    continue;
                }

                $recipe = $this->recipes->resolve($recipeKey, $feed->team_id, $s['type']);
                if (! $recipe) {
                    $errors[] = "Recipe '{$recipeKey}' nicht gefunden.";
                    continue;
                }

                $subject = $collector->collectState($s['id'], $recipe);
                $result = $this->verbalizer->verbalize(
                    subject: $subject,
                    style: $baseStyle ?? StyleProfile::formal(),
                    rails: new GuardRails(),
                    providerKey: $feed->llm_provider,
                    modelOverride: $feed->llm_model,
                    recipe: $recipe,
                );

                VerbalizationOutput::create([
                    'feed_id' => $feed->id,
                    'recipe_key' => $recipe->key,
                    'subject_type' => $subject->type,
                    'subject_id' => (string) $subject->id,
                    'subject_label' => $subject->identity->primaryName,
                    'prose' => $result->prose,
                    'llm_provider' => $result->meta['provider'] ?? null,
                    'llm_model' => $result->model,
                    'input_tokens' => $result->usage['input_tokens'] ?? null,
                    'output_tokens' => $result->usage['output_tokens'] ?? null,
                    'team_id' => $feed->team_id,
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::warning('[FeedService] verbalize failed', [
                    'feed_id' => $feed->id,
                    'subject' => $s,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "{$s['type']}#{$s['id']}: " . $e->getMessage();
            }
        }

        $feed->last_refreshed_at = now();
        $feed->save();

        // Retention: alte Outputs ueber retention_items hinaus loeschen
        $this->enforceRetention($feed);

        return [
            'outputs_created' => $created,
            'subjects_resolved' => $subjects->count(),
            'errors' => $errors,
        ];
    }

    protected function enforceRetention(VerbalizationFeed $feed): void
    {
        $limit = (int) ($feed->retention_items ?? 50);
        if ($limit <= 0) {
            return;
        }
        // Bei "snapshot"-Strategy: pro Subject nur das juengste aufheben
        if ($feed->item_strategy === 'snapshot') {
            $latestIds = DB::table('verbalization_outputs as a')
                ->where('a.feed_id', $feed->id)
                ->whereRaw('a.created_at = (
                    SELECT MAX(b.created_at) FROM verbalization_outputs b
                    WHERE b.feed_id = a.feed_id
                      AND b.subject_type = a.subject_type
                      AND b.subject_id = a.subject_id
                )')
                ->pluck('a.id');
            VerbalizationOutput::where('feed_id', $feed->id)
                ->whereNotIn('id', $latestIds)
                ->delete();
            return;
        }
        // history: nur die letzten N total
        $keepIds = VerbalizationOutput::where('feed_id', $feed->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('id');
        VerbalizationOutput::where('feed_id', $feed->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Die Items, die der RSS-Renderer fuer einen Feed zeigen soll.
     */
    public function itemsForFeed(VerbalizationFeed $feed): Collection
    {
        $limit = (int) ($feed->retention_items ?? 50);

        if ($feed->item_strategy === 'snapshot') {
            // pro Subject nur juengster Output
            $latestIds = DB::table('verbalization_outputs as a')
                ->where('a.feed_id', $feed->id)
                ->whereRaw('a.created_at = (
                    SELECT MAX(b.created_at) FROM verbalization_outputs b
                    WHERE b.feed_id = a.feed_id
                      AND b.subject_type = a.subject_type
                      AND b.subject_id = a.subject_id
                )')
                ->pluck('a.id');
            return VerbalizationOutput::whereIn('id', $latestIds)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        return VerbalizationOutput::where('feed_id', $feed->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
