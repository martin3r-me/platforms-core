<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CrmContactOptionsProviderInterface;

class NullCrmContactOptionsProvider implements CrmContactOptionsProviderInterface
{
    public function options(?string $query = null, int $limit = 20): array
    {
        return [];
    }
}

