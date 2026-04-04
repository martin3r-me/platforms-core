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
        return 'Erstellt einen neuen Terminal-Channel (Gruppenchat). '
            . 'Der aktuelle User wird automatisch als Owner eingetragen. '
            . 'Optional können direkt Mitglieder hinzugefügt werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Channels (z.B. "general", "projekt-alpha").',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Channels.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Emoji-Icon für den Channel (z.B. "🚀").',
                ],
                'member_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: User-IDs von Team-Mitgliedern, die direkt hinzugefügt werden sollen.',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $user = $context->user;
        $team = $context->team;

        if (! $user || ! $team) {
            return ToolResult::error('User und Team-Kontext erforderlich.', 'NO_CONTEXT');
        }

        $name = trim($arguments['name'] ?? '');
        if (empty($name)) {
            return ToolResult::error('name ist erforderlich.', 'VALIDATION_ERROR');
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
