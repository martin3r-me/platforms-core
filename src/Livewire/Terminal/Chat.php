<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\TerminalBookmark;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMention;
use Platform\Core\Models\TerminalMessage;
use Platform\Core\Models\TerminalPin;
use Platform\Core\Models\TerminalReaction;
use Platform\Core\Models\TerminalReminder;
use Platform\Core\Models\User;
use Platform\Core\Events\TerminalMessageSent;
use Platform\Core\Events\TerminalReactionToggled;
use Platform\Core\Services\ContextFileService;

/**
 * Chat child component — handles messages, reactions, pins, bookmarks,
 * reminders, forwarding, file attachments, and real-time Echo listeners.
 *
 * The parent Terminal component owns the sidebar + channelId and dispatches
 * `terminal-chat-channel` when the active channel changes.
 */
class Chat extends Component
{
    use WithFileUploads;

    public ?int $channelId = null;
    public $pendingFiles = [];
    public ?int $editingMessageId = null;
    public array $onlineUserIds = [];

    // ── Channel Sync from Parent ────────────────────────────────

    #[On('terminal-chat-channel')]
    public function onChannelChanged(?int $channelId): void
    {
        $this->channelId = $channelId;
        $this->editingMessageId = null;
        unset($this->messages, $this->activeChannel);

        if ($channelId) {
            $this->markAsRead();
        }
    }

    // ── Channel Management ─────────────────────────────────────

