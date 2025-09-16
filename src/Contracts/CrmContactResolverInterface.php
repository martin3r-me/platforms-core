<?php

namespace Platform\Core\Contracts;

interface CrmContactResolverInterface
{
    public function displayName(?int $contactId): ?string;
    public function email(?int $contactId): ?string;
    public function url(?int $contactId): ?string;
}


