<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CatalogArticleResolverInterface;

class NullCatalogArticleResolver implements CatalogArticleResolverInterface
{
    public function resolve(int $articleId, int $teamId): ?array
    {
        return null;
    }
}
