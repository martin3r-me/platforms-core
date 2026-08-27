<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\TerminalChannel;
use Platform\Core\Models\TerminalChannelMember;
use Platform\Core\Models\TerminalMessage;
use Platform\Core\Models\TerminalAgenda;
use Platform\Core\Models\TerminalAgendaMember;
use Platform\Core\Models\TerminalBookmark;
use Platform\Core\Models\TerminalPin;
use Platform\Core\Models\User;
use Platform\Core\Services\ContextFileService;
use Platform\Core\Terminal\TerminalContext;
use Platform\Core\Terminal\TerminalAppRegistry;
use Platform\Organization\Models\OrganizationContext;
use Illuminate\Support\Str;
use Platform\Crm\Livewire\Concerns\WithCommsChat;
use Platform\Crm\Livewire\Concerns\WithCommsChannelSettings;

/**
 * Terminal UI shell with messaging, DMs, group channels, and context awareness.
 */
class Terminal extends Component
{
    use WithFileUploads;
    use WithCommsChat {
        WithCommsChat::setCommsContext as initCommsFromPayload;
        WithCommsChat::buildContextThreadsList as traitBuildContextThreadsList;
        WithCommsChat::switchToContextThread as traitSwitchToContextThread;
    }
    use WithCommsChannelSettings;

