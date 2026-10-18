<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\Automation;

/**
 * Sammelstelle der code-deklarierten Automationen. Core registriert seine
 * Defaults beim Boot; Domänen-Module (organization, finance, …) hängen ihre
 * eigenen über app(AutomationRegistry::class)->register(...) dazu — ohne die
 * Engine anzufassen.
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
