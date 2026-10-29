<?php

namespace Platform\Core\Inbox\Services;

use Platform\Core\Inbox\Contracts\InboxQuery;
use Platform\Core\Inbox\Enums\Status;
use Platform\Core\Inbox\Models\InboxItem;
use Platform\Core\Inbox\Support\SourceDetailRegistry;
use Platform\Core\Inbox\Support\SourceListRegistry;

/**
 * Lese-Implementierung: Liste quellfrei aus inbox_items, Detail über die
 * SourceDetailRegistry (jedes Quell-Modul liefert sein Kanal-Detail), und EIN
 * konsolidierter Stream über die SourceListRegistry (Kanal-Listen) + recording nativ.
 */
class StreamQuery implements InboxQuery
{
    public function __construct(
        protected SourceDetailRegistry $sourceDetails,
        protected SourceListRegistry $sourceLists,
    ) {}

    public function listForUser(int $userId, int $teamId, ?string $channel = null, ?string $status = null, int $limit = 50): array
    {
        $query = InboxItem::query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->notSnoozed()
            ->orderByDesc('received_at');

        $query->where('status', $status ?: Status::New->value);

        if ($channel) {
            $query->where('channel', $channel);
        }

        return $query->limit($limit)->get()->map(fn (InboxItem $i) => [
            'id'           => (int) $i->id,
            'channel'      => $i->channel?->value,
            'subject'      => $i->subject,
            'preview'      => $i->preview,
            'sender_label' => $i->sender_label,
            'received_at'  => $i->received_at?->toIso8601String(),
            'importance'   => $i->importance_score !== null ? (float) $i->importance_score : null,
        ])->all();
    }

    public function listStreamForUser(int $userId, int $teamId, int $limit = 25): array
    {
        $rows = [];

        // Kanäle aus den registrierten SourceList-Providern (meeting/mail/call/…).
        foreach ($this->sourceLists->all() as $channel => $provider) {
            try {
                $chRows = $provider->listForUser($userId, $teamId, $limit);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($chRows as $r) {
                $r['channel'] = $channel; // Registry-Key ist autoritativ
                $rows[] = $r;
            }
        }

        // recording ist Core-nativ (inbox_items) — kein Quell-Modul nötig.
        foreach ($this->listForUser($userId, $teamId, 'recording', null, $limit) as $r) {
            $r['channel'] = 'recording';
            $rows[] = $r;
        }

        return $rows;
    }

    public function openCountsForUser(int $userId, int $teamId): array
    {
        $rows = InboxItem::query()
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->open()
            ->notSnoozed()
            ->selectRaw('channel, COUNT(*) as cnt')
            ->groupBy('channel')
            ->pluck('cnt', 'channel')
            ->all();

        $counts = ['all' => 0];
        foreach ($rows as $channel => $cnt) {
            $key = $channel instanceof \BackedEnum ? $channel->value : (string) $channel;
            $counts[$key] = (int) $cnt;
            $counts['all'] += (int) $cnt;
        }

        return $counts;
    }

    public function detailForItem(int $inboxItemId): ?array
    {
        $item = InboxItem::find($inboxItemId);
        if (! $item) {
            return null;
        }

        $base = [
            'id'           => (int) $item->id,
            'channel'      => $item->channel?->value,
            'subject'      => $item->subject,
            'preview'      => $item->preview,
            'body'         => $item->body,
            'sender_label' => $item->sender_label,
            'received_at'  => $item->received_at?->toIso8601String(),
            'status'       => $item->status?->value,
        ];

        // Live-Detail der Quelle (Mail-Thread, Meeting-Agenda, …) — falls ein
        // Provider für den Kanal registriert ist. Sonst bleiben die Kern-Felder.
        $channel = $item->channel?->value;
        if ($channel && $this->sourceDetails->has($channel)) {
            $detail = $this->sourceDetails->detailFor($channel, $inboxItemId);
            if ($detail) {
                $base = array_merge($base, $detail);
            }
        }

        return $base;
    }
}
