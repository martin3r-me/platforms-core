<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\User;
use Platform\Core\Services\ContextFileService;
use Platform\Core\Terminal\TerminalContext;
use Platform\Core\Terminal\TerminalAppRegistry;
use Illuminate\Support\Str;
use Platform\Crm\Livewire\Concerns\WithCommsChat;
use Platform\Crm\Livewire\Concerns\WithCommsChannelSettings;

/**
 * Terminal UI shell: Comms, Feature-Request und Kontext-Apps (Chat entfernt, Agenda lebt in home + Kontext-Panel).
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
    public string $activeApp = 'feature-request';
    public array $availableApps = ['activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false, 'comms' => false, 'feature-request' => true];
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

    // ── Context ────────────────────────────────────────────────

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
            $this->availableApps = ['activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false, 'comms' => false, 'feature-request' => true];
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
     * Open the terminal panel.
     * Usage: $dispatch('terminal:open') — opens terminal with current page context.
     */
    #[On('terminal:open')]
    public function openTerminal(array $payload = []): void
    {
        // Optionally set context if provided
        if (! empty($payload['context_type']) && ! empty($payload['context_id'])) {
            $this->setContext($payload);
        }

        $this->dispatch('toggle-terminal-open');
    }

    /**
     * Resolve a human-readable breadcrumb for a context.
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
            'PcCanvas' => '🎨', 'PlannerProjectCanvas' => '🎨',
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
     * Load activities for the context entity.
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
