<?php

namespace Platform\Core\Contracts;

/**
 * @deprecated Nur noch vom Alt-Modul project-canvas implementiert. Kanonischer Canvas ist
 *             der Projekt-Canvas im Planner (planner.canvas.*). Entfaellt nach Deinstallation.
 */
interface CanvasOptionsProviderInterface
{
    /**
     * Liefert eine Liste von Canvas-Optionen für Auswahlfelder.
     * Erwartetes Rückgabeformat: [["value"=>int, "label"=>string], ...]
     */
    public function options(?int $teamId, ?string $query = null, int $limit = 20): array;
}
