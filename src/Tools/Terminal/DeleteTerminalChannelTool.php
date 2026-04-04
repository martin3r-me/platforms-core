<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMention;
use Platform\Core\Models\TerminalMessage;

class DeleteTerminalChannelTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.channels.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht einen Terminal-Channel inkl. aller Nachrichten und Mitgliedschaften. '
            . 'Nur Channel-Owner können löschen. DMs können von beiden Teilnehmern gelöscht werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'channel_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu löschenden Channels.',
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

        // Permission check
        if ($channel->type === 'channel') {
            $isOwner = TerminalChannelMember::where('channel_id', $channel->id)
                ->where('user_id', $user->id)
                ->where('role', 'owner')
                ->exists();

            if (! $isOwner) {
                return ToolResult::error('Nur Channel-Owner können Channels löschen.', 'FORBIDDEN');
            }
        } else {
            // DMs: any participant can delete
            $isMember = TerminalChannelMember::where('channel_id', $channel->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $isMember) {
                return ToolResult::error('Du bist kein Mitglied dieses Channels.', 'FORBIDDEN');
            }
        }

        $name = $channel->name ?? 'DM';
        $messageCount = $channel->message_count;

        // Delete all related data
        $messageIds = TerminalMessage::where('channel_id', $channel->id)->pluck('id');
        TerminalMention::whereIn('message_id', $messageIds)->delete();
        TerminalMessage::where('channel_id', $channel->id)->delete();
        TerminalChannelMember::where('channel_id', $channel->id)->delete();
        $channel->delete();

        return ToolResult::success([
            'deleted_channel_id' => $channelId,
            'name' => $name,
            'messages_deleted' => $messageCount,
        ]);
    }
}