    public ?string $contextType = null;
    public ?int $contextId = null;
    public ?string $contextSubject = null;
    public ?string $contextSource = null;
    public ?string $contextUrl = null;
    public array $contextMeta = [];
    public ?int $channelId = null;
    public array $onlineUserIds = [];
    public string $activeApp = 'chat';
    public array $availableApps = ['chat' => true, 'agenda' => true, 'activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false, 'comms' => false, 'feature-request' => true];
    // ── Comms App ────────────────────────────────────────────
    public bool $commsInitialized = false;
    public bool $commsShowNewMessage = false;  // overlay panel above timeline
    public bool $commsShowSettings = false;    // overlay modal over timeline
    public string $commsComposeChannel = 'email'; // 'email' | 'whatsapp' — for compose + new message
    public bool $commsIncludeContext = false;     // toggle: append context block to outbound message
    public array $otherRecentThreads = [];
    public bool $showOtherThreads = false;
    public ?int $activeOtherThreadIndex = null;

    // ── ExtraFields App ──────────────────────────────────────
    public ?string $efContextType = null;
    public ?int $efContextId = null;

    // Agenda-App: State + Logik liegen vollständig in core.terminal.agenda (Kind).

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
            $this->availableApps = ['chat' => true, 'agenda' => true, 'activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false, 'comms' => false, 'feature-request' => true];
            $this->commsInitialized = false;
            $this->commsShowNewMessage = false;
            $this->commsShowSettings = false;
            $this->commsIncludeContext = false;
            $this->activeOtherThreadIndex = null;
            $this->otherRecentThreads = [];
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

        // Forward context to the Comms chat trait (email + WhatsApp runtime)
        $this->initCommsFromPayload($payload);
        $this->availableApps['comms'] = true;

        // If user is already on the Comms tab, reinitialize immediately
        // (updatedActiveApp won't fire because activeApp hasn't changed)
        if ($this->activeApp === 'comms') {
            $this->initCommsRuntime();
        } else {
            $this->commsInitialized = false;
        }

        // Broadcast context to all Terminal child components
        $this->broadcastContext();
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
     * Enable the Files app tab via dispatch.
     * Modules fire dispatch('terminal:app:files') to unlock the Files tab.
     */
    #[On('terminal:app:files')]
    public function setAppFiles(): void
    {
        $this->availableApps['files'] = true;
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

        // Broadcast context to all Terminal child components
        $this->broadcastContext();
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

    public function openContextChannel(): void
    {
        if ($this->contextType && $this->contextId) {
            $this->resolveContextChannel();
            $this->activeApp = 'chat';
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
                'name' => $this->contextSubject,
                'meta' => $this->contextUrl ? ['url' => $this->contextUrl] : null,
            ]);
        } elseif ($this->contextSubject && $channel->name !== $this->contextSubject) {
            $channel->update(['name' => $this->contextSubject]);
        }

        $this->channelId = $channel->id;
        $this->ensureMembership($channel);

        // Clear cached computeds so re-render picks up the new channel
        unset($this->channels, $this->activeChannel);

        $this->dispatch('terminal-chat-channel', channelId: $channel->id);
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
     * Load activities for the context entity of the active channel.
     */
    #[Computed]
    public function contextActivities(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        if (! class_exists($this->contextType)) {
            return [];
        }

        $model = $this->contextType::find($this->contextId);
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
                    'user_avatar' => $activity->user?->avatarUrl(),
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
     * Load files for the context entity (Browse in Files app).
     */
    #[Computed]
    public function contextFiles(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        return ContextFile::where('context_type', $this->contextType)
            ->where('context_id', $this->contextId)
            ->with(['variants', 'user'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'token' => $file->token,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'url' => $file->url,
                    'download_url' => $file->download_url,
                    'is_image' => $file->isImage(),
                    'thumbnail' => $file->variants()->where('variant_type', 'thumbnail_4_3')->first()?->url
                        ?? $file->variants()->where('variant_type', 'like', 'thumbnail_%')->first()?->url
                        ?? null,
                    'created_at' => $file->created_at->diffForHumans(),
                    'uploaded_by' => $file->user?->name ?? 'Unbekannt',
                ];
            })
            ->toArray();
    }

    /**
     * Open terminal in file-picker mode (select files → return IDs).
     * Modules fire dispatch('terminal:files:pick', [...]) to trigger this.
     */
    #[On('terminal:files:pick')]
    public function openFilePicker(array $payload = []): void
    {
        $this->activeApp = 'files';
        $this->dispatch('toggle-terminal-open');
        $this->dispatch('terminal-file-picker-open',
            multiple: $payload['multiple'] ?? true,
            callback: $payload['callback'] ?? null,
            referenceType: $payload['reference_type'] ?? null,
            referenceId: isset($payload['reference_id']) ? (int) $payload['reference_id'] : null,
            assignReferenceId: null,
        );
    }

    /**
     * Open terminal in file-assign mode (select file → update existing reference).
     */
    #[On('terminal:files:assign')]
    public function openFileAssign(array $payload = []): void
    {
        $this->activeApp = 'files';
        $this->dispatch('toggle-terminal-open');
        $this->dispatch('terminal-file-picker-open',
            multiple: false,
            callback: null,
            referenceType: null,
            referenceId: null,
            assignReferenceId: isset($payload['reference_id']) ? (int) $payload['reference_id'] : null,
        );
    }

    /**
     * Refresh file badge count when Files child reports changes.
     */
    #[On('terminal-files-changed')]
    public function onFilesChanged(): void
    {
        unset($this->contextFiles);
    }

    /**
     * Enable the Tags app tab via dispatch.
     * Modules fire dispatch('terminal:app:tags') to unlock the Tags tab.
     */
    #[On('terminal:app:tags')]
    public function setAppTags(): void
    {
        $this->availableApps['tags'] = true;
    }

    /**
     * Enable the Time app tab via dispatch.
     * Modules fire dispatch('terminal:app:time') to unlock the Time tab.
     */
    #[On('terminal:app:time')]
    public function setAppTime(): void
    {
        $this->availableApps['time'] = true;
    }

    /**
     * Listen to the organization dispatch from modules.
     * When allow_time_entry is true, enable the Time tab automatically.
     */
    #[On('organization')]
    public function setOrganizationContext(array $payload = []): void
    {
        if (! empty($payload['allow_time_entry'])) {
            $this->availableApps['time'] = true;
        }
    }

    /**
     * Listen to the keyresult dispatch from modules.
     * Enable the OKR tab when a module sets KeyResult context.
     */
    #[On('keyresult')]
    public function setKeyResultContext(array $payload = []): void
    {
        if (! empty($payload['context_type']) && ! empty($payload['context_id'])) {
            $this->availableApps['okr'] = true;
        }
    }

    // ── Comms App ─────────────────────────────────────────────

    /**
     * WithCommsChat abstract: only poll when comms tab is active.
     */
    protected function shouldRefreshTimelines(): bool
    {
        return $this->activeApp === 'comms' && $this->commsInitialized;
    }

    /**
     * Lazy-init comms runtime when tab is first opened.
     */
    public function updatedActiveApp(string $value): void
    {
        if ($value === 'comms' && !$this->commsInitialized) {
            $this->initCommsRuntime();
        }
    }

    /**
     * Initialize (or reinitialize) the Comms runtime for the current context.
     * Called on first tab switch and on context change while tab is active.
     */
    protected function initCommsRuntime(): void
    {
        if (!$this->contextModel || !$this->contextModelId) {
            return;
        }

        $this->loadEmailRuntime();
        $this->loadWhatsAppRuntime();
        $this->buildContextThreadsList();

        if (!empty($this->allContextThreads)) {
            $this->switchToContextThread(0);
        } else {
            $this->activeContextThreadIndex = null;
        }

        // Pre-load WA templates so they're immediately available
        if ($this->activeWhatsAppChannelId) {
            $this->loadWhatsAppTemplates();
        }

        // Set default compose channel based on available channels
        if (!empty($this->emailChannels)) {
            $this->commsComposeChannel = 'email';
        } elseif (!empty($this->whatsappChannels)) {
            $this->commsComposeChannel = 'whatsapp';
        }

        $this->commsInitialized = true;
    }

    /**
     * Open comms settings as overlay (timeline stays visible underneath).
     */
    public function openCommsSettings(): void
    {
        $this->commsShowSettings = true;
        $this->loadPostmarkConnection();
        $this->loadCommsSettingsChannels();
        $this->loadAvailableWhatsAppAccounts();
    }

    /**
     * Close all overlays — back to pure timeline.
     */
    public function commsBackToTimeline(): void
    {
        $this->commsShowNewMessage = false;
        $this->commsShowSettings = false;
    }

    /**
     * Close settings overlay.
     */
    public function closeCommsSettings(): void
    {
        $this->commsShowSettings = false;
    }

    /**
     * Toggle new-message panel (overlay above timeline).
     */
    public function openCommsNewMessage(): void
    {
        $this->commsShowNewMessage = !$this->commsShowNewMessage;
        if ($this->commsShowNewMessage) {
            $this->commsShowSettings = false; // close settings if open
            // Pre-load WA templates for the active channel
            if ($this->activeWhatsAppChannelId) {
                $this->loadWhatsAppTemplates();
            }
            // Reset template selection for clean state
            $this->whatsappSelectedTemplateId = null;
            $this->whatsappTemplatePreview = [];
            $this->whatsappTemplateVariables = [];
        }
    }

    /**
     * Send email from "new message" view and switch to the new thread.
     */
    public function sendNewEmail(): void
    {
        $this->maybeAppendContextToEmailBody();
        $this->sendEmail();
        if ($this->activeEmailThreadId) {
            $this->commsShowNewMessage = false;
        }
    }

    /**
     * Send WhatsApp from "new message" panel and close it.
     */
    public function sendNewWhatsApp(): void
    {
        $this->maybeAppendContextToWhatsAppBody();
        $this->sendWhatsApp();
        if ($this->activeWhatsAppThreadId) {
            $this->commsShowNewMessage = false;
        }
    }

    /**
     * Send WhatsApp template from "new message" panel and close it.
     */
    public function sendNewWhatsAppTemplate(): void
    {
        $this->sendWhatsAppTemplate();
        if ($this->activeWhatsAppThreadId) {
            $this->commsShowNewMessage = false;
        }
    }

    /**
     * Build a plain-text context footer block from available context properties.
     */
    protected function buildContextFooter(): ?string
    {
        if (! $this->commsIncludeContext) {
            return null;
        }

        $parts = [];
        if ($this->contextSubject) {
            $parts[] = $this->contextSubject;
        }
        if ($this->contextDescription) {
            $parts[] = $this->contextDescription;
        }
        foreach ($this->contextMeta as $key => $value) {
            if (is_string($value) && $value !== '') {
                $parts[] = ucfirst((string) $key) . ': ' . $value;
            }
        }
        if ($this->contextUrl) {
            $parts[] = $this->contextUrl;
        }

        return ! empty($parts) ? "\n\n---\n" . implode("\n", $parts) : null;
    }

    /**
     * If context toggle is on for a new email, append context block to body.
     */
    protected function maybeAppendContextToEmailBody(): void
    {
        $footer = $this->buildContextFooter();
        if ($footer && ! empty($this->emailCompose['body'])) {
            $this->emailCompose['body'] .= $footer;
        }
        $this->commsIncludeContext = false;
    }

    /**
     * If context toggle is on for a new WA message, append context block to body.
     */
    protected function maybeAppendContextToWhatsAppBody(): void
    {
        $footer = $this->buildContextFooter();
        if ($footer && ! empty($this->whatsappCompose['body'])) {
            $this->whatsappCompose['body'] .= $footer;
        }
        $this->commsIncludeContext = false;
    }

    /**
     * When WA channel changes in new-message view, reload templates.
     * Parent trait's updatedActiveWhatsAppChannelId handles thread switching;
     * we additionally load templates for the new-message context.
     */
    /**
     * Override trait's buildContextThreadsList to enrich WA threads with window_open status
     * and load recent non-context threads from the same channels.
     */
    public function buildContextThreadsList(): void
    {
        // Call trait logic (populates $this->allContextThreads)
        $this->traitBuildContextThreadsList();

        // Collect context thread IDs to exclude from "other" list
        $contextEmailIds = [];
        $contextWaIds = [];

        // Enrich WA threads with 24h window info
        foreach ($this->allContextThreads as &$thread) {
            if ($thread['type'] === 'whatsapp') {
                $contextWaIds[] = $thread['thread_id'];
                $waThread = \Platform\Crm\Models\CommsWhatsAppThread::query()->whereKey($thread['thread_id'])->first();
                $thread['window_open'] = $waThread?->isWindowOpen() ?? false;
                $thread['window_expires_at'] = $waThread?->windowExpiresAt()?->toIso8601String();
            } else {
                $contextEmailIds[] = $thread['thread_id'];
            }
        }
        unset($thread);

        // Load recent non-context threads from same channels
        $this->loadOtherRecentThreads($contextEmailIds, $contextWaIds);
    }

    /**
     * Load recent threads from the same channels that are NOT linked to the current context.
     */
    protected function loadOtherRecentThreads(array $excludeEmailIds, array $excludeWaIds): void
    {
        $this->otherRecentThreads = [];

        $emailChannelIds = collect($this->emailChannels)->pluck('id')->all();
        $waChannelIds = collect($this->whatsappChannels)->pluck('id')->all();

        if (empty($emailChannelIds) && empty($waChannelIds)) {
            return;
        }

        $list = [];
        $emailChannelLabels = collect($this->emailChannels)->keyBy('id');
        $waChannelLabels = collect($this->whatsappChannels)->keyBy('id');

        // Recent email threads (not in context)
        if (!empty($emailChannelIds)) {
            $emailThreads = \Platform\Crm\Models\CommsEmailThread::query()
                ->whereIn('comms_channel_id', $emailChannelIds)
                ->when(!empty($excludeEmailIds), fn ($q) => $q->whereNotIn('id', $excludeEmailIds))
                ->orderByRaw('GREATEST(COALESCE(last_inbound_at, updated_at), COALESCE(last_outbound_at, updated_at)) DESC')
                ->limit(10)
                ->get();

            foreach ($emailThreads as $t) {
                $lastAt = $t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at))
                    ? $t->last_inbound_at
                    : ($t->last_outbound_at ?: $t->updated_at);

                $list[] = [
                    'type' => 'email',
                    'thread_id' => (int) $t->id,
                    'channel_id' => (int) $t->comms_channel_id,
                    'label' => (string) ($t->subject ?: 'Ohne Betreff'),
                    'counterpart' => (string) ($t->last_inbound_from_address ?: $t->last_outbound_to_address ?: ''),
                    'last_at' => $lastAt?->format('d.m. H:i') ?? '',
                    'last_at_sort' => $lastAt?->toDateTimeString() ?? '',
                    'channel_label' => (string) ($emailChannelLabels[(int) $t->comms_channel_id]['label'] ?? ''),
                ];
            }
        }

        // Recent WA threads (not in context)
        if (!empty($waChannelIds)) {
            $waThreads = \Platform\Crm\Models\CommsWhatsAppThread::query()
                ->whereIn('comms_channel_id', $waChannelIds)
                ->when(!empty($excludeWaIds), fn ($q) => $q->whereNotIn('id', $excludeWaIds))
                ->orderByRaw('GREATEST(COALESCE(last_inbound_at, updated_at), COALESCE(last_outbound_at, updated_at)) DESC')
                ->limit(10)
                ->get();

            foreach ($waThreads as $t) {
                $lastAt = $t->last_inbound_at && (!$t->last_outbound_at || $t->last_inbound_at->greaterThanOrEqualTo($t->last_outbound_at))
                    ? $t->last_inbound_at
                    : ($t->last_outbound_at ?: $t->updated_at);

                $waChannel = $waChannelLabels[(int) $t->comms_channel_id] ?? [];
                $channelLabel = ($waChannel['name'] ?? '') ?: ($waChannel['label'] ?? '');

                $list[] = [
                    'type' => 'whatsapp',
                    'thread_id' => (int) $t->id,
                    'channel_id' => (int) $t->comms_channel_id,
                    'label' => (string) ($t->remote_phone_number ?: '—'),
                    'counterpart' => (string) ($t->remote_phone_number ?: ''),
                    'last_at' => $lastAt?->format('d.m. H:i') ?? '',
                    'last_at_sort' => $lastAt?->toDateTimeString() ?? '',
                    'channel_label' => (string) $channelLabel,
                    'window_open' => $t->isWindowOpen(),
                ];
            }
        }

        usort($list, fn ($a, $b) => strcmp((string) $b['last_at_sort'], (string) $a['last_at_sort']));
        $this->otherRecentThreads = array_values(array_slice($list, 0, 15));
    }

