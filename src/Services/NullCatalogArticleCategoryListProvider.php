<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CatalogArticleCategoryListProviderInterface;

class NullCatalogArticleCategoryListProvider implements CatalogArticleCategoryListProviderInterface
{
    public function list(int $teamId): array
    {
        return [];
    }
}
