<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMessage;

class GetTerminalMessagesTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.messages.GET';
    }

    public function getDescription(): string
    {
        return 'Liest Nachrichten aus einem Terminal-Channel. '
            . 'Gibt die letzten Nachrichten mit Autor, Zeitstempel, Reactions und Reply-Count zurück. '
            . 'Der User muss Mitglied des Channels sein. '
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
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximale Anzahl Nachrichten (Standard: 50, Max: 200).',
                ],
                'before_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Nachrichten VOR dieser Message-ID (für Pagination/ältere laden).',
                ],
                'after_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Nachrichten NACH dieser Message-ID (für neue Nachrichten).',
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

        // Check membership
        $isMember = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            return ToolResult::error('Du bist kein Mitglied dieses Channels.', 'FORBIDDEN');
        }

        $limit = min($arguments['limit'] ?? 50, 200);

        $query = TerminalMessage::where('channel_id', $channel->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name',
                'reactions' => fn ($q) => $q->select('id', 'message_id', 'user_id', 'emoji'),
                'attachments',
            ]);

        if (! empty($arguments['before_id'])) {
            $query->where('id', '<', (int) $arguments['before_id']);
        }
        if (! empty($arguments['after_id'])) {
            $query->where('id', '>', (int) $arguments['after_id']);
        }

        $messages = $query->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (TerminalMessage $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user_name' => $m->user?->name ?? 'Unbekannt',
                'body_plain' => $m->body_plain ?? strip_tags($m->body_html),
                'reply_count' => $m->reply_count,
                'has_attachments' => $m->has_attachments,
                'attachments' => $m->has_attachments ? $m->attachments->map(fn (ContextFile $f) => [
                    'id' => $f->id,
                    'url' => $f->url,
                    'original_name' => $f->original_name,
                    'mime_type' => $f->mime_type,
                    'file_size' => $f->file_size,
                    'is_image' => $f->isImage(),
                ])->toArray() : [],
                'reactions' => $m->reactions->groupBy('emoji')->map(fn ($group) => [
                    'emoji' => $group->first()->emoji,
                    'count' => $group->count(),
                ])->values()->toArray(),
                'created_at' => $m->created_at->toIso8601String(),
            ])
            ->toArray();

        // Mark as read
        $member = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();
        if ($member && ! empty($messages)) {
            $lastId = end($messages)['id'];
            $member->markAsRead($lastId);
        }

        return ToolResult::success([
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'channel_type' => $channel->type,
            'messages' => $messages,
            'count' => count($messages),
        ]);
    }
}
