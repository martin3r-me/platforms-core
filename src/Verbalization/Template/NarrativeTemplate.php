<?php

namespace Platform\Core\Verbalization\Template;

use Platform\Core\Verbalization\Subject;

/**
 * Erzaehlvorlage pro Entity-Typ (Baustein 4 aus der Notiz).
 *
 * Verantwortlich fuer die Dramaturgie: Identitaet -> Verortung -> Charakter -> Praktisches.
 * Pro Typ eigene Implementierung — der Rest des Verbalizers ist typ-agnostisch.
 */
interface NarrativeTemplate
{
    /**
     * Welcher subject->type wird hier behandelt? (z.B. 'planner_project')
     */
    public function handles(): string;

    /**
     * Baut die deterministische Faktenbasis (Baustein 5) als strukturierten Rohtext.
     * Reihenfolge / Gruppierung / Beiwerk werden hier entschieden — bevor das LLM rankommt.
     *
     * Output ist garantiert wahr (besteht ausschliesslich aus Subject-Inhalten).
     */
    public function renderFactSheet(Subject $subject): string;
}