    public function addMember(int $userId): void
    {
        if (! $this->channelId) {
            return;
        }

        $channel = TerminalChannel::findOrFail($this->channelId);

        TerminalChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $userId],
            [
                'role' => 'member',
                'last_read_message_id' => $channel->last_message_id,
            ]
        );
    }

    public function getChannelMembers(): array
    {
        if (! $this->channelId) {
            return [];
        }

        $channel = TerminalChannel::find($this->channelId);
        if (! $channel || $channel->isDm()) {
            return [];
        }

        return TerminalChannelMember::where('channel_id', $channel->id)
            ->with('user:id,name,avatar')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->user_id,
                'name' => $m->user?->name ?? 'Unbekannt',
                'avatar' => $m->user?->avatar,
                'initials' => $this->initials($m->user?->name ?? '?'),
                'role' => $m->role,
            ])
            ->toArray();
    }

    public function removeMember(int $userId): void
    {
        if (! $this->channelId) {
            return;
        }

        $channel = TerminalChannel::findOrFail($this->channelId);

        $isOwner = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner || $userId === auth()->id()) {
            return;
        }

        TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->delete();
    }

    // ── File Attachments ──────────────────────────────────────

    public function uploadAttachments(): array
    {
        if (empty($this->pendingFiles)) {
            return [];
        }

        $service = app(ContextFileService::class);
        $results = [];

        foreach ($this->pendingFiles as $file) {
            $result = $service->uploadForContext(
                $file,
                TerminalMessage::class,
                0,
            );

            $results[] = [
                'id' => $result['id'],
                'token' => $result['token'],
                'url' => $result['url'],
                'mime_type' => $result['mime_type'],
                'original_name' => $result['original_name'],
                'file_size' => $result['file_size'],
                'is_image' => str_starts_with($result['mime_type'], 'image/'),
            ];
        }

        $this->pendingFiles = [];

        return $results;
    }

    // ── Send Message ───────────────────────────────────────────

    public function sendMessage(string $bodyHtml, ?string $bodyPlain = null, ?int $parentId = null, array $mentionUserIds = [], array $attachmentIds = []): void
    {
        $hasAttachments = ! empty($attachmentIds);

        if (! $this->channelId || (empty(trim(strip_tags($bodyHtml))) && ! $hasAttachments)) {
            return;
        }

        $channel = TerminalChannel::findOrFail($this->channelId);
        $this->ensureMembership($channel);

        $message = TerminalMessage::create([
            'channel_id' => $channel->id,
            'user_id' => auth()->id(),
            'parent_id' => $parentId,
            'body_html' => $bodyHtml,
            'body_plain' => $bodyPlain ?? strip_tags($bodyHtml),
            'has_attachments' => $hasAttachments,
            'has_mentions' => ! empty($mentionUserIds),
        ]);

        if ($hasAttachments) {
            ContextFile::whereIn('id', $attachmentIds)
                ->where('context_type', TerminalMessage::class)
                ->where('context_id', 0)
                ->where('user_id', auth()->id())
                ->update(['context_id' => $message->id]);
        }

        $channel->increment('message_count');
        $channel->update(['last_message_id' => $message->id]);

        if ($parentId) {
            $parent = TerminalMessage::find($parentId);
            if ($parent) {
                $parent->increment('reply_count');
                $parent->update(['last_reply_at' => now()]);
            }
        }

        if (! empty($mentionUserIds)) {
            $validUserIds = User::whereIn('id', array_unique($mentionUserIds))->pluck('id');
            foreach ($validUserIds as $uid) {
                TerminalMention::create([
                    'message_id' => $message->id,
                    'user_id' => $uid,
                    'channel_id' => $channel->id,
                ]);
            }
            $mentionUserIds = $validUserIds->toArray();
        }

        $this->markAsRead($message->id);
        $this->dispatchNotifications($channel, $message, $mentionUserIds);

        try {
            TerminalMessageSent::dispatch($channel->id, $message->id, auth()->id());
        } catch (\Throwable $e) {
            \Log::warning('Terminal broadcast failed: '.$e->getMessage());
        }
    }

    protected function dispatchNotifications(TerminalChannel $channel, TerminalMessage $message, array $mentionUserIds = []): void
    {
        if (! class_exists(\Platform\Notifications\NotificationDispatcher::class)) {
            return;
        }

        $dispatcher = app(\Platform\Notifications\NotificationDispatcher::class);
        $senderId = auth()->id();
        $senderName = auth()->user()->name ?? 'Unbekannt';
        $plainText = \Illuminate\Support\Str::limit($message->body_plain ?? strip_tags($message->body_html), 100);

        if (! empty($mentionUserIds)) {
            $mentionRecipients = User::whereIn('id', array_diff(array_unique($mentionUserIds), [$senderId]))->get();
            if ($mentionRecipients->isNotEmpty()) {
                $dispatcher->dispatch('terminal.mention', [
                    'title'          => "{$senderName} hat dich erwähnt",
                    'message'        => $plainText,
                    'notice_type'    => 'toast',
                    'team_id'        => $channel->team_id,
                    'noticable_type' => TerminalMessage::class,
                    'noticable_id'   => $message->id,
                    'metadata'       => ['channel_id' => $channel->id],
                ], $mentionRecipients);
            }
        }

        $members = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', '!=', $senderId)
            ->get();

        $alreadyNotified = array_unique($mentionUserIds);
        $dmRecipients = collect();
        $channelRecipients = collect();

        foreach ($members as $member) {
            if (in_array($member->user_id, $alreadyNotified)) {
                continue;
            }
            if ($member->notification_preference === 'none' || $member->notification_preference === 'mentions') {
                continue;
            }
            if ($channel->isDm()) {
                $dmRecipients->push($member->user_id);
            } else {
                $channelRecipients->push($member->user_id);
            }
        }

        if ($dmRecipients->isNotEmpty()) {
            $dispatcher->dispatch('terminal.dm', [
                'title'          => "Nachricht von {$senderName}",
                'message'        => $plainText,
                'notice_type'    => 'toast',
                'team_id'        => $channel->team_id,
                'noticable_type' => TerminalMessage::class,
                'noticable_id'   => $message->id,
                'metadata'       => ['channel_id' => $channel->id],
            ], User::whereIn('id', $dmRecipients)->get());
        }

        if ($channelRecipients->isNotEmpty()) {
            $channelName = $channel->name ?? 'Channel';
            $dispatcher->dispatch('terminal.channel_message', [
                'title'          => "{$senderName} in #{$channelName}",
                'message'        => $plainText,
                'notice_type'    => 'toast',
                'team_id'        => $channel->team_id,
                'noticable_type' => TerminalMessage::class,
                'noticable_id'   => $message->id,
                'metadata'       => ['channel_id' => $channel->id],
            ], User::whereIn('id', $channelRecipients)->get());
        }
    }

    // ── Reactions ──────────────────────────────────────────────

    public function toggleReaction(int $messageId, string $emoji): void
    {
        $existing = TerminalReaction::where('message_id', $messageId)
            ->where('user_id', auth()->id())
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $added = false;
        } else {
            TerminalReaction::create([
                'message_id' => $messageId,
                'user_id' => auth()->id(),
                'emoji' => $emoji,
            ]);
            $added = true;
        }

        try {
            $message = TerminalMessage::find($messageId);
            if ($message) {
                TerminalReactionToggled::dispatch($message->channel_id, $messageId, $emoji, auth()->id(), $added);
            }
        } catch (\Throwable $e) {
            \Log::warning('Terminal reaction broadcast failed: '.$e->getMessage());
        }
    }

    // ── Pins ──────────────────────────────────────────────────

    public function pinMessage(int $messageId): void
    {
        if (! $this->channelId) {
            return;
        }

        $msg = TerminalMessage::find($messageId);
        if (! $msg || $msg->channel_id !== $this->channelId) {
            return;
        }

        if (TerminalPin::where('channel_id', $this->channelId)->count() >= 50) {
            return;
        }

        TerminalPin::firstOrCreate(
            ['channel_id' => $this->channelId, 'message_id' => $messageId],
            ['pinned_by_user_id' => auth()->id()]
        );

        unset($this->messages);
    }

    public function unpinMessage(int $messageId): void
    {
        if (! $this->channelId) {
            return;
        }

        TerminalPin::where('channel_id', $this->channelId)
            ->where('message_id', $messageId)
            ->delete();

        unset($this->messages);
    }

    public function getPinnedMessages(): array
    {
        if (! $this->channelId) {
            return [];
        }

        return TerminalPin::where('channel_id', $this->channelId)
            ->with(['message.user:id,name,avatar', 'pinnedBy:id,name'])
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn ($pin) => $pin->message !== null)
            ->map(fn ($pin) => [
                'id' => $pin->id,
                'message_id' => $pin->message_id,
                'body_snippet' => \Illuminate\Support\Str::limit($pin->message->body_plain ?? strip_tags($pin->message->body_html), 100),
                'user_name' => $pin->message->user?->name ?? 'Unbekannt',
                'user_avatar' => $pin->message->user?->avatar,
                'user_initials' => $this->initials($pin->message->user?->name ?? '?'),
                'pinned_by' => $pin->pinnedBy?->name ?? 'Unbekannt',
                'pinned_at' => $pin->created_at->diffForHumans(short: true),
                'time' => $pin->message->created_at->format('H:i'),
                'date' => $pin->message->created_at->translatedFormat('d. M'),
            ])
            ->values()
            ->toArray();
    }

    // ── Bookmarks ─────────────────────────────────────────────

    public function toggleBookmark(int $messageId): void
    {
        $msg = TerminalMessage::find($messageId);
        if (! $msg) {
            return;
        }

        $existing = TerminalBookmark::where('user_id', auth()->id())
            ->where('message_id', $messageId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            TerminalBookmark::create([
                'user_id' => auth()->id(),
                'message_id' => $messageId,
            ]);
        }

        unset($this->messages);
    }

    // ── Forward ───────────────────────────────────────────────

    public function forwardMessage(int $messageId, int $targetChannelId): void
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return;
        }

        $msg = TerminalMessage::find($messageId);
        if (! $msg) {
            return;
        }

        $isMemberSource = TerminalChannelMember::where('channel_id', $msg->channel_id)
            ->where('user_id', auth()->id())
            ->exists();
        if (! $isMemberSource) {
            return;
        }

        $targetChannel = TerminalChannel::where('id', $targetChannelId)
            ->where('team_id', $teamId)
            ->first();
        if (! $targetChannel) {
            return;
        }

        $this->ensureMembership($targetChannel);

        $originalUser = $msg->user;

        $forwarded = TerminalMessage::create([
            'channel_id' => $targetChannel->id,
            'user_id' => auth()->id(),
            'body_html' => $msg->body_html,
            'body_plain' => $msg->body_plain,
            'type' => 'forwarded',
            'meta' => [
                'forwarded_from' => [
                    'message_id' => $msg->id,
                    'user_id' => $msg->user_id,
                    'user_name' => $originalUser?->name ?? 'Unbekannt',
                    'channel_id' => $msg->channel_id,
                ],
            ],
        ]);

        $targetChannel->increment('message_count');
        $targetChannel->update(['last_message_id' => $forwarded->id]);

        try {
            TerminalMessageSent::dispatch($targetChannel->id, $forwarded->id, auth()->id());
        } catch (\Throwable $e) {
            \Log::warning('Terminal forward broadcast failed: ' . $e->getMessage());
        }
    }

    public function getForwardTargets(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        $memberships = TerminalChannelMember::where('user_id', auth()->id())
            ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
            ->with(['channel'])
            ->get();

        return $memberships
            ->filter(fn ($m) => $m->channel && $m->channel_id !== $this->channelId)
            ->map(function ($m) {
                $ch = $m->channel;
                $item = [
                    'id' => $ch->id,
                    'name' => $ch->name,
                    'type' => $ch->type,
                    'icon' => $ch->icon,
                ];

                if ($ch->isDm()) {
                    $other = TerminalChannelMember::where('channel_id', $ch->id)
                        ->where('user_id', '!=', auth()->id())
                        ->with('user:id,name,avatar')
                        ->first();
                    $item['name'] = $other?->user?->name ?? 'Unbekannt';
                    $item['avatar'] = $other?->user?->avatar;
                    $item['initials'] = $this->initials($item['name']);
                }

                return $item;
            })
            ->values()
            ->toArray();
    }

    // ── Reminders ─────────────────────────────────────────────

    public function setReminder(int $messageId, string $preset): void
    {
        $msg = TerminalMessage::find($messageId);
        if (! $msg) {
            return;
        }

        $remindAt = match ($preset) {
            '30min' => now()->addMinutes(30),
            '1h' => now()->addHour(),
            '3h' => now()->addHours(3),
            'tomorrow_9' => now()->addDay()->setTime(9, 0),
            'next_monday_9' => now()->next('Monday')->setTime(9, 0),
            default => null,
        };

        if (! $remindAt) {
            return;
        }

        TerminalReminder::updateOrCreate(
            ['user_id' => auth()->id(), 'message_id' => $messageId],
            ['remind_at' => $remindAt, 'reminded' => false]
        );

        unset($this->messages);
    }

    public function cancelReminder(int $messageId): void
    {
        TerminalReminder::where('user_id', auth()->id())
            ->where('message_id', $messageId)
            ->delete();

        unset($this->messages);
    }

    // ── Edit / Delete Message ─────────────────────────────────

    public function deleteMessage(int $messageId): void
    {
        $msg = TerminalMessage::find($messageId);
        if (! $msg || $msg->user_id !== auth()->id()) {
            return;
        }

        $channel = $msg->channel;
        $parentId = $msg->parent_id;

        $msg->reactions()->delete();
        $msg->mentions()->delete();
        TerminalPin::where('message_id', $messageId)->delete();
        TerminalBookmark::where('message_id', $messageId)->delete();
        TerminalReminder::where('message_id', $messageId)->delete();

        foreach ($msg->replies as $reply) {
            $reply->reactions()->delete();
            $reply->mentions()->delete();
            TerminalPin::where('message_id', $reply->id)->delete();
            TerminalBookmark::where('message_id', $reply->id)->delete();
            TerminalReminder::where('message_id', $reply->id)->delete();
            $reply->delete();
        }

        $msg->delete();

        if ($parentId) {
            $parent = TerminalMessage::find($parentId);
            if ($parent) {
                $parent->update(['reply_count' => $parent->replies()->count()]);
            }
        }

        if ($channel) {
            $channel->update(['message_count' => $channel->messages()->count()]);
        }

        unset($this->messages);
    }

    public function startEditMessage(int $messageId): void
    {
        $msg = TerminalMessage::find($messageId);
        if (! $msg || $msg->user_id !== auth()->id()) {
            return;
        }

        $this->editingMessageId = $messageId;
    }

    public function cancelEdit(): void
    {
        $this->editingMessageId = null;
    }

    public function editMessage(int $messageId, string $bodyHtml, string $bodyPlain): void
    {
        $msg = TerminalMessage::find($messageId);
        if (! $msg || $msg->user_id !== auth()->id()) {
            return;
        }

        if (empty(trim(strip_tags($bodyHtml)))) {
            return;
        }

        $msg->update([
            'body_html' => $bodyHtml,
            'body_plain' => $bodyPlain,
            'edited_at' => now(),
        ]);

        $this->editingMessageId = null;
        unset($this->messages);
    }

    // ── Read Tracking ──────────────────────────────────────────

    public function markAsRead(?int $messageId = null): void
    {
        if (! $this->channelId) {
            return;
        }

        $member = TerminalChannelMember::where('channel_id', $this->channelId)
            ->where('user_id', auth()->id())
            ->first();

        if ($member) {
            $member->markAsRead($messageId);
        }
    }

    // ── Team Members (for mentions) ────────────────────────────

    public function getTeamMembers(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        return auth()->user()->currentTeam
            ->users()
            ->where('users.id', '!=', auth()->id())
            ->select('users.id', 'users.name', 'users.avatar')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'initials' => $this->initials($u->name),
            ])
            ->toArray();
    }

    // ── Computed Properties ────────────────────────────────────

    #[Computed]
    public function messages(): array
    {
        if (! $this->channelId) {
            return [];
        }

        $messages = TerminalMessage::where('channel_id', $this->channelId)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,avatar',
                'reactions' => fn ($q) => $q->select('id', 'message_id', 'user_id', 'emoji'),
                'replies' => fn ($q) => $q->with('user:id,name,avatar')->latest()->limit(3),
                'attachments',
            ])
            ->orderBy('id')
            ->limit(100)
            ->get();

        $messageIds = $messages->pluck('id')->toArray();
        $pinnedIds = TerminalPin::where('channel_id', $this->channelId)
            ->whereIn('message_id', $messageIds)
            ->pluck('message_id')
            ->flip();
        $bookmarkedIds = TerminalBookmark::where('user_id', auth()->id())
            ->whereIn('message_id', $messageIds)
            ->pluck('message_id')
            ->flip();
        $reminderIds = TerminalReminder::where('user_id', auth()->id())
            ->where('reminded', false)
            ->whereIn('message_id', $messageIds)
            ->pluck('message_id')
            ->flip();

        return $messages->map(fn (TerminalMessage $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user_name' => $m->user?->name ?? 'Unbekannt',
                'user_avatar' => $m->user?->avatar,
                'user_initials' => $this->initials($m->user?->name ?? '?'),
                'body_html' => $m->body_html,
                'body_plain' => $m->body_plain,
                'type' => $m->type,
                'meta' => $m->meta,
                'reply_count' => $m->reply_count,
                'has_mentions' => $m->has_mentions,
                'has_attachments' => $m->has_attachments,
                'attachments' => $m->has_attachments ? $m->attachments->map(fn (ContextFile $f) => [
                    'id' => $f->id,
                    'url' => $f->url,
                    'download_url' => $f->download_url,
                    'original_name' => $f->original_name,
                    'mime_type' => $f->mime_type,
                    'file_size' => $f->file_size,
                    'is_image' => $f->isImage(),
                ])->toArray() : [],
                'reactions' => $m->reactions->groupBy('emoji')->map(fn ($group) => [
                    'emoji' => $group->first()->emoji,
                    'count' => $group->count(),
                    'reacted' => $group->contains('user_id', auth()->id()),
                ])->values()->toArray(),
                'time' => $m->created_at->format('H:i'),
                'date' => $m->created_at->translatedFormat('d. M Y'),
                'is_mine' => $m->user_id === auth()->id(),
                'edited_at' => $m->edited_at?->format('d.m.Y H:i'),
                'is_pinned' => $pinnedIds->has($m->id),
                'is_bookmarked' => $bookmarkedIds->has($m->id),
                'has_reminder' => $reminderIds->has($m->id),
            ])
            ->toArray();
    }

    #[Computed]
    public function activeChannel(): ?array
    {
        if (! $this->channelId) {
            return null;
        }

        $channel = TerminalChannel::find($this->channelId);
        if (! $channel) {
            return null;
        }

        $memberRows = TerminalChannelMember::where('channel_id', $channel->id)
            ->with('user:id,name,avatar')
            ->get();

        $members = $memberRows->map(fn ($m) => [
            'id' => $m->user_id,
            'name' => $m->user?->name ?? 'Unbekannt',
            'avatar' => $m->user?->avatar,
            'initials' => $this->initials($m->user?->name ?? '?'),
        ])->toArray();

        $data = [
            'id' => $channel->id,
            'type' => $channel->type,
            'name' => $channel->name,
            'icon' => $channel->icon,
            'description' => $channel->description,
            'member_count' => count($members),
            'members' => $members,
        ];

        if ($channel->isDm()) {
            $other = TerminalChannelMember::where('channel_id', $channel->id)
                ->where('user_id', '!=', auth()->id())
                ->with('user:id,name,avatar')
                ->first();
            $data['name'] = $other?->user?->name ?? 'Unbekannt';
            $data['avatar'] = $other?->user?->avatar;
            $data['initials'] = $this->initials($data['name']);
        }

        $data['pin_count'] = TerminalPin::where('channel_id', $channel->id)->count();

        $data['can_delete'] = $channel->type === 'channel' && TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        $data['context'] = null;
        if ($channel->isContext()) {
            $data['context'] = $this->getContextBreadcrumb($channel->context_type, $channel->context_id);
            $data['context_url'] = $channel->meta['url'] ?? null;
        }

        return $data;
    }

    // ── Echo Listeners (WebSocket real-time updates) ───────────

    public function getListeners(): array
    {
        $listeners = [];

        try {
            $teamId = $this->teamId();
            if ($teamId && auth()->check()) {
                $channelIds = TerminalChannelMember::where('user_id', auth()->id())
                    ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
                    ->pluck('channel_id');

                foreach ($channelIds as $id) {
                    $listeners["echo-private:terminal.channel.{$id},.message.sent"] = 'onMessageReceived';
                    $listeners["echo-private:terminal.channel.{$id},.reaction.toggled"] = 'onReactionToggled';
                }

                $userId = auth()->id();
                $listeners["echo-private:terminal.user.{$userId},.reminder.due"] = 'onReminderDue';

                $listeners["echo-presence:terminal.team.{$teamId},here"] = 'onPresenceHere';
                $listeners["echo-presence:terminal.team.{$teamId},joining"] = 'onPresenceJoining';
                $listeners["echo-presence:terminal.team.{$teamId},leaving"] = 'onPresenceLeaving';
            }
        } catch (\Throwable $e) {
            // Fail silently
        }

        return $listeners;
    }

    public function onMessageReceived($payload = null): void
    {
        unset($this->messages);
    }

    public function onReactionToggled($payload = null): void
    {
        unset($this->messages);
    }

    public function onReminderDue($payload = null): void
    {
        $snippet = $payload['snippet'] ?? 'Nachricht';
        $channelId = $payload['channelId'] ?? null;
        $messageId = $payload['messageId'] ?? null;

        $this->dispatch('notice', type: 'info', title: 'Erinnerung', message: $snippet, metadata: [
            'channel_id' => $channelId,
            'message_id' => $messageId,
        ]);
    }

    public function onPresenceHere($users): void
    {
        $this->onlineUserIds = collect($users)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
    }

    public function onPresenceJoining($user): void
    {
        $id = (int) ($user['id'] ?? $user);
        if (! in_array($id, $this->onlineUserIds)) {
            $this->onlineUserIds[] = $id;
        }
    }

    public function onPresenceLeaving($user): void
    {
        $id = (int) ($user['id'] ?? $user);
        $this->onlineUserIds = array_values(array_filter($this->onlineUserIds, fn ($uid) => $uid !== $id));
    }

    // ── Render ─────────────────────────────────────────────────

    public function render()
    {
        return view('platform::livewire.terminal.chat');
    }

    // ── Private Helpers ────────────────────────────────────────

    protected function ensureMembership(TerminalChannel $channel): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        TerminalChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $userId],
            [
                'role' => 'member',
                'last_read_message_id' => $channel->last_message_id,
            ]
        );
    }

    protected function teamId(): ?int
    {
        return auth()->user()?->currentTeam?->id;
    }

    protected function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }

        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }

    protected function getContextBreadcrumb(?string $contextType = null, ?int $contextId = null): ?array
    {
        if (! $contextType || ! $contextId) {
            return null;
        }

        $iconMap = [
            'Ticket' => '🎫', 'HelpdeskTicket' => '🎫',
            'Contact' => '👤', 'CrmContact' => '👤',
            'Company' => '🏢', 'CrmCompany' => '🏢',
            'Project' => '📋', 'PlannerProject' => '📋',
            'Applicant' => '📄', 'RecruitingApplicant' => '📄',
            'Deal' => '💰',
            'PlannerTask' => '✅', 'Task' => '✅',
            'Invoice' => '🧾',
            'PatientsPatient' => '🏥',
            'PcCanvas' => '🎨',
            'NotesNote' => '📝',
            'NotesFolder' => '📁',
        ];

        $shortName = class_basename($contextType);
        $icon = $iconMap[$shortName] ?? '📎';

        $title = null;
        try {
            if (class_exists($contextType)) {
                $model = $contextType::find($contextId);
                $title = $model?->name ?? $model?->title ?? $model?->subject ?? null;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return [
            'icon' => $icon,
            'label' => $shortName,
            'title' => $title ?? "{$shortName} #{$contextId}",
        ];
    }
}
