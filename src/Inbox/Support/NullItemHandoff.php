<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\ItemHandoff;

/**
 * Default-Naht: kein Ziel-Modul verfügbar. Gilt, solange kein Quell-Modul eine
 * echte ItemHandoff-Impl bindet (z.B. inbox → Planner/Helpdesk).
 */
class NullItemHandoff implements ItemHandoff
{
    public function available(string $target): bool
    {
        return false;
    }

    public function handoff(int $itemId, string $target): bool
    {
        return false;
    }
}
