<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;

class CreateTerminalChannelTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.channels.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt einen neuen Terminal-Channel (Gruppenchat) oder eine DM. '
            . 'Für Channels: name ist erforderlich, User wird Owner. '
            . 'Für DMs: type="dm" und target_user_id setzen. '
            . 'DMs werden dedupliziert (existierende DM wird zurückgegeben).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['channel', 'dm'],
                    'description' => 'Typ: "channel" (Gruppenchat, Standard) oder "dm" (Direktnachricht).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Channels (erforderlich für type=channel, ignoriert bei DMs).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Channels.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Emoji-Icon für den Channel (z.B. "🚀").',
                ],
                'target_user_id' => [
                    'type' => 'integer',
                    'description' => 'User-ID des DM-Partners (erforderlich für type=dm).',
                ],
                'member_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: User-IDs für Channels (ignoriert bei DMs).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $user = $context->user;
        $team = $context->team;

        if (! $user || ! $team) {
            return ToolResult::error('User und Team-Kontext erforderlich.', 'NO_CONTEXT');
        }

        $type = $arguments['type'] ?? 'channel';

        if ($type === 'dm') {
            return $this->createDm($arguments, $user, $team);
        }

        return $this->createChannel($arguments, $user, $team);
    }

    private function createDm(array $arguments, $user, $team): ToolResult
    {
        $targetUserId = $arguments['target_user_id'] ?? null;
        if (! $targetUserId) {
            return ToolResult::error('target_user_id ist erforderlich für type=dm.', 'VALIDATION_ERROR');
        }

        if ((int) $targetUserId === $user->id) {
            return ToolResult::error('Du kannst keine DM an dich selbst senden.', 'VALIDATION_ERROR');
        }

        $userIds = [$user->id, (int) $targetUserId];
        $hash = TerminalChannel::makeParticipantHash($userIds);

        // Find existing DM
        $channel = TerminalChannel::where('team_id', $team->id)
            ->where('participant_hash', $hash)
            ->first();

        $wasExisting = (bool) $channel;

        if (! $channel) {
            $channel = TerminalChannel::create([
                'team_id' => $team->id,
                'type' => 'dm',
                'participant_hash' => $hash,
            ]);

            foreach ($userIds as $uid) {
                TerminalChannelMember::create([
                    'channel_id' => $channel->id,
                    'user_id' => $uid,
                    'role' => 'member',
                ]);
            }
        }

        return ToolResult::success([
            'channel_id' => $channel->id,
            'type' => 'dm',
            'target_user_id' => (int) $targetUserId,
            'was_existing' => $wasExisting,
        ]);
    }

    private function createChannel(array $arguments, $user, $team): ToolResult
    {
        $name = trim($arguments['name'] ?? '');
        if (empty($name)) {
            return ToolResult::error('name ist erforderlich für type=channel.', 'VALIDATION_ERROR');
        }

        $channel = TerminalChannel::create([
            'team_id' => $team->id,
            'type' => 'channel',
            'name' => $name,
            'description' => ! empty($arguments['description']) ? trim($arguments['description']) : null,
            'icon' => $arguments['icon'] ?? null,
        ]);

        // Creator becomes owner
        TerminalChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Add members
        $memberIds = $arguments['member_ids'] ?? [];
        $addedCount = 0;
        foreach ($memberIds as $memberId) {
            if ((int) $memberId === $user->id) {
                continue;
            }
            TerminalChannelMember::firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => (int) $memberId],
                ['role' => 'member']
            );
            $addedCount++;
        }

        return ToolResult::success([
            'channel_id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'icon' => $channel->icon,
            'member_count' => 1 + $addedCount,
        ]);
    }
}
