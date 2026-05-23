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
    /** Projekt erstellen (Brands erstellt eins pro Brand, SJ eins pro Team) */
    public function createProject(Team $team, User $user, array $data): ?object;

    /** Keywords dem Team hinzufuegen + an Projekt binden */
    public function attachKeywords(int $teamId, int $projectId, array $keywords): array;

    /** Metriken fuer Team-Keywords fetchen (dedupliziert) */
    public function fetchMetrics(int $teamId, ?int $projectId = null, ?User $user = null): array;

    /** Rankings fuer ein Projekt fetchen (domain-spezifisch) */
    public function fetchRankings(int $projectId, ?User $user = null): array;

    /** Keywords fuer ein Projekt abrufen */
    public function getKeywordsForProject(int $projectId): Collection;

    /** Keyword-Summary fuer ein Projekt */
    public function getKeywordSummary(int $projectId): array;
}
