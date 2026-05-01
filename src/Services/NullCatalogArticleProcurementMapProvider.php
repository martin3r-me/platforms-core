<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CatalogArticleProcurementMapProviderInterface;

class NullCatalogArticleProcurementMapProvider implements CatalogArticleProcurementMapProviderInterface
{
    public function buildMap(int $teamId): array
    {
        return [];
    }
}
