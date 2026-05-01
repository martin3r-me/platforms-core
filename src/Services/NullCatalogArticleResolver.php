<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CatalogArticleResolverInterface;

class NullCatalogArticleResolver implements CatalogArticleResolverInterface
{
    public function resolve(int $productId, int $teamId): ?array
    {
        return null;
    }

    public function resolveByArticleNumber(string $articleNumber, int $teamId): ?array
    {
        return null;
    }
}
