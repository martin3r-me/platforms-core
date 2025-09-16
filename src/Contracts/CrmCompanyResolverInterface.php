<?php

namespace Platform\Core\Contracts;

interface CrmCompanyResolverInterface
{
    public function displayName(?int $companyId): ?string;
    public function url(?int $companyId): ?string;
}


