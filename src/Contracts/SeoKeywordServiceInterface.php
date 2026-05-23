<?php

namespace Platform\Core\Contracts;

use Illuminate\Support\Collection;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * @deprecated Use SeoUrlServiceInterface instead. Will be removed after UI migration.
 */
interface SeoKeywordServiceInterface
{
    /** Team-Settings erstellen (auto per Team) */
    public function createProject(Team $team, User $user, array $data): ?object;

    /** Keywords dem Team hinzufuegen */
    public function attachKeywords(int $teamId, int $projectId, array $keywords): array;

    /** Metriken fuer Team-Keywords fetchen (dedupliziert) */
    public function fetchMetrics(int $teamId, ?int $projectId = null, ?User $user = null): array;

    /** Rankings fuer ein Team fetchen (domain-spezifisch) */
    public function fetchRankings(int $teamId, ?User $user = null): array;

    /** Keywords fuer ein Team abrufen */
    public function getKeywordsForProject(int $teamId): Collection;

    /** Keyword-Summary fuer ein Team */
    public function getKeywordSummary(int $teamId): array;
}
