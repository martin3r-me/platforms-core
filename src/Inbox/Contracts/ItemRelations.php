<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Die Link-Naht des Triage-Kerns: Items gehören nicht nur über gleiche Stream-Keys
 * (ical_uid / conversation_id) zusammen, sondern auch über EXPLIZITE Beziehungen
 * (eine Aufnahme ergänzt ein Meeting, eine Mail antwortet auf eine Mail …).
 *
 * Diese Beziehungen leben im Quell-Modul (inbox: inbox_item_links). Der Kern kennt
 * die Tabelle nicht — er fragt nur „welche Items gehören via Link zur selben
 * Einheit wie dieses?". Das Quell-Modul entscheidet, WELCHE Relationen
 * konsolidieren (supplements/transcript_of/reply_to = ja, references = nein).
 *
 * Ohne Implementierung (NullItemRelations) fällt der UnitResolver auf reine
 * Key-Gruppierung zurück — nichts bricht.
 */
interface ItemRelations
{
    /**
     * IDs, die über Links zur selben Einheit wie $itemId gehören (beide Richtungen),
     * OHNE $itemId selbst. Nur konsolidierende Relationen.
     *
     * @return array<int, int>
     */
    public function relatedIds(int $itemId): array;

    /**
     * Bulk-Gate: welche der übergebenen IDs haben überhaupt eine konsolidierende
     * Verknüpfung? Erlaubt Konsumenten, die teure Einheit-Auflösung auf die wenigen
     * tatsächlich verlinkten Items zu beschränken (der Rest ist seine eigene Einheit).
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, int>
     */
    public function anyFor(array $itemIds): array;
}
