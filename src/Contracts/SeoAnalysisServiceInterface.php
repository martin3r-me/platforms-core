<?php

namespace Platform\Core\Contracts;

/**
 * @deprecated Use SeoUrlServiceInterface instead. Will be removed after UI migration.
 */
interface SeoAnalysisServiceInterface
{
    public function getRankingTrends(int $teamId, int $days = 30): array;

    public function getCompetitorGaps(int $teamId): array;

    public function getVisibilityScore(int $teamId): array;

    public function getQuickWins(int $teamId): array;
}
