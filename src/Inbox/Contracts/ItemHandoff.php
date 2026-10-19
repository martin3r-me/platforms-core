<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Handoff-Naht: aus einem Inbox-Item etwas in einem anderen Modul machen
 * (Aufgabe im Planner, Ticket im Helpdesk, künftig mehr). Der Consumer (home)
 * kennt weder das Ziel-Modul noch das Inbox-Model — er nennt nur Item-ID + Ziel.
 *
 * Das Quell-Modul implementiert die Übergabe (idempotent) und registriert sie.
 * Ohne Implementierung (NullItemHandoff) ist kein Ziel verfügbar — nichts bricht.
 */
interface ItemHandoff
{
    /** Ist das Ziel-Modul verfügbar? $target: 'task' | 'ticket' | … */
    public function available(string $target): bool;

    /** Item an das Ziel-Modul übergeben; true bei Erfolg. Idempotent. */
    public function handoff(int $itemId, string $target): bool;
}
