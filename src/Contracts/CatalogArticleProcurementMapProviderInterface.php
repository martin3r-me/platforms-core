<?php

namespace Platform\Core\Contracts;

interface CatalogArticleProcurementMapProviderInterface
{
    /**
     * Baut eine Name(lower) -> procurement_type Map fuer ein Team auf.
     *
     * @return array<string, string> z.B. ['artikelname' => 'stock|supplier|kitchen']
     */
    public function buildMap(int $teamId): array;
}
