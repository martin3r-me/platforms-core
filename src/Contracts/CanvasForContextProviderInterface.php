<?php

namespace Platform\Core\Contracts;

interface CanvasForContextProviderInterface
{
    /**
     * Liefert alle Canvases, die an einen bestimmten Kontext gebunden sind.
     * Erwartetes Rückgabeformat: [["id"=>int, "name"=>string, "status"=>string, "url"=>string|null], ...]
     */
    public function forContext(string $contextType, int $contextId): array;
}
