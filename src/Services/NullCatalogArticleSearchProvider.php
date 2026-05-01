<?php

namespace Platform\Core\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\CatalogArticleSearchProviderInterface;

class NullCatalogArticleSearchProvider implements CatalogArticleSearchProviderInterface
{
    public function search(int $teamId, string $query, int $limit = 20, ?int $catalogId = null): Collection
    {
        return collect();
    }
}
