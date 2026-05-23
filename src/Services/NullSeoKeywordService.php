<?php

namespace Platform\Core\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\SeoKeywordServiceInterface;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * @deprecated Use NullSeoUrlService instead. Will be removed after UI migration.
 */
class NullSeoKeywordService implements SeoKeywordServiceInterface
{
    public function createProject(Team $team, User $user, array $data): ?object
    {
        return null;
    }

    public function attachKeywords(int $teamId, int $projectId, array $keywords): array
    {
        return [];
    }

    public function fetchMetrics(int $teamId, ?int $projectId = null, ?User $user = null): array
    {
        return ['fetched' => 0, 'cost_cents' => 0];
    }

    public function fetchRankings(int $teamId, ?User $user = null): array
    {
        return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0];
    }

    public function getKeywordsForProject(int $teamId): Collection
    {
        return collect();
    }

    public function getKeywordSummary(int $teamId): array
    {
        return [
            'total_keywords' => 0,
            'clusters_count' => 0,
            'avg_search_volume' => 0,
            'avg_difficulty' => 0,
            'total_search_volume' => 0,
            'intents' => [],
            'with_metrics' => 0,
            'without_metrics' => 0,
        ];
    }
}