    /**
     * Override trait's switchToContextThread to also clear the "other" active index
     * and set compose channel to match thread type.
     */
    public function switchToContextThread(int $index): void
    {
        $this->activeOtherThreadIndex = null;
        $this->commsShowNewMessage = false;
        $this->traitSwitchToContextThread($index);

        // Set compose channel to match the selected thread type
        if (isset($this->allContextThreads[$index])) {
            $this->commsComposeChannel = $this->allContextThreads[$index]['type'] === 'whatsapp' ? 'whatsapp' : 'email';
        }
    }

    /**
     * Switch to a thread from the "other recent" list (non-context thread).
     */
    public function switchToOtherThread(int $index): void
    {
        if (!isset($this->otherRecentThreads[$index])) {
            return;
        }

        $entry = $this->otherRecentThreads[$index];
        $this->activeContextThreadIndex = null; // Deselect context threads
        $this->activeOtherThreadIndex = $index;
        $this->commsShowNewMessage = false;
        $this->commsComposeChannel = $entry['type'] === 'whatsapp' ? 'whatsapp' : 'email';

        if ($entry['type'] === 'email') {
            $this->activeEmailChannelId = (int) $entry['channel_id'];
            $this->refreshActiveEmailChannelLabel();
            $this->loadEmailThreads();
            $this->setActiveEmailThread((int) $entry['thread_id']);
        } elseif ($entry['type'] === 'whatsapp') {
            $this->activeWhatsAppChannelId = (int) $entry['channel_id'];
            $this->refreshActiveWhatsAppChannelLabel();
            $this->loadWhatsAppThreads();
            $this->setActiveWhatsAppThread((int) $entry['thread_id']);
        }
    }

