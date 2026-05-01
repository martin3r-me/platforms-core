<?php

namespace Platform\Core\Contracts;

interface CatalogListProviderInterface
{
    /**
     * Liefert die aktiven Kataloge eines Teams fuer Filter-Dropdowns.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function list(int $teamId): array;
}
