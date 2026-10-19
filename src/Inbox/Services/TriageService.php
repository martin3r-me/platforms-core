<?php

namespace Platform\Core\Inbox\Services;

use Platform\Core\Inbox\Enums\Status;
use Platform\Core\Inbox\Models\InboxItem;

/**
 * Triage-Zustandsübergänge des Kerns: Erledigt / Snooze. Wirkt auf die ganze
 * Einheit (Meeting-Serie via ical_uid bzw. Mail-Thread via conversation_id), damit
 * der kollabierte Listeneintrag als Ganzes verschwindet — sonst taucht sofort die
 * nächste Occurrence / nächste Thread-Mail als Repräsentant wieder auf.
 *
 * Gehört in den Kern (Zustand ist der kostbare, user-eigene Teil), nicht in die
 * Präsentation (home).
 */
class TriageService
{
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
        $item = InboxItem::find($itemId);
        if (! $item) {
            return;
        }

        InboxItem::query()
            ->whereIn('id', $this->groupIds($item))
            ->update($attrs);
    }

    /**
     * IDs der ganzen Einheit (Serie/Thread) inkl. des Items selbst.
     *
     * @return array<int, int>
     */
    protected function groupIds(InboxItem $item): array
    {
        $col = ! empty($item->ical_uid)
            ? 'ical_uid'
            : (! empty($item->conversation_id) ? 'conversation_id' : null);

        if (! $col) {
            return [(int) $item->id];
        }

        return InboxItem::query()
            ->where('user_id', $item->user_id)
            ->where($col, $item->{$col})
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