    public function commsLoadTemplatesForChannel(): void
    {
        if ($this->activeWhatsAppChannelId) {
            $this->loadWhatsAppTemplates();
        }
    }

    /**
     * Override trait's setActiveEmailThread to also fill 'to' from last outbound
     * when there are no inbound mails (outbound-only thread scenario).
     */
    public function setActiveEmailThread(int $threadId): void
    {
        // Call parent trait logic via the inherited method chain
        $this->activeEmailThreadId = $threadId;
        $this->resetForwardState();
        $this->loadEmailTimeline();

        $thread = \Platform\Crm\Models\CommsEmailThread::query()->whereKey($threadId)->first();

        // 1. Try inbound address (trait's original logic)
        if ($thread?->last_inbound_from_address) {
            $this->emailCompose['to'] = (string) $thread->last_inbound_from_address;
        } else {
            $lastInbound = \Platform\Crm\Models\CommsEmailInboundMail::query()
                ->where('thread_id', $threadId)
                ->orderByDesc('received_at')
                ->first();
            if ($lastInbound?->from) {
                $this->emailCompose['to'] = $this->extractEmailAddress((string) $lastInbound->from) ?: (string) $lastInbound->from;
            }
        }

        // 2. Fallback: last outbound's "to" address (for outbound-only threads)
        if (empty(trim($this->emailCompose['to'] ?? ''))) {
            $lastOutbound = \Platform\Crm\Models\CommsEmailOutboundMail::query()
                ->where('thread_id', $threadId)
                ->orderByDesc('sent_at')
                ->first();
            if ($lastOutbound?->to) {
                $this->emailCompose['to'] = $this->extractEmailAddress((string) $lastOutbound->to) ?: (string) $lastOutbound->to;
            }
        }

        // 3. Final fallback: context recipient
        if (empty(trim($this->emailCompose['to'] ?? ''))) {
            $contextEmail = $this->findContextRecipientByType('email');
            if ($contextEmail) {
                $this->emailCompose['to'] = $contextEmail;
            }
        }

        $this->dispatch('comms:scroll-bottom');
    }

