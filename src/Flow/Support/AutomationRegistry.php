<?php

namespace Platform\Core\Flow\Support;

use Platform\Core\Flow\Contracts\Automation;

/**
 * Sammelstelle aller code-deklarierten Automationen des Event→Reaktion-Gewebes.
 * Core registriert Defaults beim Boot; jedes Modul (inbox, drip, planner, …) hängt
 * seine eigenen über app(AutomationRegistry::class)->register(...) dazu — ohne das
 * Gewebe anzufassen.
 */
class AutomationRegistry
{
    /** @var array<int, Automation> */
    protected array $automations = [];

    public function register(Automation $automation): void
    {
        $this->automations[] = $automation;
    }

    /**
     * Alle Automationen, die auf diese Event-Klasse reagieren.
     *
     * @return array<int, Automation>
     */
    public function for(string $eventClass): array
    {
        return array_values(array_filter(
            $this->automations,
            fn (Automation $a) => $a->handles() === $eventClass,
        ));
    }
}
