<?php

namespace Platform\Core\Contracts;

/**
 * Interface für Models, die einen anzeigbaren Namen/Titel haben.
 * 
 * Ermöglicht loose coupling für die Anzeige von Model-Namen
 * ohne direkte Abhängigkeit von spezifischen Feldnamen.
 */
interface HasDisplayName
{
    /**
     * Gibt den anzeigbaren Namen/Titel des Models zurück.
     * 
     * @return string|null
     */
    public function getDisplayName(): ?string;
}

