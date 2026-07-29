<?php

namespace Platform\Core\Services;

use Platform\Core\Events\TerminalMessageSent;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMention;
use Platform\Core\Models\TerminalMessage;
use Platform\Core\Models\User;

/**
 * Postet eine Nachricht in den Context-Channel einer Entity (Thread im Kontext).
 *
 * Findet den Channel (type=context, context_type/context_id) oder legt ihn an,
 * stellt die Mitglieder sicher, erstellt die Nachricht, erwähnt (@mention) die
 * genannten User und broadcastet. Wiederverwendbar für Menschen wie Agenten —
 * z. B. Worker-Rückfragen, die den Ersteller einer Aufgabe erwähnen.
 */
class PostContextMessage
{
    /**
     * @param  int[]  $memberIds  Zusätzlich zum Absender als Channel-Mitglieder sicherstellen.
     * @param  int[]  $mentionUserIds  User, die erwähnt (benachrichtigt) werden.
     */
    public function post(
        int $teamId,
        string $contextType,
        int $contextId,
        ?string $contextName,
        int $senderId,
        string $body,
        array $memberIds = [],
        array $mentionUserIds = [],
    ): TerminalMessage {
        $channel = TerminalChannel::forTeam($teamId)
            ->forContext($contextType, $contextId)
            ->first();

        if (! $channel) {
            $channel = TerminalChannel::create([
                'team_id' => $teamId,
                'type' => 'context',
                'context_type' => $contextType,
                'context_id' => $contextId,
                'name' => $contextName,
                'created_by_user_id' => $senderId,
            ]);
        }

        // Mitglieder sicherstellen (Absender + genannte, z. B. der Ersteller).
        foreach (array_unique(array_merge([$senderId], $memberIds)) as $uid) {
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
            'has_mentions' => ! empty($mentionUserIds),
        ]);

        $channel->increment('message_count');
        $channel->update(['last_message_id' => $message->id]);

        // Mentions (nur existierende User → keine FK-Verletzung).
        if (! empty($mentionUserIds)) {
            $valid = User::whereIn('id', array_unique(array_map('intval', $mentionUserIds)))->pluck('id');
            foreach ($valid as $uid) {
                TerminalMention::create([
                    'message_id' => $message->id,
                    'user_id' => $uid,
                    'channel_id' => $channel->id,
                ]);
            }
        }

        // Für den Absender als gelesen markieren.
        TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $senderId)
            ->first()?->markAsRead($message->id);

        try {
            TerminalMessageSent::dispatch($channel->id, $message->id, $senderId);
        } catch (\Throwable $e) {
            // Broadcast ist unkritisch — die Nachricht steht bereits.
        }

        return $message;
    }
}
