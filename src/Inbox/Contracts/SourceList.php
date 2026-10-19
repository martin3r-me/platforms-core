<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Listen-Naht je Kanal: der Triage-Kern stellt EINEN konsolidierten Stream bereit,
 * aber die kanal-spezifische Listen-Form (Meeting-Serie falten, Mail-Thread falten,
 * Sender-Label, Zeit) liefert das Quell-Modul. Es implementiert pro Kanal einen
 * SourceList-Provider und registriert ihn in der SourceListRegistry.
 *
 * Symmetrisch zu SourceDetail (Detail-Naht). Zusammen ersetzen sie die bisherigen
 * Inbox*QueryContracts: Consumer (home) lesen Liste UND Detail über die eine
 * Core-InboxQuery.
 */
interface SourceList
{
    /** Kanal, den dieser Provider bedient: 'mail' | 'meeting' | 'call' | … */
    public function channel(): string;

    /**
     * Offene Items dieses Kanals (bereits kanal-spezifisch geformt/gefaltet).
     * Jede Zeile trägt mindestens 'id' = inbox_item-ID.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listForUser(int $userId, int $teamId, int $limit): array;
}
