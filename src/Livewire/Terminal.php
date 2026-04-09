<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMention;
use Platform\Core\Models\TerminalMessage;
use Platform\Core\Models\TerminalReaction;
use Platform\Core\Models\TerminalBookmark;
use Platform\Core\Models\TerminalPin;
use Platform\Core\Models\TerminalReminder;
use Platform\Core\Models\User;
use Platform\Core\Events\TerminalMessageSent;
use Platform\Core\Events\TerminalReactionToggled;
use Platform\Core\Services\ContextFileService;

/**
 * Terminal UI shell with messaging, DMs, group channels, and context awareness.
 */
class Terminal extends Component
{
    use WithFileUploads;

    public ?string $contextType = null;
    public ?int $contextId = null;
    public ?string $contextSubject = null;
    public ?string $contextSource = null;
    public ?string $contextUrl = null;
    public array $contextMeta = [];
    public ?int $channelId = null;
    public $pendingFiles = [];
    public ?int $editingMessageId = null;
    public array $onlineUserIds = [];
    public string $activeApp = 'chat';
    public array $availableApps = ['chat' => true, 'activity' => false, 'files' => false];

    // ── Lifecycle ──────────────────────────────────────────────

    public function mount(): void
    {
        // Load last active channel for the user
        $teamId = $this->teamId();
        if (! $teamId) {
            return;
        }

        // Deep-link: ?channel={id}&message={id}
        $deepChannel = request()->query('channel');
        $deepMessage = request()->query('message');

        if ($deepChannel) {
            $channel = TerminalChannel::where('id', (int) $deepChannel)
                ->where('team_id', $teamId)
                ->first();

            if ($channel) {
                // Verify membership
                $isMember = TerminalChannelMember::where('channel_id', $channel->id)
                    ->where('user_id', auth()->id())
                    ->exists();

                if ($isMember) {
                    $this->channelId = $channel->id;

                    if ($deepMessage) {
                        $this->dispatch('scroll-to-message', messageId: (int) $deepMessage);
                    }

                    return;
                }
            }
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

    /**
     * Receive context from modules via dispatch('comms', {...}).
     * Every module dispatches this in rendered() — this is the platform-standard way
     * to share page context with global components (formerly ModalComms, now Terminal).
     *
     * Payload: model, modelId, subject, description, url, source, recipients, capabilities, meta
     */
    #[On('comms')]
    public function setCommsContext(array $payload = []): void
    {
        $model = $payload['model'] ?? null;
        $modelId = $payload['modelId'] ?? null;

        // Only set context when we have a concrete entity (not dashboards/index pages)
        if (! $model || ! $modelId) {
            $this->contextType = null;
            $this->contextId = null;

            return;
        }

        // Reset available apps when context changes
        if ($model !== $this->contextType || (int) $modelId !== $this->contextId) {
            $this->availableApps = ['chat' => true, 'activity' => false, 'files' => false];
        }

        $this->contextType = $model;
        $this->contextId = (int) $modelId;
        $this->contextSubject = $payload['subject'] ?? null;
        $this->contextSource = $payload['source'] ?? null;
        $this->contextUrl = $payload['url'] ?? null;
        $this->contextMeta = $payload['meta'] ?? [];

        // Persist subject + URL on existing context channel
        $teamId = $this->teamId();
        if ($teamId && ($this->contextSubject || ! empty($payload['url']))) {
            $updates = [];
            if ($this->contextSubject) {
                $updates['name'] = $this->contextSubject;
            }
            if (! empty($payload['url'])) {
                $channel = TerminalChannel::forTeam($teamId)
                    ->forContext($this->contextType, $this->contextId)
                    ->first();
                if ($channel) {
                    $meta = $channel->meta ?? [];
                    if (($meta['url'] ?? null) !== $payload['url']) {
                        $meta['url'] = $payload['url'];
                        $updates['meta'] = $meta;
                    }
                    if (! empty($updates)) {
                        $channel->update($updates);
                    }
                }
            } elseif ($this->contextSubject) {
                TerminalChannel::forTeam($teamId)
                    ->forContext($this->contextType, $this->contextId)
                    ->where(fn ($q) => $q->whereNull('name')->orWhere('name', '!=', $this->contextSubject))
                    ->update($updates);
            }
        }
    }

    /**
     * Enable a specific Terminal app tab via dispatch.
     * Modules fire e.g. dispatch('terminal:app:activity') to unlock the Activity tab.
     */
    #[On('terminal:app:activity')]
    public function setAppActivity(): void
    {
        $this->availableApps['activity'] = true;
    }

    /**
     * Direct context set (for explicit terminal targeting from other components).
     */
    #[On('terminal:set-context')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;
        $this->contextSubject = $payload['subject'] ?? null;
        $this->contextSource = $payload['source'] ?? null;
    }

    /**
     * Open the terminal panel and switch to the current context channel.
     * Usage: $dispatch('terminal:open') — opens terminal with current page context.
     */
    #[On('terminal:open')]
    public function openTerminal(array $payload = []): void
    {
        // Optionally set context if provided
        if (! empty($payload['context_type']) && ! empty($payload['context_id'])) {
            $this->setContext($payload);
        }

        // Resolve context channel if we have a context
        if ($this->contextType && $this->contextId) {
            $this->resolveContextChannel();
        }

        $this->dispatch('toggle-terminal-open');
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
                'name' => $this->contextSubject,
                'meta' => $this->contextUrl ? ['url' => $this->contextUrl] : null,
            ]);
        } elseif ($this->contextSubject && $channel->name !== $this->contextSubject) {
            $channel->update(['name' => $this->contextSubject]);
        }

        $this->channelId = $channel->id;
        $this->ensureMembership($channel);
    }

    /**
     * Resolve a human-readable breadcrumb for a context channel.
     * Uses contextSubject from comms event when available, falls back to model lookup.
     */
    public function getContextBreadcrumb(?string $contextType = null, ?int $contextId = null, ?string $subject = null): ?array
    {
        $contextType = $contextType ?? $this->contextType;
        $contextId = $contextId ?? $this->contextId;

        if (! $contextType || ! $contextId) {
            return null;
        }

        $shortName = class_basename($contextType);

        // Icon map for known model types
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
            'Cycle' => '🔄',
            'Okr' => '🎯',
            'Objective' => '🎯',
        ];

        $icon = $iconMap[$shortName] ?? '📎';

        // Use provided subject, or contextSubject only if this IS the current page context
        $title = $subject;
        if (! $title && $contextType === $this->contextType && $contextId === $this->contextId) {
            $title = $this->contextSubject;
        }

        if (! $title) {
            try {
                if (class_exists($contextType)) {
                    $model = $contextType::find($contextId);
                    if ($model) {
                        $title = $model->display_name ?? $model->name ?? $model->title ?? $model->label ?? $model->subject ?? null;
                        if (isset($model->number)) {
                            $title = "#{$model->number} " . ($title ?? '');
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        $title = $title ?: "#{$contextId}";

        return [
            'label' => $shortName,
            'title' => \Illuminate\Support\Str::limit($title, 50),
            'icon' => $icon,
            'context_type' => $contextType,
            'context_id' => $contextId,
        ];
    }

    /**
     * Add a manual note to the context entity's activity log.
     */
    public function addActivityNote(string $bodyHtml, ?string $bodyPlain = null, array $attachmentIds = []): void
    {
        $plain = trim($bodyPlain ?? strip_tags($bodyHtml));
        if (empty($plain) && empty($attachmentIds)) {
            return;
        }

        $channel = $this->channelId ? TerminalChannel::find($this->channelId) : null;
        if (! $channel || ! $channel->context_type || ! $channel->context_id) {
            return;
        }

        if (! class_exists($channel->context_type)) {
            return;
        }

        $model = $channel->context_type::find($channel->context_id);
        if (! $model || ! method_exists($model, 'logActivity')) {
            return;
        }

        $metadata = [];
        if (! empty($attachmentIds)) {
            $metadata['attachment_ids'] = $attachmentIds;
        }

        $model->logActivity($plain, $metadata);

        // Link attachments to the activity model
        if (! empty($attachmentIds)) {
            $activity = $model->activities()->latest()->first();
            if ($activity) {
                ContextFile::whereIn('id', $attachmentIds)
                    ->where('context_type', TerminalMessage::class)
                    ->where('context_id', 0)
                    ->where('user_id', auth()->id())
                    ->update([
                        'context_type' => get_class($activity),
                        'context_id' => $activity->id,
                    ]);
            }
        }

        unset($this->contextActivities);
    }

    /**
     * Delete a manual activity note (only own notes).
     */
    public function deleteActivityNote(int $activityId): void
    {
        $channel = $this->channelId ? TerminalChannel::find($this->channelId) : null;
        if (! $channel || ! $channel->context_type || ! $channel->context_id) {
            return;
        }

        if (! class_exists($channel->context_type)) {
            return;
        }

        $model = $channel->context_type::find($channel->context_id);
        if (! $model || ! method_exists($model, 'activities')) {
            return;
        }

        $model->activities()
            ->where('id', $activityId)
            ->where('activity_type', 'manual')
            ->where('user_id', auth()->id())
            ->delete();

        unset($this->contextActivities);
    }

    /**
     * Load activities for the context entity of the active channel.
     */
    #[Computed]
    public function contextActivities(): array
    {
        if ($this->activeApp !== 'activity') {
            return [];
        }

        $channel = $this->channelId ? TerminalChannel::find($this->channelId) : null;
        if (! $channel || ! $channel->context_type || ! $channel->context_id) {
            return [];
        }

        if (! class_exists($channel->context_type)) {
            return [];
        }

        $model = $channel->context_type::find($channel->context_id);
        if (! $model || ! method_exists($model, 'activities')) {
            return [];
        }

        $currentUserId = auth()->id();

        $activities = $model->activities()
            ->with('user')
            ->limit(30)
            ->get();

        // Batch-load attachments for manual activities that have them
        $activityClass = get_class($activities->first() ?? new \stdClass());
        $activityIds = $activities->pluck('id')->toArray();
        $attachmentsByActivity = [];
        if (! empty($activityIds) && class_exists($activityClass)) {
            $attachmentsByActivity = ContextFile::where('context_type', $activityClass)
                ->whereIn('context_id', $activityIds)
                ->get()
                ->groupBy('context_id');
        }

        return $activities
            ->map(function ($activity) use ($currentUserId, $attachmentsByActivity) {
                $userName = $activity->user?->name ?? 'System';
                $event = $activity->name;
                $isManual = $activity->activity_type === 'manual';

                // Build readable title
                if ($isManual && $activity->message) {
                    $title = $activity->message;
                } elseif ($activity->message) {
                    $title = "{$userName}: {$activity->message}";
                } else {
                    $translations = [
                        'created' => 'erstellt',
                        'updated' => 'aktualisiert',
                        'deleted' => 'gelöscht',
                    ];
                    $translated = $translations[$event] ?? $event;

                    $changedFields = [];
                    $props = $activity->properties ?? [];
                    if (! empty($props)) {
                        $fieldKeys = isset($props['new']) ? array_keys($props['new']) : (isset($props['old']) ? [] : array_keys($props));
                        $fieldTranslations = [
                            'title' => 'Titel', 'description' => 'Beschreibung', 'due_date' => 'Fälligkeitsdatum',
                            'is_done' => 'Status', 'status' => 'Status', 'priority' => 'Priorität',
                            'name' => 'Name', 'user_in_charge_id' => 'Verantwortlicher',
                        ];
                        $changedFields = array_map(fn ($f) => $fieldTranslations[$f] ?? $f, $fieldKeys);
                    }

                    $title = $changedFields
                        ? "{$userName} hat " . implode(', ', array_slice($changedFields, 0, 3)) . " {$translated}"
                        : "{$userName} hat {$translated}";
                }

                // Resolve attachments
                $files = $attachmentsByActivity[$activity->id] ?? collect();
                $attachments = $files->map(fn (ContextFile $f) => [
                    'id' => $f->id,
                    'url' => $f->url,
                    'download_url' => $f->download_url,
                    'original_name' => $f->original_name,
                    'mime_type' => $f->mime_type,
                    'file_size' => $f->file_size,
                    'is_image' => $f->isImage(),
                ])->values()->toArray();

                return [
                    'id' => $activity->id,
                    'title' => $title,
                    'message' => $activity->message,
                    'user' => $userName,
                    'user_avatar' => $activity->user?->avatar,
                    'user_initials' => $this->initials($activity->user?->name ?? '?'),
                    'activity_type' => $activity->activity_type ?? 'system',
                    'is_mine' => $activity->user_id === $currentUserId,
                    'has_attachments' => ! empty($attachments),
                    'attachments' => $attachments,
                    'time' => $activity->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    /**
     * Dispatch context to ModalFiles — open file manager for current context.
     */
    public function dispatchFilesContext(): void
    {
        $channel = $this->channelId ? TerminalChannel::find($this->channelId) : null;
        if (! $channel || ! $channel->context_type || ! $channel->context_id) {
            return;
        }

        $this->dispatch('files', [
            'context_type' => $channel->context_type,
            'context_id' => $channel->context_id,
        ]);
        $this->dispatch('files:open');
    }

    /**
     * Dispatch context to ModalTagging — open tagging for current context.
     */
    public function dispatchTaggingContext(): void
    {
        $channel = $this->channelId ? TerminalChannel::find($this->channelId) : null;
        if (! $channel || ! $channel->context_type || ! $channel->context_id) {
            return;
        }

        $this->dispatch('tagging', [
            'context_type' => $channel->context_type,
            'context_id' => $channel->context_id,
        ]);
        $this->dispatch('tagging:open');
    }

    // ── Open / Switch Channel ──────────────────────────────────

    public function openChannel(int $channelId): void
    {
        $teamId = $this->teamId();
        $channel = TerminalChannel::where('id', $channelId)
            ->where('team_id', $teamId)
            ->firstOrFail();

        $this->channelId = $channel->id;
        $this->activeApp = 'chat';
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

    public function createChannel(string $name, ?string $description = null, ?string $icon = null, array $memberIds = []): void
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

        // Add selected members
        foreach ($memberIds as $userId) {
            if ((int) $userId === auth()->id()) {
                continue;
            }
            TerminalChannelMember::firstOrCreate(
                ['channel_id' => $channel->id, 'user_id' => (int) $userId],
                ['role' => 'member']
            );
        }

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

        // Only owners can remove members
        $isOwner = TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner) {
            return;
        }

        // Cannot remove yourself
        if ($userId === auth()->id()) {
            return;
        }

        TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->delete();
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

        // Context channels: any member can delete (will be recreated on demand)
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
                0, // will be updated after message create
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

        // Link attachments to the message
        if ($hasAttachments) {
            ContextFile::whereIn('id', $attachmentIds)
                ->where('context_type', TerminalMessage::class)
                ->where('context_id', 0)
                ->where('user_id', auth()->id())
                ->update(['context_id' => $message->id]);
        }

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

        // Store mentions (validate user IDs exist to avoid FK constraint violations)
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

        // Mark as read for sender
        $this->markAsRead($message->id);

        // Dispatch notifications
        $this->dispatchNotifications($channel, $message, $mentionUserIds);

        // Broadcast via WebSocket (for real-time updates to other users)
        try {
            TerminalMessageSent::dispatch(
                $channel->id,
                $message->id,
                auth()->id(),
            );
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

        // Max 50 pins per channel
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

    public function getBookmarks(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        $channelIds = TerminalChannelMember::where('user_id', auth()->id())
            ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
            ->pluck('channel_id');

        return TerminalBookmark::where('user_id', auth()->id())
            ->whereHas('message', fn ($q) => $q->whereIn('channel_id', $channelIds))
            ->with(['message.user:id,name,avatar', 'message.channel:id,name,type,context_type,context_id'])
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn ($bm) => $bm->message !== null)
            ->map(function ($bm) {
                $channelName = $bm->message->channel?->name;
                if (! $channelName && $bm->message->channel?->type === 'dm') {
                    $other = TerminalChannelMember::where('channel_id', $bm->message->channel_id)
                        ->where('user_id', '!=', auth()->id())
                        ->with('user:id,name')
                        ->first();
                    $channelName = $other?->user?->name ?? 'Chat';
                }

                return [
                    'id' => $bm->id,
                    'message_id' => $bm->message_id,
                    'channel_id' => $bm->message->channel_id,
                    'channel_name' => $channelName ?? 'Kontext',
                    'body_snippet' => \Illuminate\Support\Str::limit($bm->message->body_plain ?? strip_tags($bm->message->body_html), 80),
                    'user_name' => $bm->message->user?->name ?? 'Unbekannt',
                    'user_avatar' => $bm->message->user?->avatar,
                    'user_initials' => $this->initials($bm->message->user?->name ?? '?'),
                    'time' => $bm->message->created_at->format('H:i'),
                    'date' => $bm->message->created_at->translatedFormat('d. M'),
                ];
            })
            ->values()
            ->toArray();
    }

    // ── Forward ───────────────────────────────────────────────

    public function forwardMessage(int $messageId, int $targetChannelId): void
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return;
        }

        // Verify access to source message
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

        // Verify access to target channel
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
            TerminalMessageSent::dispatch(
                $targetChannel->id,
                $forwarded->id,
                auth()->id(),
            );
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

        // Cascade delete related records
        $msg->reactions()->delete();
        $msg->mentions()->delete();
        TerminalPin::where('message_id', $messageId)->delete();
        TerminalBookmark::where('message_id', $messageId)->delete();
        TerminalReminder::where('message_id', $messageId)->delete();

        // Delete child replies (and their reactions/mentions)
        foreach ($msg->replies as $reply) {
            $reply->reactions()->delete();
            $reply->mentions()->delete();
            TerminalPin::where('message_id', $reply->id)->delete();
            TerminalBookmark::where('message_id', $reply->id)->delete();
            TerminalReminder::where('message_id', $reply->id)->delete();
            $reply->delete();
        }

        $msg->delete();

        // Update parent reply_count if this was a reply
        if ($parentId) {
            $parent = TerminalMessage::find($parentId);
            if ($parent) {
                $parent->update(['reply_count' => $parent->replies()->count()]);
            }
        }

        // Update channel message_count
        if ($channel) {
            $channel->update(['message_count' => $channel->messages()->count()]);
        }

        // Invalidate messages cache
        unset($this->messages);
        unset($this->channels);
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

    // ── Search ─────────────────────────────────────────────────

    public function searchMessages(string $query): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        $channelIds = TerminalChannelMember::where('user_id', auth()->id())
            ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
            ->pluck('channel_id');

        return TerminalMessage::whereIn('channel_id', $channelIds)
            ->where('body_plain', 'LIKE', "%{$query}%")
            ->with(['user:id,name,avatar', 'channel:id,name,type,context_type,context_id'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(function (TerminalMessage $m) use ($query) {
                $plain = $m->body_plain ?? '';
                $pos = mb_stripos($plain, $query);
                $start = max(0, $pos - 30);
                $snippet = ($start > 0 ? '…' : '') . mb_substr($plain, $start, 80) . (mb_strlen($plain) > $start + 80 ? '…' : '');

                $channelName = $m->channel?->name;
                if (! $channelName && $m->channel?->type === 'dm') {
                    $other = TerminalChannelMember::where('channel_id', $m->channel_id)
                        ->where('user_id', '!=', auth()->id())
                        ->with('user:id,name')
                        ->first();
                    $channelName = $other?->user?->name ?? 'Chat';
                }

                return [
                    'id' => $m->id,
                    'channel_id' => $m->channel_id,
                    'channel_name' => $channelName ?? 'Kontext',
                    'channel_type' => $m->channel?->type,
                    'user_name' => $m->user?->name ?? 'Unbekannt',
                    'user_avatar' => $m->user?->avatar,
                    'user_initials' => $this->initials($m->user?->name ?? '?'),
                    'snippet' => $snippet,
                    'time' => $m->created_at->format('H:i'),
                    'date' => $m->created_at->translatedFormat('d. M'),
                ];
            })
            ->toArray();
    }

    // ── Permalink ──────────────────────────────────────────────

    public function getMessagePermalink(int $messageId): ?string
    {
        $message = TerminalMessage::find($messageId);
        if (! $message) {
            return null;
        }

        $isMember = TerminalChannelMember::where('channel_id', $message->channel_id)
            ->where('user_id', auth()->id())
            ->exists();

        if (! $isMember) {
            return null;
        }

        return config('app.url') . '/terminal?channel=' . $message->channel_id . '&message=' . $message->id;
    }

    // ── Computed Properties ────────────────────────────────────

    #[Computed]
    public function channels(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return ['dms' => [], 'channels' => [], 'context_groups' => []];
        }

        $userId = auth()->id();

        $memberships = TerminalChannelMember::where('user_id', $userId)
            ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
            ->with(['channel' => fn ($q) => $q->with('lastMessage:id,body_plain,created_at')])
            ->get();

        $dms = [];
        $channels = [];
        $contextGroups = [];

        // Label map for context type group names
        $groupLabelMap = [
            'PlannerTask' => 'Tasks', 'Task' => 'Tasks',
            'PlannerProject' => 'Projekte', 'Project' => 'Projekte',
            'HelpdeskTicket' => 'Tickets', 'Ticket' => 'Tickets',
            'CrmContact' => 'Kontakte', 'Contact' => 'Kontakte',
            'CrmCompany' => 'Unternehmen', 'Company' => 'Unternehmen',
            'Deal' => 'Deals',
            'RecruitingApplicant' => 'Bewerber', 'Applicant' => 'Bewerber',
            'PatientsPatient' => 'Patienten',
            'NotesNote' => 'Notizen',
            'NotesFolder' => 'Ordner',
            'Invoice' => 'Rechnungen',
            'PcCanvas' => 'Canvas',
            'Cycle' => 'OKR Cycles',
            'Okr' => 'OKRs',
            'Objective' => 'Objectives',
        ];

        // Icon map (reuse from getContextBreadcrumb)
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
            'Cycle' => '🔄',
            'Okr' => '🎯',
            'Objective' => '🎯',
        ];

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

            $lastTimestamp = $ch->lastMessage?->created_at?->timestamp ?? 0;

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
                'last_timestamp' => $lastTimestamp,
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
                $item['other_user_id'] = $other?->user_id;
                $dms[] = $item;
            } elseif ($ch->type === 'channel') {
                $channels[] = $item;
            } else {
                // Context channels — resolve breadcrumb and group by type
                $breadcrumb = $this->getContextBreadcrumb($ch->context_type, $ch->context_id);
                // Prefer persisted name, fall back to breadcrumb title (no type prefix — group header shows type)
                $item['name'] = $item['name'] ?: ($breadcrumb['title'] ?? 'Kontext');
                $item['context_label'] = $breadcrumb['label'] ?? 'Kontext';
                $item['context_icon'] = $breadcrumb['icon'] ?? '📎';

                $shortName = class_basename($ch->context_type ?? '');
                $groupKey = \Illuminate\Support\Str::snake($shortName);

                if (! isset($contextGroups[$groupKey])) {
                    $contextGroups[$groupKey] = [
                        'label' => $groupLabelMap[$shortName] ?? $shortName,
                        'icon' => $iconMap[$shortName] ?? '📎',
                        'items' => [],
                        'newest_timestamp' => 0,
                    ];
                }

                $contextGroups[$groupKey]['items'][] = $item;
                if ($lastTimestamp > $contextGroups[$groupKey]['newest_timestamp']) {
                    $contextGroups[$groupKey]['newest_timestamp'] = $lastTimestamp;
                }
            }
        }

        // Sort DMs and channels: unreads first, then by last_timestamp DESC
        $sort = fn ($a, $b) => $b['unread'] <=> $a['unread'] ?: $b['last_timestamp'] <=> $a['last_timestamp'];
        usort($dms, $sort);
        usort($channels, $sort);

        // Sort items within each context group by last_timestamp DESC
        foreach ($contextGroups as &$group) {
            usort($group['items'], fn ($a, $b) => $b['last_timestamp'] <=> $a['last_timestamp']);
        }
        unset($group);

        // Sort context groups by newest_timestamp DESC
        uasort($contextGroups, fn ($a, $b) => $b['newest_timestamp'] <=> $a['newest_timestamp']);

        return ['dms' => $dms, 'channels' => $channels, 'context_groups' => $contextGroups];
    }

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

        // Batch-load pins, bookmarks, reminders for this channel
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

        // Pin count for header badge
        $data['pin_count'] = TerminalPin::where('channel_id', $channel->id)->count();

        // Check if current user can delete this channel
        $data['can_delete'] = $channel->type === 'channel' && TerminalChannelMember::where('channel_id', $channel->id)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        // Context breadcrumb for context channels
        $data['context'] = null;
        if ($channel->isContext()) {
            $data['context'] = $this->getContextBreadcrumb(
                $channel->context_type,
                $channel->context_id,
            );
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

                // Private user channel for reminders
                $userId = auth()->id();
                $listeners["echo-private:terminal.user.{$userId},.reminder.due"] = 'onReminderDue';

                // Presence channel for online status
                $listeners["echo-presence:terminal.team.{$teamId},here"] = 'onPresenceHere';
                $listeners["echo-presence:terminal.team.{$teamId},joining"] = 'onPresenceJoining';
                $listeners["echo-presence:terminal.team.{$teamId},leaving"] = 'onPresenceLeaving';
            }
        } catch (\Throwable $e) {
            // Fail silently — listeners will be re-registered on next render
        }

        return $listeners;
    }

    public function onMessageReceived($payload = null): void
    {
        unset($this->messages);
        unset($this->channels);
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
