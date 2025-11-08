<?php

namespace Platform\Core\Contracts;

interface HasTimeAncestors
{
    /**
     * Gibt alle Vorfahren-Kontexte für die Zeitkaskade zurück.
     *
     * Jeder Eintrag im Array sollte folgende Struktur haben:
     * [
     *     'type' => 'App\Models\Project',
     *     'id' => 123,
     *     'is_root' => false, // optional, true wenn Root-Kontext
     *     'label' => 'Projekt XYZ', // optional, für Anzeige
     * ]
     *
     * @return array Array von Vorfahren-Kontexten
     */
    public function timeAncestors(): array;
}

