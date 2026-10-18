<?php

namespace Platform\Core\Inbox\Automations;

use Platform\Core\Inbox\Contracts\Automation;
use Platform\Core\Inbox\Contracts\Scorer;
use Platform\Core\Inbox\Events\ItemDelivered;
use Platform\Core\Inbox\Models\InboxItem;

/**
 * Durchverdrahteter Beweis-Ablauf (Phase 1): kommt ein Item rein → Score setzen.
 * Deterministisch, agent-frei, self-contained in Core. Zeigt die Kette
 * Trigger (ItemDelivered) → Aktion (Scorer) → Zustand (importance_score).
 *
 * Cross-Modul-Automationen (Meeting→Zeit, Rechnung→Kontierung) folgen demselben
 * Muster, nutzen aber ToolAutomation (Trigger → Tool im Besitzer-Modul).
 */
class ScoreOnDeliveryAutomation implements Automation
{
    public function __construct(
        protected Scorer $scorer,
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
        $item = InboxItem::find($event->itemId);
        if (! $item) {
            return;
        }

        $item->forceFill([
            'importance_score'     => $this->scorer->score($item),
            'importance_scored_at' => now(),
        ])->save();
    }
}
