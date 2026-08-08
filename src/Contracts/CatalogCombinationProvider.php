<?php

namespace Platform\Core\Contracts;

/**
 * Liefert die Vermengungs-/Exklusivitätsgruppe einer Katalog-Referenz (morphMap-Alias + id).
 * Implementiert von den Katalog-Modulen (examinations, arbmedvv); Konsumenten (z.B. encounter)
 * fragen über die CatalogCombinationRegistry, ohne die Katalog-Module hart zu kennen.
 */
interface CatalogCombinationProvider
{
    /** morphMap-Aliasse, die dieser Provider bedient, z.B. ['examination']. */
    public function supportedTypes(): array;

    /** Vermengungsgruppe der Referenz (z.B. "vorsorge"), oder null = unbekannt/frei kombinierbar. */
    public function combinationGroup(string $catalogType, int $catalogId): ?string;
}
