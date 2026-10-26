<?php

namespace Platform\Core\Contracts;

/**
 * @deprecated Nur noch vom Alt-Modul project-canvas implementiert. Kanonischer Canvas ist
 *             der Projekt-Canvas im Planner (planner.canvas.*). Entfaellt nach Deinstallation.
 */
interface CanvasForContextProviderInterface
{
    /**
     * Liefert alle Canvases, die an einen bestimmten Kontext gebunden sind.
     * Erwartetes Rückgabeformat: [["id"=>int, "name"=>string, "status"=>string, "url"=>string|null], ...]
     */
    public function forContext(string $contextType, int $contextId): array;
}
