<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolRegistryEntry;
use Platform\Core\Models\ToolRegistryTag;
use Platform\Core\Models\ToolRegistryRequires;
use Platform\Core\Models\ToolExecution;
use Platform\Core\Tools\ToolRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToolRegistryService
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolPermissionService $permissionService,
    ) {}

    /**
     * Suche nach Tools mit lexikalischem Scoring.
     *
     * @param string $query Natürlichsprachliche Suchanfrage
     * @param array  $filters {namespace?, tier?, cost_class?, kind?, deprecated?, name_glob?}
     * @param int    $limit Max Ergebnisse
     * @return array Token-sparendes Response-Format
     */
    public function search(string $query = '', array $filters = [], int $limit = 5): array
    {
        $builder = ToolRegistryEntry::query()->with(['tags', 'requires']);

        // Filter anwenden
        $this->applyFilters($builder, $filters);

        // ILIKE-Kandidaten holen (bei leerer Query: alle mit Filtern)
        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($query) . '%'])
                  ->orWhereRaw('LOWER(intent) LIKE ?', ['%' . mb_strtolower($query) . '%'])
                  ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ['%' . mb_strtolower($query) . '%'])
                  ->orWhereHas('tags', function ($tq) use ($query) {
                      $tq->whereRaw('LOWER(tag) LIKE ?', ['%' . mb_strtolower($query) . '%']);
                  });
            });
        }

        // name_glob Filter (z.B. "obsidian.*" oder "*.bulk.POST")
        if (!empty($filters['name_glob'])) {
            $glob = $filters['name_glob'];
            // Konvertiere Glob zu SQL LIKE Pattern
            $pattern = str_replace(['*', '?'], ['%', '_'], $glob);
            $builder->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($pattern)]);
        }

        // Großzügig laden, dann per PHP scoren und kappen
        $candidates = $builder->limit(100)->get();

        // Scoring
        $scored = [];
        foreach ($candidates as $entry) {
            $score = 0;

            if ($query !== '') {
                $score = $entry->matchesQuery($query);
            }

            // Usage-Boost (normalisiert: max +3)
            $usageBoost = min(3, (int) floor(($entry->usage_30d ?? 0) / 10));
            $score += $usageBoost;

            // Lazy-Validierung: Tool muss im ToolRegistry existieren + Permission
            if (!$this->registry->has($entry->name)) {
                continue;
            }
            if (!$this->permissionService->hasAccess($entry->name)) {
                continue;
            }

            $scored[] = ['entry' => $entry, 'score' => $score];
        }

        // Nach Score sortieren (absteigend)
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Limit anwenden
        $scored = array_slice($scored, 0, $limit);

        return array_map(fn($item) => $this->formatCompact($item['entry']), $scored);
    }

    /**
     * Einzelnes Tool laden (volle Details).
     */
    public function get(string $name): ?array
    {
        $entry = ToolRegistryEntry::with(['tags', 'requires'])
            ->where('name', $name)
            ->first();

        if (!$entry) {
            return null;
        }

        // Lazy-Validierung
        if (!$this->registry->has($entry->name)) {
            return null;
        }

        return $this->formatFull($entry);
    }

    /**
     * Tools auflisten mit Filtern.
     */
    public function list(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $builder = ToolRegistryEntry::query()->with(['tags', 'requires']);
        $this->applyFilters($builder, $filters);

        $total = $builder->count();

        $entries = $builder
            ->orderByDesc('usage_30d')
            ->orderBy('name')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Lazy-Validierung
        $results = [];
        foreach ($entries as $entry) {
            if (!$this->registry->has($entry->name)) {
                continue;
            }
            if (!$this->permissionService->hasAccess($entry->name)) {
                continue;
            }
            $results[] = $this->formatCompact($entry);
        }

        return [
            'tools' => $results,
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
        ];
    }

    /**
     * Erstellt oder aktualisiert einen Registry-Eintrag.
     */
    public function upsert(array $data): ToolRegistryEntry
    {
        $name = $data['name'] ?? null;
        if (!$name) {
            throw new \InvalidArgumentException('name ist erforderlich.');
        }

        // Beim Schreiben: Tool muss im ToolRegistry existieren
        if (!$this->registry->has($name)) {
            throw new \InvalidArgumentException("Tool '{$name}' ist nicht im ToolRegistry registriert.");
        }

        $tags = $data['tags'] ?? null;
        $requires = $data['requires'] ?? null;
        unset($data['tags'], $data['requires']);

        $entry = ToolRegistryEntry::updateOrCreate(
            ['name' => $name],
            $data
        );

        // Tags synchronisieren
        if ($tags !== null) {
            $entry->tags()->delete();
            foreach ((array) $tags as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $entry->tags()->create(['tag' => $tag]);
                }
            }
        }

        // Requires synchronisieren
        if ($requires !== null) {
            $entry->requires()->delete();
            foreach ((array) $requires as $req) {
                if (is_array($req) && !empty($req['tool'])) {
                    $entry->requires()->create([
                        'required_tool_name' => $req['tool'],
                        'for_param' => $req['for_param'] ?? null,
                    ]);
                }
            }
        }

        return $entry->fresh(['tags', 'requires']);
    }

    /**
     * Batch-Upsert, atomar via Transaction.
     */
    public function bulkUpsert(array $items): array
    {
        $results = [];

        DB::transaction(function () use ($items, &$results) {
            foreach ($items as $data) {
                try {
                    $results[] = [
                        'name' => $data['name'] ?? '(unknown)',
                        'ok' => true,
                        'entry' => $this->upsert($data),
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'name' => $data['name'] ?? '(unknown)',
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return $results;
    }

    /**
     * Aktualisiert usage_7d/30d/90d aus der tool_executions Tabelle.
     */
    public function syncUsageStats(): void
    {
        try {
            $periods = [
                'usage_7d' => 7,
                'usage_30d' => 30,
                'usage_90d' => 90,
            ];

            foreach ($periods as $column => $days) {
                $since = now()->subDays($days);

                $stats = ToolExecution::query()
                    ->where('success', true)
                    ->where('created_at', '>', $since)
                    ->select('tool_name', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('tool_name')
                    ->get()
                    ->keyBy('tool_name');

                // Alle Einträge auf 0 setzen, dann bekannte updaten
                ToolRegistryEntry::query()->update([$column => 0]);

                foreach ($stats as $toolName => $row) {
                    ToolRegistryEntry::where('name', $toolName)
                        ->update([$column => (int) $row->cnt]);
                }
            }

            Log::debug('[ToolRegistry] Usage-Stats synchronisiert');
        } catch (\Throwable $e) {
            Log::warning('[ToolRegistry] Usage-Sync fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // --- Private Helpers ---

    private function applyFilters($builder, array $filters): void
    {
        if (!empty($filters['namespace'])) {
            $builder->where('namespace', $filters['namespace']);
        }
        if (!empty($filters['tier'])) {
            $builder->where('tier', $filters['tier']);
        }
        if (!empty($filters['cost_class'])) {
            $builder->where('cost_class', $filters['cost_class']);
        }
        if (!empty($filters['kind'])) {
            $builder->where('kind', $filters['kind']);
        }
        if (isset($filters['deprecated'])) {
            $builder->where('deprecated', (bool) $filters['deprecated']);
        } else {
            // Standardmäßig keine deprecaten Tools anzeigen
            $builder->where('deprecated', false);
        }
    }

    /**
     * Token-sparendes Kompaktformat für Search-Results.
     */
    private function formatCompact(ToolRegistryEntry $entry): array
    {
        $result = [
            'name' => $entry->name,
            'intent' => $entry->intent,
            'kind' => $entry->kind,
            'tier' => $entry->tier,
            'cost_class' => $entry->cost_class,
            'namespace' => $entry->namespace,
            'read_only' => $entry->read_only,
        ];

        if (!empty($entry->required_params)) {
            $result['required_params'] = array_map(
                fn($p) => ['name' => $p['name'] ?? '', 'type' => $p['type'] ?? 'string'],
                $entry->required_params
            );
        }

        if ($entry->relationLoaded('tags') && $entry->tags->isNotEmpty()) {
            $result['tags'] = $entry->tags->pluck('tag')->all();
        }

        if ($entry->relationLoaded('requires') && $entry->requires->isNotEmpty()) {
            $result['requires'] = $entry->requires->map(fn($r) => [
                'tool' => $r->required_tool_name,
                'for_param' => $r->for_param,
            ])->all();
        }

        return $result;
    }

    /**
     * Volles Format für Einzel-Abfragen.
     */
    private function formatFull(ToolRegistryEntry $entry): array
    {
        $result = $this->formatCompact($entry);
        $result['description'] = $entry->description;
        $result['module'] = $entry->module;
        $result['optional_params'] = $entry->optional_params;
        $result['deprecated'] = $entry->deprecated;
        $result['successor_name'] = $entry->successor_name;
        $result['cost_per_call_eur'] = $entry->cost_per_call_eur;
        $result['usage_7d'] = $entry->usage_7d;
        $result['usage_30d'] = $entry->usage_30d;
        $result['usage_90d'] = $entry->usage_90d;

        return $result;
    }
}
