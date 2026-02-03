<?php

namespace Platform\Core\Contracts;

/**
 * Interface für Models, die einen Datei-Kontext definieren.
 *
 * Ermöglicht loose coupling für die Zuordnung von Dateien
 * zu einem gemeinsamen Kontext (z.B. alle Boards einer Location
 * teilen denselben Datei-Pool).
 */
interface HasFileContext
{
    /**
     * Der Kontext-Typ für Dateien (Model-Klasse).
     *
     * @return string
     */
    public function getFileContextType(): string;

    /**
     * Die Kontext-ID für Dateien.
     *
     * @return int
     */
    public function getFileContextId(): int;
}
