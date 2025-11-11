<?php

namespace Platform\Core\Contracts;

interface CrmContactOptionsProviderInterface
{
    /**
     * Liefert eine Liste von Kontaktoptionen für Auswahlfelder.
     * Erwartetes Rückgabeformat: [["value"=>int, "label"=>string], ...]
     */
    public function options(?string $query = null, int $limit = 20): array;
}

