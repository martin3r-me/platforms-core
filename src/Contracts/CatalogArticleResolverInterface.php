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

    /**
     * Laedt einen Artikel anhand seiner Artikelnummer (SKU).
     * Wird z.B. von LocationPricingApplicator verwendet, wo Pricings/Add-ons
     * per article_number auf Stammdaten verweisen.
     *
     * @return array{id: int, name: string, category_name: ?string, description: ?string, offer_text: ?string, gebinde: ?string, ek: float, vk: float, mwst: ?string, procurement_type: ?string}|null
     */
    public function resolveByArticleNumber(string $articleNumber, int $teamId): ?array;
}
