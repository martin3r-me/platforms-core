<?php

namespace Platform\Core\Services;

use Platform\Core\Models\Team;
use Platform\Core\Models\ToolCatalog;
use Platform\Core\Models\ToolExecution;
use Platform\Core\Tools\ToolRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ToolCatalogService
{
    public function __construct(
        private ToolRegistry $registry,
    ) {}

    /**
     * Build catalog for a single team
     */
    public function buildForTeam(Team $team, int $limit = 250, int $periodDays = 30): void
    {
        $since = now()->subDays($periodDays);

        $rows = ToolExecution::query()
            ->where('team_id', $team->id)
            ->where('success', true)
            ->where('created_at', '>', $since)
            ->select('tool_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('tool_name')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get();

        $catalog = $rows->map(function ($row) {
            $desc = null;
            try {
                $tool = $this->registry->get($row->tool_name);
                $desc = $tool?->getDescription();
            } catch (\Throwable $e) {
                // Tool not in registry
            }

            return [
                'tool' => $row->tool_name,
                'desc' => $desc ? mb_substr($desc, 0, 120) : null,
                'count' => (int) $row->cnt,
            ];
        })->all();

        ToolCatalog::updateOrCreate(
            ['team_id' => $team->id],
            [
                'catalog' => $catalog,
                'tool_count' => count($catalog),
                'period_days' => $periodDays,
                'built_at' => now(),
            ]
        );
    }

    /**
     * Get catalog for a team
     */
    public function getForTeam(Team $team): array
    {
        $entry = ToolCatalog::where('team_id', $team->id)->first();

        if (!$entry) {
            return [];
        }

        return [
            'catalog' => $entry->catalog ?? [],
            'meta' => [
                'count' => $entry->tool_count,
                'built_at' => $entry->built_at?->toIso8601String(),
                'period_days' => $entry->period_days,
            ],
        ];
    }

    /**
     * Rebuild catalogs for all active teams
     */
    public function rebuildAll(int $limit = 250, int $periodDays = 30): int
    {
        $since = now()->subDays($periodDays);

        $teamIds = ToolExecution::query()
            ->where('created_at', '>', $since)
            ->distinct()
            ->pluck('team_id')
            ->filter()
            ->all();

        $count = 0;
        foreach ($teamIds as $teamId) {
            $team = Team::find($teamId);
            if (!$team) {
                continue;
            }

            try {
                $this->buildForTeam($team, $limit, $periodDays);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('[ToolCatalog] Build fehlgeschlagen', [
                    'team_id' => $teamId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
