<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;

class ListTerminalChannelsTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.channels.GET';
    }

    public function getDescription(): string
    {
        return 'Listet alle Terminal-Channels (Chat, Gruppenchat, Kontext) auf, in denen der aktuelle User Mitglied ist. '
            . 'Gibt DMs, Channels und Kontext-Channels zurück, jeweils mit Unread-Count und letzter Nachricht. '
            . 'Nutze channel_id aus den Ergebnissen für terminal.messages.GET oder terminal.messages.POST.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['dm', 'channel', 'context'],
                    'description' => 'Optional: Nur Channels eines bestimmten Typs zurückgeben.',
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

        $memberships = TerminalChannelMember::where('user_id', $user->id)
            ->whereHas('channel', fn ($q) => $q->where('team_id', $team->id))
            ->with(['channel' => fn ($q) => $q->with('lastMessage:id,body_plain,created_at')])
            ->get();

        $typeFilter = $arguments['type'] ?? null;
        $channels = [];

        foreach ($memberships as $membership) {
            $ch = $membership->channel;
            if (! $ch) {
                continue;
            }

            if ($typeFilter && $ch->type !== $typeFilter) {
                continue;
            }

            $unread = 0;
            if ($membership->last_read_message_id) {
                $unread = $ch->messages()
                    ->where('id', '>', $membership->last_read_message_id)
                    ->whereNull('parent_id')
                    ->count();
            } elseif ($ch->message_count > 0) {
                $unread = $ch->message_count;
            }

            $item = [
                'id' => $ch->id,
                'type' => $ch->type,
                'name' => $ch->name,
                'description' => $ch->description,
                'icon' => $ch->icon,
                'member_count' => $ch->members()->count(),
                'message_count' => $ch->message_count,
                'unread' => $unread,
                'last_message' => $ch->lastMessage?->body_plain
                    ? \Illuminate\Support\Str::limit($ch->lastMessage->body_plain, 80)
                    : null,
                'last_message_at' => $ch->lastMessage?->created_at?->toIso8601String(),
            ];

            if ($ch->type === 'dm') {
                $other = TerminalChannelMember::where('channel_id', $ch->id)
                    ->where('user_id', '!=', $user->id)
                    ->with('user:id,name')
                    ->first();
                $item['name'] = $other?->user?->name ?? 'Unbekannt';
                $item['dm_partner_id'] = $other?->user_id;
            }

            if ($ch->type === 'context') {
                $item['context_type'] = $ch->context_type;
                $item['context_id'] = $ch->context_id;
            }

            $channels[] = $item;
        }

        // Sort: unread first, then by last message
        usort($channels, fn ($a, $b) => $b['unread'] <=> $a['unread'] ?: ($b['last_message_at'] ?? '') <=> ($a['last_message_at'] ?? ''));

        return ToolResult::success([
            'channels' => $channels,
            'count' => count($channels),
        ]);
    }
}
