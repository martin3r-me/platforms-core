<?php

namespace Platform\Core\Flow\Contracts;

/**
 * Eine code-deklarierte Automation: bindet einen Trigger (irgendein Domänen-Event)
 * an eine Aktion — AGENT-FREI, deterministisch, EIN Hop. „Passiert X → tu Y."
 *
 * Das ist das allgemeine Event→Reaktion-Gewebe von Core — NICHT inbox-spezifisch.
 * Items, Aufgaben, Transaktionen (drip) und künftige Dinge feuern Events; Module
 * registrieren Automationen in der AutomationRegistry. Der Kern kennt keine
 * konkrete Automation — er feuert nur Events, der AutomationRunner führt die
 * passenden aus.
 *
 * Workflows ENTSTEHEN aus der Verkettung: eine Aktion feuert ein neues Event, auf
 * das die nächste Automation reagiert. Keine Workflow-Engine, kein Zustandsautomat
 * — die Kette ist emergent. (Kluge, mehrdeutige Fälle laufen über Agenten, nicht hier.)
 */
interface Automation
{
    /** FQN der Event-Klasse, auf die diese Automation reagiert. */
    public function handles(): string;

    /** Deterministische Bedingung — soll sie für dieses Event laufen? */
    public function matches(object $event): bool;

    /** Die Aktion ausführen (Service- oder Tool-Aufruf). */
    public function run(object $event): void;
}
