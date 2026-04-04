<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;

class UpdateTerminalChannelTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.channels.PATCH';
    }

    public function getDescription(): string
    {
        return 'Aktualisiert einen Terminal-Channel (Name, Beschreibung, Icon). '
            . 'Nur Channel-Owner können Änderungen vornehmen. '
            . 'Felder die nicht übergeben werden, bleiben unverändert. '
            . 'Icon auf null setzen um es zu entfernen.';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name des Channels.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung. Leerer String um zu entfernen.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues Emoji-Icon. Null oder leerer String um zu entfernen.',
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

        if ($channel->isDm()) {
            return ToolResult::error('DMs können nicht bearbeitet werden.', 'INVALID_CHANNEL_TYPE');
        }

        // Only owners can update
        $isOwner = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner) {
            return ToolResult::error('Nur Channel-Owner können den Channel bearbeiten.', 'FORBIDDEN');
        }

        $updated = [];

        if (array_key_exists('name', $arguments) && ! empty(trim($arguments['name']))) {
            $channel->name = trim($arguments['name']);
            $updated[] = 'name';
        }

        if (array_key_exists('description', $arguments)) {
            $channel->description = ! empty(trim($arguments['description'])) ? trim($arguments['description']) : null;
            $updated[] = 'description';
        }

        if (array_key_exists('icon', $arguments)) {
            $channel->icon = ! empty($arguments['icon']) ? $arguments['icon'] : null;
            $updated[] = 'icon';
        }

        if (empty($updated)) {
            return ToolResult::error('Keine Änderungen angegeben (name, description oder icon).', 'VALIDATION_ERROR');
        }

        $channel->save();

        return ToolResult::success([
            'channel_id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'icon' => $channel->icon,
            'updated_fields' => $updated,
        ]);
    }
}
