<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CatalogListProviderInterface;

class NullCatalogListProvider implements CatalogListProviderInterface
{
    public function list(int $teamId): array
    {
        return [];
    }
}
