<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CrmContactResolverInterface;

class NullCrmContactResolver implements CrmContactResolverInterface
{
    public function displayName(?int $contactId): ?string
    {
        return null;
    }

    public function email(?int $contactId): ?string
    {
        return null;
    }

    public function url(?int $contactId): ?string
    {
        return null;
    }
}


