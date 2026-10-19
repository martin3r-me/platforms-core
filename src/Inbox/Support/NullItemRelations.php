<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\ItemRelations;

/**
 * Default-Naht: keine Links bekannt → Einheiten gruppieren nur über Stream-Keys.
 * Wird genutzt, solange kein Quell-Modul eine echte ItemRelations-Impl bindet.
 */
class NullItemRelations implements ItemRelations
{
    public function relatedIds(int $itemId): array
    {
        return [];
    }

    public function anyFor(array $itemIds): array
    {
        return [];
    }
}
