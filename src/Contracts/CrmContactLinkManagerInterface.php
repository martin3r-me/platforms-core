<?php

namespace Platform\Core\Contracts;

interface CrmContactLinkManagerInterface
{
    /**
     * Liefert verknüpfte Kontakte für ein linkable Objekt.
     * Rückgabe: [["id"=>int, "link_id"=>int, "name"=>string, "email"=>string|null], ...]
     */
    public function getLinkedContacts(string $linkableType, int $linkableId): array;

    /**
     * Synchronisiert Kontakt-Verknüpfungen: fügt neue hinzu, entfernt abgewählte.
     */
    public function syncContactLinks(string $linkableType, int $linkableId, array $selectedContactIds): void;
}
