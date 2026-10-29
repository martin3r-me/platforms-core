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

    /** Ignorieren: dauerhaft wegdrücken (Status = Ignored), ohne „erledigt". */
    public function ignore(int $itemId): void
    {
        $this->applyToGroup($itemId, [
            'status'     => Status::Ignored->value,
            'handled_at' => now(),
        ]);
    }

    /**
     * Backlog aufräumen: ALLE offenen Items eines Users (optional nur eines Kanals)
     * in einem Rutsch auf Erledigt setzen. Ein UPDATE — auch bei tausenden Items.
     * Gibt die Zahl der betroffenen Zeilen zurück. Nicht über Undo umkehrbar
     * (bewusst: deutliche Aktion, vorher bestätigen).
     */
    public function markAllDoneForUser(int $userId, int $teamId, ?string $channel = null): int
    {
        $query = InboxItem::query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->open()
            ->notSnoozed();

        if ($channel) {
            $query->where('channel', $channel);
        }

        return $query->update([
            'status'     => Status::Done->value,
            'handled_at' => now(),
        ]);
    }

    /**
     * Rückgängig: Einheit zurück in den offenen Eingang (Status = New). Macht
     * Done/Ignored/Snooze einer frischen Triage wieder auf (Undo).
     */
    public function reopen(int $itemId): void
    {
        $this->applyToGroup($itemId, [
            'status'        => Status::New->value,
            'handled_at'    => null,
            'snoozed_until' => null,
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
