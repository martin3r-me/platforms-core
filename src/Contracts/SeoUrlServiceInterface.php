<?php

namespace Platform\Core\Contracts;

use Illuminate\Support\Collection;

interface SeoUrlServiceInterface
{
    /**
     * URL registrieren (idempotent).
     *
     * @param  array  $options  source_module, source_type, source_id, reason, priority, is_own, keywords[], fetch_immediately
     */
    public function register(int $teamId, string $url, array $options = []): array;

    /**
     * URL-Registrierung entfernen.
     */
    public function unregister(int $teamId, string $url, string $sourceModule, ?string $sourceType = null, ?int $sourceId = null): array;

    /**
     * SEO-Daten fuer eine URL.
     */
    public function getData(int $teamId, string $url): ?array;

    /**
     * SEO-Daten fuer mehrere URLs.
     */
    public function getDataBatch(int $teamId, array $urls): array;

    /**
     * Registrierte URLs eines Teams (mit Filtern).
     */
    public function getUrls(int $teamId, array $filters = []): Collection;

    /**
     * URL-Beziehungen.
     */
    public function getRelationships(int $teamId, string $url, array $types = []): array;

    /**
     * Keywords einer URL.
     */
    public function getKeywordsForUrl(int $teamId, string $url): Collection;

    /**
     * URLs die fuer ein Keyword ranken.
     */
    public function getUrlsForKeyword(int $teamId, string $keyword): Collection;

    /**
     * Datenanreicherung triggern.
     */
    public function enrich(int $teamId, ?string $url = null, array $collectors = []): array;

    /**
     * Kannibalisierungen erkennen.
     */
    public function getCannibalization(int $teamId): array;

    /**
     * Sichtbarkeits-Zusammenfassung.
     */
    public function getVisibilitySummary(int $teamId, ?string $domain = null): array;
}
