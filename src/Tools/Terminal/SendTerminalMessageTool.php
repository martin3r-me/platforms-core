<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Events\TerminalMessageSent;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMention;
use Platform\Core\Models\TerminalMessage;

class SendTerminalMessageTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.messages.POST';
    }

    public function getDescription(): string
    {
        return 'Sendet eine Nachricht in einen Terminal-Channel. '
            . 'Der User muss Mitglied des Channels sein. '
            . 'Unterstützt plain text und HTML. Mentions werden als User-IDs übergeben. '
            . 'Nutze terminal.channels.GET um verfügbare Channel-IDs zu sehen.';
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
                'message' => [
                    'type' => 'string',
                    'description' => 'Nachrichtentext (plain text). Wird als body_plain und body_html gespeichert.',
                ],
                'parent_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer Nachricht, auf die geantwortet wird (Thread-Reply).',
                ],
                'mention_user_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von User-IDs, die in der Nachricht erwähnt werden.',
                ],
                'attachment_file_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von ContextFile-IDs, die als Attachments an die Nachricht angehängt werden.',
                ],
            ],
            'required' => ['channel_id', 'message'],
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
        $messageText = $arguments['message'] ?? null;

        if (! $channelId || ! $messageText || empty(trim($messageText))) {
            return ToolResult::error('channel_id und message sind erforderlich.', 'VALIDATION_ERROR');
        }

        $channel = TerminalChannel::where('id', $channelId)
            ->where('team_id', $team->id)
            ->first();

        if (! $channel) {
            return ToolResult::error('Channel nicht gefunden.', 'NOT_FOUND');
        }

        // Ensure membership
        TerminalChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $user->id],
            ['role' => 'member', 'last_read_message_id' => $channel->last_message_id]
        );

        $parentId = $arguments['parent_id'] ?? null;
        $mentionUserIds = $arguments['mention_user_ids'] ?? [];
        $attachmentFileIds = $arguments['attachment_file_ids'] ?? [];

        // Wrap plain text in paragraph tags for HTML
        $bodyHtml = '<p>' . e(trim($messageText)) . '</p>';
        $bodyPlain = trim($messageText);

        $message = TerminalMessage::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body_html' => $bodyHtml,
            'body_plain' => $bodyPlain,
            'has_attachments' => ! empty($attachmentFileIds),
            'has_mentions' => ! empty($mentionUserIds),
        ]);

        // Link attachments to the message
        if (! empty($attachmentFileIds)) {
            ContextFile::whereIn('id', $attachmentFileIds)
                ->where('team_id', $team->id)
                ->update([
                    'context_type' => TerminalMessage::class,
                    'context_id' => $message->id,
                ]);
        }

        // Update channel counters
        $channel->increment('message_count');
        $channel->update(['last_message_id' => $message->id]);

        // Update parent reply count
        if ($parentId) {
            $parent = TerminalMessage::find($parentId);
            if ($parent) {
                $parent->increment('reply_count');
                $parent->update(['last_reply_at' => now()]);
            }
        }

        // Store mentions (validate user IDs to avoid FK constraint violations)
        if (! empty($mentionUserIds)) {
            $validUserIds = \Platform\Core\Models\User::whereIn('id', array_unique($mentionUserIds))->pluck('id');
            foreach ($validUserIds as $uid) {
                TerminalMention::create([
                    'message_id' => $message->id,
                    'user_id' => $uid,
                    'channel_id' => $channel->id,
                ]);
            }
        }

        // Mark as read for sender
        $member = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();
        $member?->markAsRead($message->id);

        // Broadcast
        try {
            TerminalMessageSent::dispatch($channel->id, $message->id, $user->id);
        } catch (\Throwable $e) {
            // Non-critical
        }

        return ToolResult::success([
            'message_id' => $message->id,
            'channel_id' => $channel->id,
            'body_plain' => $bodyPlain,
            'created_at' => $message->created_at->toIso8601String(),
        ]);
    }
}
