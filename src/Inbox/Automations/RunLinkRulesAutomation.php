<?php

namespace Platform\Core\Inbox\Automations;

use Platform\Core\Flow\Contracts\Automation;
use Platform\Core\Inbox\Events\ItemDelivered;
use Platform\Core\Inbox\Support\LinkRuleRunner;

/**
 * Trigger (ItemDelivered) → Aktion (LinkRules). Kommt ein Item über Core-Delivery
 * rein, laufen die registrierten Verknüpfungs-Regeln (Aufnahme↔Meeting, künftig
 * mehr). Agent-frei, deterministisch. Audio-Aufnahmen laufen (noch) über den
 * inbox-eigenen Ingest und rufen den LinkRuleRunner dort inline — Regeln sind
 * idempotent, daher kein Doppellauf.
 */
class RunLinkRulesAutomation implements Automation
{
    public function __construct(
        protected LinkRuleRunner $runner,
    ) {}

    public function handles(): string
    {
        return ItemDelivered::class;
    }

    public function matches(object $event): bool
    {
        return $event instanceof ItemDelivered;
    }

    public function run(object $event): void
    {
        /** @var ItemDelivered $event */
        $this->runner->run($event->itemId);
    }
}
