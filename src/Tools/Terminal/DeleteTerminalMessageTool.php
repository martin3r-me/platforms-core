<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMention;
use Platform\Core\Models\TerminalMessage;

class DeleteTerminalMessageTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.messages.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht eine oder mehrere Nachrichten aus einem Terminal-Channel. '
            . 'User können eigene Nachrichten löschen, Channel-Owner können alle Nachrichten löschen. '
            . 'Unterstützt Einzel- und Bulk-Löschung.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channel_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Terminal-Channels.',
                ],
                'message_id' => [
                    'type' => 'integer',
                    'description' => 'ID einer einzelnen Nachricht zum Löschen.',
                ],
                'message_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von Message-IDs zum Bulk-Löschen.',
                ],
            ],
            'required' => ['channel_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $user = $context->user;
        $team = $context->team;

        if (! $user || ! $team) {
            return ToolResult::error('User und Team-Kontext erforderlich.', 'NO_CONTEXT');
        }

        $channelId = $arguments['channel_id'] ?? null;
        if (! $channelId) {
            return ToolResult::error('channel_id ist erforderlich.', 'VALIDATION_ERROR');
        }

        $channel = TerminalChannel::where('id', $channelId)
            ->where('team_id', $team->id)
            ->first();

        if (! $channel) {
            return ToolResult::error('Channel nicht gefunden.', 'NOT_FOUND');
        }

        // Collect message IDs
        $messageIds = $arguments['message_ids'] ?? [];
        if (! empty($arguments['message_id'])) {
            $messageIds[] = (int) $arguments['message_id'];
        }
        $messageIds = array_unique(array_map('intval', $messageIds));

        if (empty($messageIds)) {
            return ToolResult::error('message_id oder message_ids ist erforderlich.', 'VALIDATION_ERROR');
        }

        // Check if user is channel owner
        $isOwner = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();

        $deleted = 0;
        $forbidden = 0;

        foreach ($messageIds as $msgId) {
            $message = TerminalMessage::where('id', $msgId)
                ->where('channel_id', $channel->id)
                ->first();

            if (! $message) {
                continue;
            }

            // Only own messages or owner can delete
            if ($message->user_id !== $user->id && ! $isOwner) {
                $forbidden++;
                continue;
            }

            // Delete mentions
            TerminalMention::where('message_id', $message->id)->delete();

            // Delete replies if this is a parent message
            if ($message->reply_count > 0) {
                $replyIds = TerminalMessage::where('parent_id', $message->id)->pluck('id');
                TerminalMention::whereIn('message_id', $replyIds)->delete();
                TerminalMessage::where('parent_id', $message->id)->delete();
            }

            // Update parent reply count if this is a reply
            if ($message->parent_id) {
                $parent = TerminalMessage::find($message->parent_id);
                if ($parent && $parent->reply_count > 0) {
                    $parent->decrement('reply_count');
                }
            }

            $message->delete();
            $deleted++;
        }

        // Update channel counters
        if ($deleted > 0) {
            $channel->decrement('message_count', $deleted);
            $lastMessage = TerminalMessage::where('channel_id', $channel->id)
                ->orderByDesc('id')
                ->first();
            $channel->update(['last_message_id' => $lastMessage?->id]);
        }

        return ToolResult::success([
            'channel_id' => $channel->id,
            'deleted' => $deleted,
            'forbidden' => $forbidden,
            'requested' => count($messageIds),
        ]);
    }
}
