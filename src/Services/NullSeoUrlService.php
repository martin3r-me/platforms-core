<?php

namespace Platform\Core\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\SeoUrlServiceInterface;

class NullSeoUrlService implements SeoUrlServiceInterface
{
    public function register(int $teamId, string $url, array $options = []): array
    {
        return ['url_id' => null, 'created' => false, 'registration_id' => null];
    }

    public function unregister(int $teamId, string $url, string $sourceModule, ?string $sourceType = null, ?int $sourceId = null): array
    {
        return ['removed' => false, 'url_deleted' => false];
    }

    public function getData(int $teamId, string $url): ?array
    {
        return null;
    }

    public function getDataBatch(int $teamId, array $urls): array
    {
        return [];
    }

    public function getUrls(int $teamId, array $filters = []): Collection
    {
        return collect();
    }

    public function getRelationships(int $teamId, string $url, array $types = []): array
    {
        return [];
    }

    public function getKeywordsForUrl(int $teamId, string $url): Collection
    {
        return collect();
    }

    public function getUrlsForKeyword(int $teamId, string $keyword): Collection
    {
        return collect();
    }

    public function enrich(int $teamId, ?string $url = null, array $collectors = []): array
    {
        return ['urls_processed' => 0, 'collectors_run' => [], 'cost_cents' => 0];
    }

    public function getCannibalization(int $teamId): array
    {
        return [];
    }

    public function getVisibilitySummary(int $teamId, ?string $domain = null): array
    {
        return [
            'visibility_score' => 0,
            'total_urls' => 0,
            'total_keywords' => 0,
            'total_search_volume' => 0,
            'position_distribution' => [],
        ];
    }
}
