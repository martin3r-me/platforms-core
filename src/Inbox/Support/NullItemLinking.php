<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\ItemLinking;

/**
 * Default-Naht: keine manuelle Zuordnung möglich (No-op), solange kein Quell-Modul
 * eine echte ItemLinking-Impl bindet.
 */
class NullItemLinking implements ItemLinking
{
    public function assign(int $itemId, int $anchorId): void {}

    public function unassign(int $itemId): void {}
}
