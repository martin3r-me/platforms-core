<?php

namespace Platform\Core\Contracts;

interface CatalogArticleCategoryListProviderInterface
{
    /**
     * Liefert alle aktiven Artikel-Kategorie-Namen eines Teams.
     * Wird fuer die Gruppen-Validierung in Angebots-/Bestell-Positionen verwendet.
     *
     * @return array<string>
     */
    public function list(int $teamId): array;
}
