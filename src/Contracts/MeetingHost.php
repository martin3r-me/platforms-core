<?php

namespace Platform\Core\Contracts;

/**
 * Ein Host (Projekt, Gremium, Person, Deal …), an dem Meetings hängen können,
 * beschreibt sich selbst gegenüber dem Meeting: Icon, Link zur Quelle
 * (jump-to-source) und Typ-Label. Der Anzeigename kommt über {@see HasDisplayName}.
 *
 * Neutraler Boden: das Meeting-Modul (gathering) kennt NUR diesen Vertrag, keine
 * Host-Klassen. Jeder Host erfüllt ihn selbst — damit bleibt die Kopplung lose und
 * das Meeting host-agnostisch. Dasselbe Muster wie HasDisplayName.
 */
interface MeetingHost
{
    /** Heroicon-Name OHNE Prefix (z. B. 'briefcase'); null → generisches Icon. */
    public function meetingHostIcon(): ?string;

    /** Kanonische URL zur Quelle (jump-to-source); null → kein Link. */
    public function meetingHostUrl(): ?string;

    /** Typ-Label (z. B. 'Projekt', 'Gremium'); null → aus Typ/Klasse abgeleitet. */
    public function meetingHostTypeLabel(): ?string;
}
