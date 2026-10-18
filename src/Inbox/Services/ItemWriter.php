<?php

namespace Platform\Core\Inbox\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Platform\Core\Inbox\Contracts\Delivery;
use Platform\Core\Inbox\Enums\Channel;
use Platform\Core\Inbox\Enums\Status;
use Platform\Core\Inbox\Events\ItemDelivered;
use Platform\Core\Inbox\Models\InboxItem;
use Platform\Core\Models\User;

/**
 * Die Delivery-Naht: schreibt ein strukturiertes Item in den Eingang des Users
 * und feuert den Trigger ItemDelivered. Bewusst schlank — kein Connector-Kram,
 * keine Anreicherung; System-Items sind bereits strukturiert.
 */
class ItemWriter implements Delivery
{
    public function deliver(array $payload): ?int
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $teamId = (int) ($payload['team_id'] ?? 0);
        $subject = trim((string) ($payload['subject'] ?? ''));

        if (! $userId || ! $teamId || $subject === '') {
            return null;
        }

        [$sourceType, $sourceId] = $this->resolveSource($payload, $userId);

        // Optionale Deduplizierung: ein Item je (User, Quelle).
        if (! empty($payload['dedupe'])) {
            $existing = InboxItem::query()
                ->where('user_id', $userId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existing) {
                return (int) $existing->id;
            }
        }

        $body = $payload['body'] ?? null;
        $channel = (string) ($payload['channel'] ?? Channel::System->value);
        $receivedAt = isset($payload['received_at'])
            ? Carbon::parse($payload['received_at'])
            : now();

        $item = InboxItem::create([
            'team_id'           => $teamId,
            'user_id'           => $userId,
            'source_type'       => $sourceType,
            'source_id'         => $sourceId,
            'channel'           => $channel,
            'sender_kind'       => (string) ($payload['sender_kind'] ?? 'system'),
            'sender_label'      => (string) ($payload['sender_label'] ?? 'System'),
            'sender_identifier' => (string) ($payload['sender_identifier'] ?? ($payload['sender_label'] ?? 'system')),
            'subject'           => Str::limit($subject, 250, ''),
            'preview'           => Str::limit(trim(strip_tags((string) ($body ?? $subject))), 200),
            'body'              => $body,
            'body_format'       => 'text',
            'direction'         => 'inbound',
            'status'            => Status::New->value,
            'received_at'       => $receivedAt,
            'importance_score'  => isset($payload['importance']) ? (float) $payload['importance'] : null,
        ]);

        // Trigger feuern — Automationen/Agenten reagieren (lose, die Engine kennt sie nicht).
        ItemDelivered::dispatch(
            (int) $item->id,
            $userId,
            $teamId,
            $channel,
            $sourceType,
            $sourceId,
        );

        return (int) $item->id;
    }

    /**
     * Quelle bestimmen: Model → morph; sonst explizit; sonst der User selbst.
     *
     * @return array{0:string,1:int}
     */
    protected function resolveSource(array $payload, int $userId): array
    {
        $source = $payload['source'] ?? null;

        if ($source instanceof Model) {
            return [$source->getMorphClass(), (int) $source->getKey()];
        }

        if (! empty($payload['source_type']) && ! empty($payload['source_id'])) {
            return [(string) $payload['source_type'], (int) $payload['source_id']];
        }

        return [(new User())->getMorphClass(), $userId];
    }
}
