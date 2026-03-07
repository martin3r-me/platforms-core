<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CrmContactLinkManagerInterface;

class NullCrmContactLinkManager implements CrmContactLinkManagerInterface
{
    public function getLinkedContacts(string $linkableType, int $linkableId): array
    {
        return [];
    }

    public function syncContactLinks(string $linkableType, int $linkableId, array $selectedContactIds): void
    {
        // No-op
    }
}
