<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CrmCompanyContactsProviderInterface;

class NullCrmCompanyContactsProvider implements CrmCompanyContactsProviderInterface
{
    public function contacts(?int $companyId): array
    {
        return [];
    }
}

