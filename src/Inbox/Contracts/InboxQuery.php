<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Lese-Naht: Consumer (home-Eingang) lesen den Triage-Stream, ohne Model oder
 * Quellen direkt anzufassen. Die LISTE kommt quellfrei aus den Item-Feldern; das
 * DETAIL zieht der Kern über die SourceDetailRegistry aus dem Quell-Modul.
 */
interface InboxQuery
{
    /**
     * Offene Items eines Users (neueste zuerst), optional nach Kanal/Status gefiltert.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listForUser(int $userId, int $teamId, ?string $channel = null, ?string $status = null, int $limit = 50): array;

    /**
     * EIN konsolidierter Stream über alle Kanäle (via SourceListRegistry + Core-
     * nativem recording). Jede Zeile trägt 'channel' und 'id' (inbox_item-ID);
     * die Einheit-Konsolidierung macht der Consumer. Ersetzt die kanal-einzelnen
     * Inbox*QueryContract::listForUser-Aufrufe im home-Eingang.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listStreamForUser(int $userId, int $teamId, int $limit = 25): array;

    /**
     * Voll-Detail eines Items (nach Auswahl) — Listen-Felder + Live-Detail der Quelle.
     *
     * @return array<string,mixed>|null
     */
    public function detailForItem(int $inboxItemId): ?array;
}
