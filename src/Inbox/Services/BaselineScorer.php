<?php

namespace Platform\Core\Inbox\Services;

use Platform\Core\Inbox\Contracts\Scorer;
use Platform\Core\Inbox\Models\InboxItem;

/**
 * Deterministische Basis-Bewertung — bewusst schlank und domänen-rein (keine
 * VIP-/Carrier-Signale, die hingen an Fremddaten). Etabliert die Naht; reichere
 * Signale kommen später als überschreibende Implementierung.
 *
 *  +1.0  eingehend (inbound)
 *  +0.5  frisch (< 24h)
 */
class BaselineScorer implements Scorer
{
    public function score(InboxItem $item): float
    {
        $score = 0.0;

        if (($item->direction ?? 'inbound') === 'inbound') {
            $score += 1.0;
        }

        if ($item->received_at && $item->received_at->gt(now()->subDay())) {
            $score += 0.5;
        }

        return $score;
    }
}
