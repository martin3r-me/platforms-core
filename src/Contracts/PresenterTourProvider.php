<?php

namespace Platform\Core\Contracts;

/**
 * Liefert dem globalen Presenter-Overlay (Core) den aktiven Tour-Schritt eines Zuschauers.
 * Implementiert vom tour-Modul; Core kennt nur diesen Contract (lose Kopplung).
 */
interface PresenterTourProvider
{
    /**
     * Aktueller Schritt des laufenden Tour-Ablaufs für diesen Zuschauer, oder null.
     *
     * @return array{title:?string,message:string,navigate:?string,speaker:string,position:int,total:int,is_last:bool}|null
     */
    public function activeStep(int $userId, int $teamId): ?array;

    /** Einen Schritt vorschalten ("Weiter"). Am Ende wird der Ablauf beendet. */
    public function advance(int $userId, int $teamId): void;

    /** Laufenden Ablauf für den Zuschauer beenden. */
    public function stop(int $userId, int $teamId): void;
}
