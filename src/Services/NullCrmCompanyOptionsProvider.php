<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CrmCompanyOptionsProviderInterface;

class NullCrmCompanyOptionsProvider implements CrmCompanyOptionsProviderInterface
{
    public function options(?string $query = null, int $limit = 20): array
    {
        return [];
    }
}


