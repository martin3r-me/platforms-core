<?php

namespace Platform\Core\Contracts;

interface CanvasOptionsProviderInterface
{
    /**
     * Liefert eine Liste von Canvas-Optionen für Auswahlfelder.
     * Erwartetes Rückgabeformat: [["value"=>int, "label"=>string], ...]
     */
    public function options(?int $teamId, ?string $query = null, int $limit = 20): array;
}
