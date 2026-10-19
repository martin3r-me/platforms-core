<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Manuelle Zuordnungs-Naht: ein Item einer Einheit zuordnen (z.B. eine Aufnahme
 * an einen Termin) oder die Zuordnung lösen. Der Consumer (home) nennt nur die
 * IDs — das Quell-Modul legt den Link an und reicht den Kontext in den Org-Baum
 * durch. Pendant zur automatischen LinkRule-Fabrik, hier vom Nutzer ausgelöst.
 *
 * Ohne Implementierung (NullItemLinking) ein No-op.
 */
interface ItemLinking
{
    /** $itemId der Einheit von $anchorId zuordnen (ersetzt bestehende Zuordnung). */
    public function assign(int $itemId, int $anchorId): void;

    /** Zuordnung von $itemId lösen. */
    public function unassign(int $itemId): void;
}
