<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;

class ManageTerminalMembersTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.members.POST';
    }

    public function getDescription(): string
    {
        return 'Verwaltet Mitglieder eines Terminal-Channels. '
            . 'Aktionen: "list" (Mitglieder auflisten), "add" (Mitglied hinzufügen), "remove" (Mitglied entfernen). '
            . 'Nur Channel-Owner können Mitglieder entfernen. '
            . 'Nicht für DMs geeignet.';
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
                'action' => [
                    'type' => 'string',
                    'enum' => ['list', 'add', 'remove'],
                    'description' => 'Aktion: "list" (auflisten), "add" (hinzufügen), "remove" (entfernen).',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'User-ID des Mitglieds (erforderlich für "add" und "remove").',
                ],
            ],
            'required' => ['channel_id', 'action'],
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
        $action = $arguments['action'] ?? null;

        if (! $channelId || ! $action) {
            return ToolResult::error('channel_id und action sind erforderlich.', 'VALIDATION_ERROR');
        }

        $channel = TerminalChannel::where('id', $channelId)
            ->where('team_id', $team->id)
            ->first();

        if (! $channel) {
            return ToolResult::error('Channel nicht gefunden.', 'NOT_FOUND');
        }

        if ($channel->isDm()) {
            return ToolResult::error('Mitgliederverwaltung ist für DMs nicht verfügbar.', 'INVALID_CHANNEL_TYPE');
        }

        return match ($action) {
            'list' => $this->listMembers($channel),
            'add' => $this->addMember($channel, $arguments, $user),
            'remove' => $this->removeMember($channel, $arguments, $user),
            default => ToolResult::error("Unbekannte Aktion: {$action}", 'VALIDATION_ERROR'),
        };
    }

    private function listMembers(TerminalChannel $channel): ToolResult
    {
        $members = TerminalChannelMember::where('channel_id', $channel->id)
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($m) => [
                'user_id' => $m->user_id,
                'name' => $m->user?->name ?? 'Unbekannt',
                'email' => $m->user?->email,
                'role' => $m->role,
                'joined_at' => $m->created_at?->toIso8601String(),
            ])
            ->toArray();

        return ToolResult::success([
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'members' => $members,
            'count' => count($members),
        ]);
    }

    private function addMember(TerminalChannel $channel, array $arguments, $user): ToolResult
    {
        $targetUserId = $arguments['user_id'] ?? null;
        if (! $targetUserId) {
            return ToolResult::error('user_id ist erforderlich für action=add.', 'VALIDATION_ERROR');
        }

        $membership = TerminalChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => (int) $targetUserId],
            ['role' => 'member', 'last_read_message_id' => $channel->last_message_id]
        );

        return ToolResult::success([
            'channel_id' => $channel->id,
            'user_id' => (int) $targetUserId,
            'action' => 'added',
            'was_already_member' => ! $membership->wasRecentlyCreated,
        ]);
    }

    private function removeMember(TerminalChannel $channel, array $arguments, $user): ToolResult
    {
        $targetUserId = $arguments['user_id'] ?? null;
        if (! $targetUserId) {
            return ToolResult::error('user_id ist erforderlich für action=remove.', 'VALIDATION_ERROR');
        }

        // Only owners can remove
        $isOwner = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner) {
            return ToolResult::error('Nur Channel-Owner können Mitglieder entfernen.', 'FORBIDDEN');
        }

        if ((int) $targetUserId === $user->id) {
            return ToolResult::error('Du kannst dich nicht selbst entfernen.', 'VALIDATION_ERROR');
        }

        $deleted = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', (int) $targetUserId)
            ->delete();

        return ToolResult::success([
            'channel_id' => $channel->id,
            'user_id' => (int) $targetUserId,
            'action' => 'removed',
            'was_member' => $deleted > 0,
        ]);
    }
}
