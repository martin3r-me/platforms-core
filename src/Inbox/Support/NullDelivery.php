<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\Delivery;

/**
 * Null-Default für den Delivery-Port (Ports & Adapters). Greift, solange kein
 * Triage-Kern eine echte Implementierung bindet — dann verpufft ein deliver()
 * still (return null), statt zu fatalen. Analog zu NullSuggestionProvider.
 */
class NullDelivery implements Delivery
{
    public function deliver(array $payload): ?int
    {
        return null;
    }
}
