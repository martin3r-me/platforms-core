<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\SeoAnalysisServiceInterface;

/**
 * @deprecated Use NullSeoUrlService instead. Will be removed after UI migration.
 */
class NullSeoAnalysisService implements SeoAnalysisServiceInterface
{
    public function getRankingTrends(int $projectId, int $days = 30): array
    {
        return [
            'period_days' => $days,
            'summary' => [
                'rising_count' => 0,
                'falling_count' => 0,
                'stable_count' => 0,
                'new_entries_count' => 0,
                'no_data_count' => 0,
            ],
            'rising' => [],
            'falling' => [],
            'stable' => [],
            'new_entries' => [],
        ];
    }

    public function getCompetitorGaps(int $projectId): array
    {
        return [
            'gaps' => [],
            'gaps_count' => 0,
            'total_keywords' => 0,
            'keywords_with_competitors' => 0,
            'top_competitor_domains' => [],
        ];
    }

    public function getVisibilityScore(int $projectId): array
    {
        return [
            'score' => 0,
            'max_score' => 0,
            'percentage' => 0,
            'keywords_with_position' => 0,
            'breakdown' => [],
        ];
    }

    public function getQuickWins(int $projectId): array
    {
        return [
            'quick_wins' => [],
            'count' => 0,
            'total_search_volume' => 0,
        ];
    }
}
