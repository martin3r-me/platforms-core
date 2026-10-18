<?php

namespace Platform\Core\Flow\Support;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Flow\Contracts\Automation;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * Basis für die häufigste Automation-Form: Trigger → TOOL. Verbindet ein
 * Domänen-Event mit einer bestehenden Tool-Aktion (ToolContract::execute) —
 * agent-frei. Das Muster für „automatische Abläufe": z. B. „Meeting an Knoten →
 * book-time-Tool", „Rechnung → auto-kontieren-Tool", „Task zugewiesen → deliver".
 *
 * Core nennt KEINE konkrete Tool-Klasse — die Subklasse (im Besitzer-Modul)
 * benennt ihr eigenes Tool per toolClass(). Fehlt das Tool/Modul, passiert still
 * nichts (soft-coupled).
 *
 * Subklasse definiert: handles(), toolClass(), arguments(), userId()/teamId().
 */
abstract class ToolAutomation implements Automation
{
    /** @return class-string FQN der Tool-Klasse (ToolContract). */
    abstract protected function toolClass(): string;

    /** @return array<string,mixed> Tool-Argumente aus dem Event. */
    abstract protected function arguments(object $event): array;

    abstract protected function userId(object $event): int;

    protected function teamId(object $event): ?int
    {
        return null;
    }

    public function matches(object $event): bool
    {
        return true;
    }

    public function run(object $event): void
    {
        $toolClass = $this->toolClass();
        if (! class_exists($toolClass)) {
            return; // Tool/Modul nicht installiert → kein harter Bruch
        }

        $tool = app($toolClass);
        if (! $tool instanceof ToolContract) {
            return;
        }

        $user = User::find($this->userId($event));
        if (! $user) {
            return;
        }

        $teamId = $this->teamId($event);
        $team = $teamId ? Team::find($teamId) : null;

        $tool->execute($this->arguments($event), new ToolContext($user, $team));
    }
}
