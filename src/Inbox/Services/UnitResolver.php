<?php

namespace Platform\Core\Inbox\Services;

use Illuminate\Support\Collection;
use Platform\Core\Inbox\Contracts\ItemRelations;
use Platform\Core\Inbox\Models\InboxItem;

/**
 * Die „Einheit" (Hülle): mehrere Inbox-Items, die EINE reale Sache sind — ein
 * Meeting samt Aufnahme, ein Mail-Thread, eine Termin-Serie. Berechnet (nicht
 * gespeichert) als transitive Hülle über Stream-Keys (ical_uid / conversation_id)
 * ∪ explizite Links (ItemRelations). Ein ANKER ist das Gesicht der Einheit.
 *
 * Kanal-blind: dieselbe Mechanik gilt für jede Quelle. Die Frage „welche Items
 * gehören via Link zusammen" beantwortet die ItemRelations-Naht (Quell-Modul);
 * die Frage „welche via gleichem Stream" beantworten die Item-Felder selbst.
 *
 * Nutzer: TriageService (Erledigt/Snooze auf die ganze Einheit), die Eingang-
 * Konsolidierung (2→1 Zeile) und das Durchreichen von Kontext in den Org-Baum.
 */
class UnitResolver
{
    /** Kanal-Priorität für die Anker-Wahl (kleiner = eher das Gesicht). */
    protected const CHANNEL_PRIORITY = [
        'meeting'   => 0,
        'call'      => 1,
        'mail'      => 2,
        'message'   => 3,
        'ticket'    => 4,
        'task'      => 5,
        'recording' => 6,
        'system'    => 7,
    ];

    public function __construct(protected ItemRelations $relations) {}

    /**
     * Alle Item-IDs der Einheit: transitive Hülle über Stream-Keys ∪ Links.
     *
     * @return array<int, int>
     */
    public function unitIds(int $itemId): array
    {
        $seen = [];
        $queue = [$itemId];

        while ($queue) {
            $id = (int) array_pop($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $item = InboxItem::find($id);
            if (! $item) {
                continue;
            }
            $seen[$id] = true;

            foreach ($this->keyMembers($item) as $kid) {
                if (! isset($seen[$kid])) {
                    $queue[] = $kid;
                }
            }
            foreach ($this->relations->relatedIds($id) as $rid) {
                if (! isset($seen[(int) $rid])) {
                    $queue[] = (int) $rid;
                }
            }
        }

        return array_map('intval', array_keys($seen));
    }

    /** Anker (Gesicht) der Einheit: Kanal-Priorität, dann neuestes received_at. */
    public function anchorId(int $itemId): int
    {
        $ids = $this->unitIds($itemId);
        if (count($ids) <= 1) {
            return $itemId;
        }

        return $this->pickAnchor(InboxItem::whereIn('id', $ids)->get(), $itemId);
    }

    public function isAnchor(int $itemId): bool
    {
        return $this->anchorId($itemId) === $itemId;
    }

    /**
     * Einheit als Struktur: Anker + Mitglieder (mit Kanal) + Facetten-Kanäle
     * (alle Mitglieder-Kanäle außer dem des Ankers).
     *
     * @return array{anchor_id:int, member_ids:array<int,int>, members:array<int,array{id:int,channel:?string}>, facet_channels:array<int,string>}
     */
    public function unitFor(int $itemId): array
    {
        $ids = $this->unitIds($itemId);
        $items = InboxItem::whereIn('id', $ids)->get();
        $anchor = $this->pickAnchor($items, $itemId);

        $members = $items->map(fn (InboxItem $it) => [
            'id'      => (int) $it->id,
            'channel' => $it->channel?->value,
        ])->values()->all();

        $facets = $items
            ->filter(fn (InboxItem $it) => (int) $it->id !== $anchor)
            ->map(fn (InboxItem $it) => $it->channel?->value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'anchor_id'      => $anchor,
            'member_ids'     => $ids,
            'members'        => $members,
            'facet_channels' => $facets,
        ];
    }

    /**
     * Konsolidierung einer präsenten Item-Menge (z.B. die Eingang-Liste): bildet
     * jedes Item auf seinen Anker ab und sammelt die Facetten je Anker. Nur Items
     * mit echten Links werden aufgelöst (ItemRelations::anyFor) — der Rest ist seine
     * eigene Einheit (Key-Gruppen falten die Quell-Contracts bereits). Performant:
     * eine Bulk-Abfrage + Einheit-Auflösung nur für die wenigen verlinkten Items.
     *
     * @param  array<int, int>  $presentIds
     * @return array{anchorOf: array<int,int>, facetsOf: array<int, array<int, array{channel:?string, inbox_id:int}>>}
     */
    public function consolidate(array $presentIds): array
    {
        $present = array_flip(array_map('intval', $presentIds));
        $toResolve = array_flip($this->relations->anyFor(array_map('intval', $presentIds)));

        $anchorOf = [];
        $facetsOf = [];

        foreach (array_keys($present) as $id) {
            if (isset($anchorOf[$id])) {
                continue;
            }
            if (! isset($toResolve[$id])) {
                $anchorOf[$id] = $id; // keine Links → eigene Einheit
                continue;
            }

            $unit = $this->unitFor($id);
            $anchor = (int) $unit['anchor_id'];
            $anchorPresent = isset($present[$anchor]);

            foreach ($unit['members'] as $m) {
                $mid = (int) $m['id'];
                if (! isset($present[$mid])) {
                    continue;
                }
                $anchorOf[$mid] = $anchorPresent ? $anchor : $mid;
                if ($anchorPresent && $mid !== $anchor) {
                    $facetsOf[$anchor][] = ['channel' => $m['channel'], 'inbox_id' => $mid];
                }
            }
        }

        return ['anchorOf' => $anchorOf, 'facetsOf' => $facetsOf];
    }

    /**
     * @param  Collection<int, InboxItem>  $items
     */
    protected function pickAnchor(Collection $items, int $fallback): int
    {
        $best = null;
        $bestKey = null;

        foreach ($items as $it) {
            $prio = self::CHANNEL_PRIORITY[$it->channel?->value] ?? 50;
            $ts = $it->received_at?->getTimestamp() ?? 0;
            $key = [$prio, -$ts]; // kleinste Priorität gewinnt, dann neuestes

            if ($bestKey === null || $key < $bestKey) {
                $bestKey = $key;
                $best = $it;
            }
        }

        return (int) ($best?->id ?? $fallback);
    }

    /** @return array<int, int> */
    protected function keyMembers(InboxItem $item): array
    {
        $col = ! empty($item->ical_uid)
            ? 'ical_uid'
            : (! empty($item->conversation_id) ? 'conversation_id' : null);

        if (! $col) {
            return [];
        }

        return InboxItem::query()
            ->where('user_id', $item->user_id)
            ->where($col, $item->{$col})
            ->pluck('id')
            ->map(fn ($i) => (int) $i)
            ->all();
    }
}
