<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Eine code-deklarierte Automation: bindet einen Trigger (Engine-Event) an eine
 * Aktion — AGENT-FREI, deterministisch. Das ist der „automatische Ablauf":
 * kommt etwas rein / wird etwas verlinkt → passiert etwas Festes.
 *
 * Module registrieren ihre Automationen in der AutomationRegistry. Die Engine
 * kennt keine konkrete Automation — sie feuert nur Events, der AutomationRunner
 * führt die passenden aus. (Kluge, nicht-deterministische Abläufe laufen separat
 * über Agenten — nicht hier.)
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