    /**
     * Open tags app from sidebar button.
     */
    public function openTagsApp(): void
    {
        $this->availableApps['tags'] = true;
        $this->activeApp = 'tags';
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
        unset($this->channels, $this->activeChannel);
        $this->dispatch('terminal-chat-channel', channelId: $channel->id);
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
        $this->activeApp = 'chat';
        $this->ensureMembership($channel);
        unset($this->channels, $this->activeChannel);
        $this->dispatch('terminal-chat-channel', channelId: $channel->id);
    }

    // ── Group Channel ──────────────────────────────────────────

    public function createChatChannel(string $name, ?string $description = null, ?string $icon = null, array $memberIds = []): void
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
        $this->activeApp = 'chat';
        unset($this->channels, $this->activeChannel);
        $this->dispatch('terminal-chat-channel', channelId: $channel->id);
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
        unset($this->channels, $this->activeChannel);
        $this->dispatch('terminal-chat-channel', channelId: null);
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
        unset($this->channels, $this->activeChannel);
        $this->dispatch('terminal-chat-channel', channelId: null);
    }

    // ── Bookmarks (sidebar) ──────────────────────────────────

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
                    'user_avatar' => $bm->message->user?->avatarUrl(),
                    'user_initials' => $this->initials($bm->message->user?->name ?? '?'),
                    'time' => $bm->message->created_at->format('H:i'),
                    'date' => $bm->message->created_at->translatedFormat('d. M'),
                ];
            })
            ->values()
            ->toArray();
    }

    // ── Team Members (for DM picker / mentions) ────────────────

    public function getTeamMembers(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        $team = auth()->user()?->currentTeam;
        if (! $team) {
            return [];
        }

        return $team
            ->users()
            ->where('users.id', '!=', auth()->id())
            ->select('users.id', 'users.name', 'users.avatar')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatarUrl(),
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
                    'user_avatar' => $m->user?->avatarUrl(),
                    'user_initials' => $this->initials($m->user?->name ?? '?'),
                    'snippet' => $snippet,
                    'time' => $m->created_at->format('H:i'),
                    'date' => $m->created_at->translatedFormat('d. M'),
                ];
            })
            ->toArray();
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
        if (! $userId) {
            return ['dms' => [], 'channels' => [], 'context_groups' => []];
        }

        $memberships = TerminalChannelMember::where('user_id', $userId)
            ->whereHas('channel', fn ($q) => $q->where('team_id', $teamId))
            ->with(['channel' => fn ($q) => $q->with('lastMessage:id,body_plain,created_at')])
            ->get();

        // Unread-Counts für ALLE Channels in EINER Query statt COUNT je Channel (N+1).
        // Zählt Root-Nachrichten neuer als last_read_message_id (bzw. alle, wenn nie gelesen).
        $channelIds = $memberships->pluck('channel_id')->all();
        $unreadByChannel = [];
        if (! empty($channelIds)) {
            $unreadByChannel = TerminalMessage::query()
                ->join('terminal_channel_members as cm', function ($join) use ($userId) {
                    $join->on('cm.channel_id', '=', 'terminal_messages.channel_id')
                        ->where('cm.user_id', '=', $userId);
                })
                ->whereIn('terminal_messages.channel_id', $channelIds)
                ->whereNull('terminal_messages.parent_id')
                ->where(function ($q) {
                    $q->whereNull('cm.last_read_message_id')
                        ->orWhereColumn('terminal_messages.id', '>', 'cm.last_read_message_id');
                })
                ->groupBy('terminal_messages.channel_id')
                ->selectRaw('terminal_messages.channel_id as cid, COUNT(*) as cnt')
                ->pluck('cnt', 'cid')
                ->toArray();
        }

        // DM-Gegenüber für ALLE DMs in EINER Query statt je DM (N+1). Nur DM-Channels,
        // damit nicht Avatare aller Mitglieder großer Channels geladen werden.
        $dmChannelIds = $memberships
            ->filter(fn ($m) => $m->channel?->type === 'dm')
            ->pluck('channel_id')
            ->all();
        $dmOthers = collect();
        if (! empty($dmChannelIds)) {
            $dmOthers = TerminalChannelMember::whereIn('channel_id', $dmChannelIds)
                ->where('user_id', '!=', $userId)
                ->with('user:id,name,avatar')
                ->get()
                ->keyBy('channel_id');
        }

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

            $unread = $unreadByChannel[$ch->id] ?? 0;

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
                $other = $dmOthers->get($ch->id);
                $item['name'] = $other?->user?->name ?? 'Unbekannt';
                $item['avatar'] = $other?->user?->avatarUrl();
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
            'avatar' => $m->user?->avatarUrl(),
            'initials' => $this->initials($m->user?->name ?? '?'),
        ])->toArray();

        $data = [
            'id' => $channel->id,
            'type' => $channel->type,
            'name' => $channel->name,
            'icon' => $channel->icon,
            'description' => $channel->description,
            'context_type' => $channel->context_type,
            'context_id' => $channel->context_id,
            'member_count' => count($members),
            'members' => $members,
        ];

        if ($channel->isDm()) {
            $other = TerminalChannelMember::where('channel_id', $channel->id)
                ->where('user_id', '!=', auth()->id())
                ->with('user:id,name,avatar')
                ->first();
            $data['name'] = $other?->user?->name ?? 'Unbekannt';
            $data['avatar'] = $other?->user?->avatarUrl();
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

    // ── Echo Listeners (Presence only — message listeners moved to Chat child) ─

    public function getListeners(): array
    {
        $listeners = [];

        try {
            $teamId = $this->teamId();
            if ($teamId && auth()->check()) {
                // Presence channel for online status (used in sidebar + chat header)
                $listeners["echo-presence:terminal.team.{$teamId},here"] = 'onPresenceHere';
                $listeners["echo-presence:terminal.team.{$teamId},joining"] = 'onPresenceJoining';
                $listeners["echo-presence:terminal.team.{$teamId},leaving"] = 'onPresenceLeaving';
            }
        } catch (\Throwable $e) {
            // Fail silently
        }

        return $listeners;
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

    // ── ExtraFields App ───────────────────────────────────────

    #[On('extrafields')]
    public function setExtraFieldsContext(array $payload = []): void
    {
        $this->efContextType = $payload['context_type'] ?? null;
        $this->efContextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;
        $this->availableApps['extrafields'] = true;
    }

    /**
     * Tab-Aktivierung: Panel freischalten + wechseln. Reset/Laden passiert jetzt
     * in der Kind-Komponente (core.terminal.extra-fields) via mount()/Reactive-Props.
     */
    public function openExtraFieldsApp(): void
    {
        $this->availableApps['extrafields'] = true;
        $this->activeApp = 'extrafields';
    }

    // ── Render ─────────────────────────────────────────────────

    /**
     * The Terminal's current context as a typed value object, handed to apps
     * for availability checks and passed down on mount.
     */
    public function terminalContext(): TerminalContext
    {
        return new TerminalContext(
            teamId:      $this->teamId(),
            contextType: $this->contextType,
            contextId:   $this->contextId,
            subject:     $this->contextSubject,
            source:      $this->contextSource,
        );
    }

    /**
     * Apps contributed via TerminalAppRegistry that are available for the
     * current context, in rail order. Rendered alongside the built-in apps
     * while the shell is progressively migrated onto the registry.
     *
     * @return array<string, \Platform\Core\Terminal\Contracts\TerminalApp>
     */
    #[Computed]
    public function registryApps(): array
    {
        return TerminalAppRegistry::availableFor($this->terminalContext());
    }

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

    protected function broadcastContext(): void
    {
        $this->dispatch('terminal-context-changed',
            contextType: $this->contextType,
            contextId: $this->contextId,
            contextSubject: $this->contextSubject,
            contextSource: $this->contextSource,
            contextUrl: $this->contextUrl,
            contextMeta: $this->contextMeta,
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
