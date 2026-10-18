<?php

namespace Platform\Core\Inbox\Contracts;

use Platform\Core\Inbox\Models\InboxItem;

/**
 * Pluggbare Wichtigkeits-Bewertung. Core bringt eine deterministische
 * Basis-Implementierung; Module/Features können sie überschreiben (z. B. mit
 * VIP-/Carrier-Signalen), ohne den Kern anzufassen.
 */
interface Scorer
{
    /** Score 0..n (höher = wichtiger). */
    public function score(InboxItem $item): float;
}
