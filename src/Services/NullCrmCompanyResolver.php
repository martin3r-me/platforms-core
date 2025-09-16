<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CrmCompanyResolverInterface;

class NullCrmCompanyResolver implements CrmCompanyResolverInterface
{
    public function displayName(?int $companyId): ?string
    {
        return null;
    }

    public function url(?int $companyId): ?string
    {
        return null;
    }
}


