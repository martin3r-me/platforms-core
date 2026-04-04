<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMention;
use Platform\Core\Models\TerminalMessage;
use Platform\Core\Models\TerminalReaction;
use Platform\Core\Models\User;
use Platform\Core\Events\TerminalMessageSent;
use Platform\Core\Events\TerminalReactionToggled;

/**
 * Terminal UI shell with messaging, DMs, group channels, and context awareness.
 */
class Terminal extends Component
{
    public ?string $contextType = null;
    public ?int $contextId = null;
    public ?int $channelId = null;

    // ── Lifecycle ──────────────────────────────────────────────

    public function mount(): void
    {
        // Load last active channel for the user
        $teamId = $this->teamId();
        if (! $teamId) {
            return;
        }

        $lastMembership = TerminalChannelMember::where('user_id', auth()->id())
            ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
            ->latest('updated_at')
            ->first();

        if ($lastMembership) {
            $this->channelId = $lastMembership->channel_id;
        }
    }

    // ── Context Channel ────────────────────────────────────────

    #[On('terminal:set-context')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;

        if ($this->contextType && $this->contextId) {
            $this->resolveContextChannel();
        }
    }

    protected function resolveContextChannel(): void
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return;
        }

        $channel = TerminalChannel::forTeam($teamId)
            ->forContext($this->contextType, $this->contextId)
            ->first();

        if (! $channel) {
            $channel = TerminalChannel::create([
                'team_id' => $teamId,
                'type' => 'context',
                'context_type' => $this->contextType,
                'context_id' => $this->contextId,
            ]);
        }

        $this->channelId = $channel->id;
        $this->ensureMembership($channel);
    }

    // ── Open / Switch Channel ──────────────────────────────────

    public function openChannel(int $channelId): void
    {
        $teamId = $this->teamId();
        $channel = TerminalChannel::where('id', $channelId)
            ->where('team_id', $teamId)
            ->firstOrFail();

        $this->channelId = $channel->id;
        $this->ensureMembership($channel);
        $this->markAsRead();
    }

    // ── DM ─────────────────────────────────────────────────────

    public function openDm(int $targetUserId): void
    {
        $teamId = $this->teamId();
        if (! $teamId || $targetUserId === auth()->id()) {
            return;
        }

        $userIds = [auth()->id(), $targetUserId];
        $hash = TerminalChannel::makeParticipantHash($userIds);

        $channel = TerminalChannel::forTeam($teamId)
            ->where('participant_hash', $hash)
            ->first();

        if (! $channel) {
            $channel = TerminalChannel::create([
                'team_id' => $teamId,
                'type' => 'dm',
                'participant_hash' => $hash,
            ]);

            // Add both participants
            foreach ($userIds as $uid) {
                TerminalChannelMember::create([
                    'channel_id' => $channel->id,
                    'user_id' => $uid,
                    'role' => 'member',
                ]);
            }
        }

        $this->channelId = $channel->id;
        $this->ensureMembership($channel);
    }

    // ── Group Channel ──────────────────────────────────────────

    public function createChannel(string $name, ?string $description = null, ?string $icon = null): void
    {
        $teamId = $this->teamId();
        if (! $teamId || empty(trim($name))) {
            return;
        }

        $channel = TerminalChannel::create([
            'team_id' => $teamId,
            'type' => 'channel',
            'name' => trim($name),
            'description' => $description ? trim($description) : null,
            'icon' => $icon,
        ]);

        // Creator becomes owner
        TerminalChannelMember::create([
            'channel_id' => $channel->id,
            'user_id' => auth()->id(),
            'role' => 'owner',
        ]);

        $this->channelId = $channel->id;
    }

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

    // ── Delete / Leave Channel ─────────────────────────────────

    public function deleteChannel(): void
    {
        if (! $this->channelId) {
            return;
        }

        $channel = TerminalChannel::findOrFail($this->channelId);

        // Only owners can delete group channels
        if ($channel->type === 'channel') {
            $isOwner = TerminalChannelMember::where('channel_id', $channel->id)
                ->where('user_id', auth()->id())
                ->where('role', 'owner')
                ->exists();

            if (! $isOwner) {
                return;
            }
        }

        // DMs: just remove membership (don't delete the channel for the other user)
        if ($channel->isDm()) {
            TerminalChannelMember::where('channel_id', $channel->id)
                ->where('user_id', auth()->id())
                ->delete();
        } else {
            // Channel/Context: cascade delete (FK handles messages, members, etc.)
            $channel->delete();
        }

        $this->channelId = null;
    }

    public function leaveChannel(): void
    {
        if (! $this->channelId) {
            return;
        }

        TerminalChannelMember::where('channel_id', $this->channelId)
            ->where('user_id', auth()->id())
            ->delete();

        $this->channelId = null;
    }

    // ── Send Message ───────────────────────────────────────────

    public function sendMessage(string $bodyHtml, ?string $bodyPlain = null, ?int $parentId = null, array $mentionUserIds = []): void
    {
        if (! $this->channelId || empty(trim(strip_tags($bodyHtml)))) {
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
            'has_mentions' => ! empty($mentionUserIds),
        ]);

        // Update channel counters
        $channel->increment('message_count');
        $channel->update(['last_message_id' => $message->id]);

        // Update parent reply count if this is a thread reply
        if ($parentId) {
            $parent = TerminalMessage::find($parentId);
            if ($parent) {
                $parent->increment('reply_count');
                $parent->update(['last_reply_at' => now()]);
            }
        }

        // Store mentions
        if (! empty($mentionUserIds)) {
            foreach (array_unique($mentionUserIds) as $uid) {
                TerminalMention::create([
                    'message_id' => $message->id,
                    'user_id' => $uid,
                    'channel_id' => $channel->id,
                ]);
            }
        }

        // Mark as read for sender
        $this->markAsRead($message->id);

        // Dispatch notifications
        $this->dispatchNotifications($channel, $message, $mentionUserIds);

        // Broadcast via WebSocket (for real-time updates to other users)
        try {
            TerminalMessageSent::dispatch($channel->id, [
                'id' => $message->id,
                'user_id' => $message->user_id,
                'user_name' => auth()->user()->name ?? 'Unbekannt',
                'user_avatar' => auth()->user()->avatar,
                'user_initials' => $this->initials(auth()->user()->name ?? '?'),
                'body_html' => $message->body_html,
                'body_plain' => $message->body_plain,
                'type' => $message->type,
                'reply_count' => 0,
                'has_mentions' => $message->has_mentions,
                'reactions' => [],
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->translatedFormat('d. M Y'),
                'is_mine' => false,
            ], auth()->id());
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

        // 1. @Mention notifications — always sent regardless of channel notification_preference
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

        // 2. Channel-member notifications based on notification_preference
        $members = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', '!=', $senderId)
            ->get();

        $alreadyNotified = array_unique($mentionUserIds);
        $dmRecipients = collect();
        $channelRecipients = collect();

        foreach ($members as $member) {
            // Skip users already notified via @mention
            if (in_array($member->user_id, $alreadyNotified)) {
                continue;
            }

            if ($member->notification_preference === 'none') {
                continue;
            }

            // 'mentions' = only notify on @mention (already handled above)
            if ($member->notification_preference === 'mentions') {
                continue;
            }

            // 'all' = notify on every message
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

        // Broadcast reaction toggle to channel members
        try {
            $message = TerminalMessage::find($messageId);
            if ($message) {
                TerminalReactionToggled::dispatch(
                    $message->channel_id,
                    $messageId,
                    $emoji,
                    auth()->id(),
                    $added,
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Terminal reaction broadcast failed: '.$e->getMessage());
        }
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

    // ── Team Members (for DM picker / mentions) ────────────────

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
    public function channels(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return ['dms' => [], 'channels' => [], 'context' => []];
        }

        $userId = auth()->id();

        $memberships = TerminalChannelMember::where('user_id', $userId)
            ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
            ->with(['channel' => fn ($q) => $q->with('lastMessage:id,body_plain,created_at')])
            ->get();

        $dms = [];
        $channels = [];
        $context = [];

        foreach ($memberships as $membership) {
            $ch = $membership->channel;
            if (! $ch) {
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
                'name' => $ch->name,
                'icon' => $ch->icon,
                'type' => $ch->type,
                'unread' => $unread,
                'last_message' => $ch->lastMessage?->body_plain
                    ? \Illuminate\Support\Str::limit($ch->lastMessage->body_plain, 40)
                    : null,
                'last_at' => $ch->lastMessage?->created_at?->diffForHumans(short: true),
            ];

            // For DMs, resolve the other participant's name + avatar
            if ($ch->type === 'dm') {
                $other = TerminalChannelMember::where('channel_id', $ch->id)
                    ->where('user_id', '!=', $userId)
                    ->with('user:id,name,avatar')
                    ->first();
                $item['name'] = $other?->user?->name ?? 'Unbekannt';
                $item['avatar'] = $other?->user?->avatar;
                $item['initials'] = $this->initials($item['name']);
                $dms[] = $item;
            } elseif ($ch->type === 'channel') {
                $channels[] = $item;
            } else {
                $context[] = $item;
            }
        }

        // Sort by last activity (unreads first, then recency)
        $sort = fn ($a, $b) => $b['unread'] <=> $a['unread'] ?: ($b['last_at'] ?? '') <=> ($a['last_at'] ?? '');
        usort($dms, $sort);
        usort($channels, $sort);

        return compact('dms', 'channels', 'context');
    }

    #[Computed]
    public function messages(): array
    {
        if (! $this->channelId) {
            return [];
        }

        return TerminalMessage::where('channel_id', $this->channelId)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,avatar',
                'reactions' => fn ($q) => $q->select('id', 'message_id', 'user_id', 'emoji'),
                'replies' => fn ($q) => $q->with('user:id,name,avatar')->latest()->limit(3),
            ])
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (TerminalMessage $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user_name' => $m->user?->name ?? 'Unbekannt',
                'user_avatar' => $m->user?->avatar,
                'user_initials' => $this->initials($m->user?->name ?? '?'),
                'body_html' => $m->body_html,
                'body_plain' => $m->body_plain,
                'type' => $m->type,
                'reply_count' => $m->reply_count,
                'has_mentions' => $m->has_mentions,
                'reactions' => $m->reactions->groupBy('emoji')->map(fn ($group) => [
                    'emoji' => $group->first()->emoji,
                    'count' => $group->count(),
                    'reacted' => $group->contains('user_id', auth()->id()),
                ])->values()->toArray(),
                'time' => $m->created_at->format('H:i'),
                'date' => $m->created_at->translatedFormat('d. M Y'),
                'is_mine' => $m->user_id === auth()->id(),
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

        $data = [
            'id' => $channel->id,
            'type' => $channel->type,
            'name' => $channel->name,
            'icon' => $channel->icon,
            'description' => $channel->description,
            'member_count' => $channel->members()->count(),
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

        // Check if current user can delete this channel
        $data['can_delete'] = $channel->type === 'channel' && TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        return $data;
    }

    // ── Echo Listeners (WebSocket real-time updates) ───────────

    #[On('echo-private:terminal.channel.{channelId},message.sent')]
    public function onMessageReceived(array $payload = []): void
    {
        // Invalidate computed properties so Livewire re-renders with fresh data
        unset($this->messages);
        unset($this->channels);
    }

    #[On('echo-private:terminal.channel.{channelId},reaction.toggled')]
    public function onReactionToggled(array $payload = []): void
    {
        unset($this->messages);
    }

    // ── Render ─────────────────────────────────────────────────

    public function render()
    {
        return view('platform::livewire.terminal');
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
}
