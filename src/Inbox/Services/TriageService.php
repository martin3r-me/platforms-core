<?php

namespace Platform\Core\Inbox\Services;

use Platform\Core\Inbox\Enums\Status;
use Platform\Core\Inbox\Models\InboxItem;

/**
 * Triage-Zustandsübergänge des Kerns: Erledigt / Snooze. Wirkt auf die ganze
 * Einheit (UnitResolver: Serie/Thread via Keys ∪ verknüpfte Items via Links),
 * damit der kollabierte Listeneintrag als Ganzes verschwindet — sonst taucht
 * sofort die nächste Occurrence / das verknüpfte Item als Repräsentant wieder auf.
 *
 * Gehört in den Kern (Zustand ist der kostbare, user-eigene Teil), nicht in die
 * Präsentation (home).
 */
class TriageService
{
    public function __construct(protected UnitResolver $units) {}

    /** Erledigt: raus aus dem Eingang (Status = Done). */
    public function markDone(int $itemId): void
    {
        $this->applyToGroup($itemId, [
            'status'     => Status::Done->value,
            'handled_at' => now(),
        ]);
    }

    /** Snooze: Einheit für X Stunden ausblenden (snoozed_until in der Zukunft). */
    public function snooze(int $itemId, int $hours = 4): void
    {
        $this->applyToGroup($itemId, [
            'status'        => Status::Snoozed->value,
            'snoozed_until' => now()->addHours(max(1, $hours)),
        ]);
    }

    /**
     * @param  array<string,mixed>  $attrs
     */
    protected function applyToGroup(int $itemId, array $attrs): void
    {
        if (! InboxItem::whereKey($itemId)->exists()) {
            return;
        }

        InboxItem::query()
            ->whereIn('id', $this->units->unitIds($itemId))
            ->update($attrs);
    }
}
