<?php

namespace Platform\Core\Contracts;

interface CatalogArticleResolverInterface
{
    /**
     * Laedt einen einzelnen Artikel mit allen fuer Events relevanten Feldern.
     *
     * @return array{id: int, name: string, category_name: ?string, description: ?string, offer_text: ?string, gebinde: ?string, ek: float, vk: float, mwst: ?string, procurement_type: ?string}|null
     */
    public function resolve(int $productId, int $teamId): ?array;
}
