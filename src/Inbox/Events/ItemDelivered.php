<?php

namespace Platform\Core\Inbox\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Trigger: ein Item ist in der Inbox gelandet. Abonnenten (Automationen,
 * Agenten) reagieren — die Engine selbst zeigt nach keinem.
 */
class ItemDelivered
{
    use Dispatchable;

    public function __construct(
        public readonly int $itemId,
        public readonly int $userId,
        public readonly int $teamId,
        public readonly string $channel,
        public readonly ?string $sourceType = null,
        public readonly ?int $sourceId = null,
    ) {}
}
