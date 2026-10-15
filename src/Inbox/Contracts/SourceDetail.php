<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Live-Detail-Naht nach außen: der Triage-Kern hält die LISTE (quellfrei aus den
 * Item-Feldern), aber das VOLL-DETAIL eines Items (Mail-Thread, Meeting-Agenda,
 * Anrufdaten) lebt im Quell-Modul. Dieses implementiert pro Kanal einen
 * SourceDetail-Provider und registriert ihn in der SourceDetailRegistry.
 *
 * Damit kennt Core keine `user_connector_*`-Tabelle mehr beim Namen — es fragt
 * nur „gib mir das Detail zu diesem Item" und bekommt ein normalisiertes Array,
 * das der Reading-Pane rendert.
 *
 * Ersetzt die detailForItem()-Hälfte der bisherigen Inbox*QueryContracts.
 */
interface SourceDetail
{
    /**
     * Kanal, den dieser Provider bedient: 'mail' | 'meeting' | 'call' | …
     */
    public function channel(): string;

    /**
     * Voll-Detail zur Quelle eines Items (nach Auswahl), normalisiert als Array.
     * Null, wenn das Item nicht zu diesem Kanal gehört oder die Quelle fehlt
     * (dann bleibt der Kern bei den gecachten Listen-Feldern).
     *
     * @return array<string,mixed>|null
     */
    public function detailForItem(int $inboxItemId): ?array;
}
