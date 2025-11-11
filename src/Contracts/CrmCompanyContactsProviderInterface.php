<?php

namespace Platform\Core\Contracts;

interface CrmCompanyContactsProviderInterface
{
    /**
     * Liefert eine Liste von Kontakten für eine Company.
     * Erwartetes Rückgabeformat: [["id"=>int, "name"=>string, "email"=>string|null, "position"=>string|null, "is_primary"=>bool], ...]
     */
    public function contacts(?int $companyId): array;
}

