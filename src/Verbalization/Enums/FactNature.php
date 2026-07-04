<?php

namespace Platform\Core\Verbalization\Enums;

/**
 * Nature eines Facts — orthogonal zu FactPriority.
 *
 * Priority steuert die Dramaturgie (was zuerst erzaehlen). Nature steuert
 * die inhaltliche Ebene (State, Bewegung, Ableitung). Recipes koennen ueber
 * include_natures filtern, um pro Kanal/Zweck den passenden Report-Typ zu
 * bauen (reiner State, reiner Change-Ticker, Hybrid).
 */
enum FactNature: string
{
    /**
     * Zustand: wie ist es jetzt.
     * Beispiele: Health-Score, Anzahl offener Tasks, Description-Zweck, Kostenstelle.
     */
    case STATE = 'state';

    /**
     * Bewegung: was hat sich seit dem letzten Bericht getan.
     * Beispiele: "3 Tasks erledigt seit gestern", "Slot X geschlossen",
     * "seit-Snapshot-Delta".
     */
    case MOVEMENT = 'movement';

    /**
     * Ableitung: qualitatives Fazit aus State + Bewegung.
     * Beispiele: "Ball beim Kunden", "Vertrag laeuft aus ohne Aktivitaet",
     * "Slot X vollstaendig geschlossen".
     */
    case DERIVATION = 'derivation';
}
