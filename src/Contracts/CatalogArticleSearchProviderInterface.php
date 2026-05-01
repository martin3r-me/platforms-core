<?php

namespace Platform\Core\Contracts;

use Illuminate\Support\Collection;

interface CatalogArticleSearchProviderInterface
{
    /**
     * Sucht Artikel eines Teams anhand von Name, Art-Nr. oder sonstigen Feldern.
     *
     * @return Collection<int, array{id: int, article_number: ?string, name: string, gebinde: ?string, ek: float, vk: float, mwst: ?string}>
     */
    public function search(int $teamId, string $query, int $limit = 20): Collection;
}
