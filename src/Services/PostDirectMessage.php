<?php

namespace Platform\Core\Services;

use Platform\Core\Events\TerminalMessageSent;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMessage;

/**
 * Postet eine Direktnachricht (DM) an EINEN User. Findet den 1:1-DM-Kanal
 * (participant_hash zwischen Absender und Empfänger) oder legt ihn an, stellt
 * beide Mitglieder sicher, erstellt die Nachricht und broadcastet.
 *
 * Für einmalige Meldungen OHNE Kontext-Thread — z. B. „✅ Aufgabe erledigt" an
 * den Verantwortlichen, wenn es keinen Rückfrage-Thread gibt (dort lohnt kein
 * Thread). Gegenstück zu PostContextMessage (Dialog im Entity-Kontext).
 */
class PostDirectMessage
{
    public function post(int $teamId, int $senderId, int $recipientId, string $body): ?TerminalMessage
    {
        // Kein Empfänger / keine DM an sich selbst → nichts tun (still).
        if ($recipientId <= 0 || $recipientId === $senderId) {
            return null;
        }

        $userIds = [$senderId, $recipientId];
        $hash = TerminalChannel::makeParticipantHash($userIds);

        $channel = TerminalChannel::where('team_id', $teamId)
            ->where('participant_hash', $hash)
            ->first();

        if (! $channel) {
            $channel = TerminalChannel::create([
                'team_id' => $teamId,
                'type' => 'dm',
                'participant_hash' => $hash,
            ]);
        }

        // Beide Mitglieder sicherstellen (auch bei bestehendem Kanal — robust).
        foreach ($userIds as $uid) {
            TerminalChannelMember::firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => (int) $uid],
                ['role' => 'member', 'last_read_message_id' => $channel->last_message_id]
            );
        }

        $bodyPlain = trim($body);
        $message = TerminalMessage::create([
            'channel_id' => $channel->id,
            'user_id' => $senderId,
            'body_html' => '<p>'.e($bodyPlain).'</p>',
            'body_plain' => $bodyPlain,
            'has_mentions' => false,
        ]);

        $channel->increment('message_count');
        $channel->update(['last_message_id' => $message->id]);

        // Für den Absender als gelesen markieren (Empfänger bekommt Unread = Signal).
        TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $senderId)
            ->first()?->markAsRead($message->id);

        try {
            TerminalMessageSent::dispatch($channel->id, $message->id, $senderId);
        } catch (\Throwable $e) {
            // Broadcast unkritisch — die Nachricht steht bereits.
        }

        return $message;
    }
}
