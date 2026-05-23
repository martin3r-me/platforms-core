<?php

namespace Platform\Core\Contracts;

/**
 * @deprecated Use SeoUrlServiceInterface instead. Will be removed after UI migration.
 */
interface SeoAnalysisServiceInterface
{
    public function getRankingTrends(int $projectId, int $days = 30): array;

    public function getCompetitorGaps(int $projectId): array;

    public function getVisibilityScore(int $projectId): array;

    public function getQuickWins(int $projectId): array;
}
