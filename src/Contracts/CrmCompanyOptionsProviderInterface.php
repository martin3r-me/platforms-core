<?php

namespace Platform\Core\Contracts;

interface CrmCompanyOptionsProviderInterface
{
    /**
     * Liefert eine Liste von Firmenoptionen für Auswahlfelder.
     * Erwartetes Rückgabeformat: [["value"=>int, "label"=>string], ...]
     */
    public function options(?string $query = null, int $limit = 20): array;
}


