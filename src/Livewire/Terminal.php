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
use Platform\Organization\Models\OrganizationContext;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Core\Models\CoreLookup;
use Platform\Core\Models\CoreLookupValue;
use Platform\Core\Services\ExtraFieldCircularDependencyDetector;
use Platform\Core\Services\ExtraFieldConditionEvaluator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
    public array $availableApps = ['chat' => true, 'agenda' => true, 'activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false, 'comms' => false];
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
    public string $efTab = 'fields'; // 'fields' | 'lookups'
    public string $efEditFieldTab = 'basis'; // 'basis' | 'options' | 'conditions' | 'verification' | 'autofill'

    // Definitions
    public array $efDefinitions = [];
    public ?int $efEditingDefinitionId = null;
    public array $efNewField = [
        'name' => '',
        'label' => '',
        'description' => '',
        'type' => 'text',
        'is_required' => false,
        'is_mandatory' => false,
        'is_encrypted' => false,
        'options' => [],
        'is_multiple' => false,
        'verify_by_llm' => false,
        'verify_instructions' => '',
        'auto_fill_source' => '',
        'auto_fill_prompt' => '',
        'lookup_id' => null,
        'regex_pattern' => '',
        'regex_description' => '',
        'regex_error' => '',
        'placeholder' => '',
        'visibility' => [
            'enabled' => false,
            'logic' => 'AND',
            'groups' => [],
        ],
    ];
    public array $efEditField = [
        'label' => '',
        'description' => '',
        'type' => 'text',
        'is_required' => false,
        'is_mandatory' => false,
        'is_encrypted' => false,
        'options' => [],
        'is_multiple' => false,
        'verify_by_llm' => false,
        'verify_instructions' => '',
        'auto_fill_source' => '',
        'auto_fill_prompt' => '',
        'lookup_id' => null,
        'regex_pattern' => '',
        'regex_description' => '',
        'regex_error' => '',
        'placeholder' => '',
        'visibility' => [
            'enabled' => false,
            'logic' => 'AND',
            'groups' => [],
        ],
    ];
    public string $efNewOptionText = '';
    public string $efEditOptionText = '';

    // Lookups
    public array $efLookups = [];
    public ?int $efEditingLookupId = null;
    public array $efNewLookup = ['name' => '', 'label' => '', 'description' => ''];
    public array $efEditLookup = ['label' => '', 'description' => ''];

    // Lookup Values
    public array $efLookupValues = [];
    public ?int $efSelectedLookupId = null;
    public string $efNewLookupValueText = '';
    public string $efNewLookupValueLabel = '';

    // ── Agenda App ────────────────────────────────────────────
    public ?int $activeAgendaId = null;
    public string $agendaView = 'board'; // 'board' | 'day'
    public string $agendaDayDate = '';    // Y-m-d for "Mein Tag" navigation

    // ── Lifecycle ──────────────────────────────────────────────

    public function mount(): void
    {
        $this->agendaDayDate = now()->toDateString();

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
            $this->availableApps = ['chat' => true, 'agenda' => true, 'activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false, 'comms' => false];
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
                    'user_avatar' => $bm->message->user?->avatar,
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

    public function openExtraFieldsApp(): void
    {
        $this->availableApps['extrafields'] = true;
        $this->activeApp = 'extrafields';
        $this->efTab = 'fields';
        $this->efEditingDefinitionId = null;
        $this->efEditFieldTab = 'basis';
        $this->efResetForm();
        $this->efResetLookupForm();
        $this->efLoadDefinitions();
        $this->efLoadLookups();
    }

    // ── EF: Load & CRUD Definitions ─────────────────────────

    public function efLoadDefinitions(): void
    {
        if (! $this->efContextType) {
            $this->efDefinitions = [];
            return;
        }

        if (! class_exists($this->efContextType)) {
            $this->efDefinitions = [];
            return;
        }

        try {
            if (! Schema::hasTable('core_extra_field_definitions')) {
                $this->efDefinitions = [];
                return;
            }
        } catch (\Exception $e) {
            $this->efDefinitions = [];
            return;
        }

        try {
            $teamId = $this->efGetTeamId();
            if (! $teamId) {
                $this->efDefinitions = [];
                return;
            }

            $this->efDefinitions = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->efContextType, $this->efContextId)
                ->orderBy('order')
                ->orderBy('label')
                ->get()
                ->map(function ($def) {
                    return [
                        'id' => $def->id,
                        'name' => $def->name,
                        'label' => $def->label,
                        'description' => $def->description,
                        'type' => $def->type,
                        'type_label' => $def->type_label,
                        'is_required' => $def->is_required,
                        'is_mandatory' => $def->is_mandatory,
                        'is_encrypted' => $def->is_encrypted,
                        'is_global' => $def->isGlobal(),
                        'options' => $def->options,
                        'visibility_config' => $def->visibility_config,
                        'has_visibility_conditions' => $def->hasVisibilityConditions(),
                        'verify_by_llm' => $def->verify_by_llm,
                        'verify_instructions' => $def->verify_instructions,
                        'auto_fill_source' => $def->auto_fill_source,
                        'auto_fill_prompt' => $def->auto_fill_prompt,
                        'created_at' => $def->created_at?->format('d.m.Y'),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            $this->efDefinitions = [];
        }
    }

    public function efCreateDefinition(): void
    {
        $rules = [
            'efNewField.label' => ['required', 'string', 'max:255'],
            'efNewField.type' => ['required', 'string', 'in:' . implode(',', array_keys(CoreExtraFieldDefinition::TYPES))],
            'efNewField.is_required' => ['boolean'],
            'efNewField.is_mandatory' => ['boolean'],
            'efNewField.is_encrypted' => ['boolean'],
        ];

        if ($this->efNewField['type'] === 'select') {
            $rules['efNewField.options'] = ['required', 'array', 'min:1'];
        }
        if ($this->efNewField['type'] === 'lookup') {
            $rules['efNewField.lookup_id'] = ['required', 'integer', 'exists:core_lookups,id'];
        }
        if ($this->efNewField['type'] === 'regex') {
            $rules['efNewField.regex_pattern'] = ['required', 'string', 'max:500'];
        }

        $this->validate($rules);

        try {
            $teamId = $this->efGetTeamId();
            if (! $teamId) {
                $this->addError('efNewField.label', 'Kein Team-Kontext vorhanden.');
                return;
            }

            $user = Auth::user();
            $name = Str::slug($this->efNewField['label'], '_');

            $exists = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->efContextType, $this->efContextId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $this->addError('efNewField.label', 'Ein Feld mit diesem Namen existiert bereits.');
                return;
            }

            $maxOrder = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->efContextType, $this->efContextId)
                ->max('order') ?? 0;

            $options = null;
            if ($this->efNewField['type'] === 'select') {
                $options = [
                    'choices' => $this->efNewField['options'],
                    'multiple' => $this->efNewField['is_multiple'] ?? false,
                ];
            }
            if ($this->efNewField['type'] === 'lookup') {
                $options = [
                    'lookup_id' => (int) $this->efNewField['lookup_id'],
                    'multiple' => $this->efNewField['is_multiple'] ?? false,
                ];
            }
            if ($this->efNewField['type'] === 'file') {
                $options = [
                    'multiple' => $this->efNewField['is_multiple'] ?? false,
                ];
            }
            if ($this->efNewField['type'] === 'regex') {
                $pattern = trim($this->efNewField['regex_pattern'] ?? '');
                if (@preg_match('/' . $pattern . '/', '') === false) {
                    $this->addError('efNewField.regex_pattern', 'Ungültiges reguläres Ausdrucksmuster.');
                    return;
                }
                $options = [
                    'pattern' => $pattern,
                    'pattern_description' => trim($this->efNewField['regex_description'] ?? '') ?: null,
                    'pattern_error' => trim($this->efNewField['regex_error'] ?? '') ?: null,
                ];
            }

            $placeholder = trim($this->efNewField['placeholder'] ?? '');
            if ($placeholder !== '' && in_array($this->efNewField['type'], ['text', 'number', 'textarea', 'regex'])) {
                $options = $options ?? [];
                $options['placeholder'] = $placeholder;
            }

            CoreExtraFieldDefinition::create([
                'team_id' => $teamId,
                'created_by_user_id' => $user->id,
                'context_type' => $this->efContextType,
                'context_id' => $this->efContextId,
                'name' => $name,
                'label' => trim($this->efNewField['label']),
                'description' => ! empty($this->efNewField['description']) ? trim($this->efNewField['description']) : null,
                'type' => $this->efNewField['type'],
                'is_required' => $this->efNewField['is_required'] ?? false,
                'is_mandatory' => $this->efNewField['is_mandatory'] ?? false,
                'is_encrypted' => $this->efNewField['is_encrypted'] ?? false,
                'order' => $maxOrder + 1,
                'options' => $options,
                'verify_by_llm' => $this->efNewField['type'] === 'file' && ($this->efNewField['verify_by_llm'] ?? false),
                'verify_instructions' => $this->efNewField['type'] === 'file' ? ($this->efNewField['verify_instructions'] ?? null) : null,
                'auto_fill_source' => ! empty($this->efNewField['auto_fill_source']) ? $this->efNewField['auto_fill_source'] : null,
                'auto_fill_prompt' => ! empty($this->efNewField['auto_fill_prompt']) ? $this->efNewField['auto_fill_prompt'] : null,
            ]);

            $this->efResetForm();
            $this->efLoadDefinitions();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Feld erstellt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Erstellen des Feldes.']);
        }
    }

    public function efStartEditDefinition(int $definitionId): void
    {
        $definition = collect($this->efDefinitions)->firstWhere('id', $definitionId);
        if (! $definition) {
            return;
        }

        $this->efEditingDefinitionId = $definitionId;
        $this->efEditFieldTab = 'basis';

        $visibility = $definition['visibility_config'] ?? ExtraFieldConditionEvaluator::createEmptyConfig();

        $this->efEditField = [
            'label' => $definition['label'],
            'description' => $definition['description'] ?? '',
            'type' => $definition['type'],
            'is_required' => $definition['is_required'],
            'is_mandatory' => $definition['is_mandatory'],
            'is_encrypted' => $definition['is_encrypted'],
            'options' => $definition['options']['choices'] ?? [],
            'is_multiple' => $definition['options']['multiple'] ?? false,
            'verify_by_llm' => $definition['verify_by_llm'] ?? false,
            'verify_instructions' => $definition['verify_instructions'] ?? '',
            'auto_fill_source' => $definition['auto_fill_source'] ?? '',
            'auto_fill_prompt' => $definition['auto_fill_prompt'] ?? '',
            'lookup_id' => $definition['options']['lookup_id'] ?? null,
            'regex_pattern' => $definition['options']['pattern'] ?? '',
            'regex_description' => $definition['options']['pattern_description'] ?? '',
            'regex_error' => $definition['options']['pattern_error'] ?? '',
            'placeholder' => $definition['options']['placeholder'] ?? '',
            'visibility' => $visibility,
        ];
        $this->efEditOptionText = '';
    }

    public function efCancelEditDefinition(): void
    {
        $this->efEditingDefinitionId = null;
        $this->efEditFieldTab = 'basis';
        $this->efEditField = [
            'label' => '',
            'description' => '',
            'type' => 'text',
            'is_required' => false,
            'is_mandatory' => false,
            'is_encrypted' => false,
            'options' => [],
            'is_multiple' => false,
            'verify_by_llm' => false,
            'verify_instructions' => '',
            'auto_fill_source' => '',
            'auto_fill_prompt' => '',
            'lookup_id' => null,
            'regex_pattern' => '',
            'regex_description' => '',
            'regex_error' => '',
            'placeholder' => '',
            'visibility' => ExtraFieldConditionEvaluator::createEmptyConfig(),
        ];
        $this->efEditOptionText = '';
    }

    public function efSaveEditDefinition(): void
    {
        if (! $this->efEditingDefinitionId) {
            return;
        }

        $rules = [
            'efEditField.label' => ['required', 'string', 'max:255'],
            'efEditField.type' => ['required', 'string', 'in:' . implode(',', array_keys(CoreExtraFieldDefinition::TYPES))],
            'efEditField.is_required' => ['boolean'],
            'efEditField.is_mandatory' => ['boolean'],
            'efEditField.is_encrypted' => ['boolean'],
        ];

        if ($this->efEditField['type'] === 'select') {
            $rules['efEditField.options'] = ['required', 'array', 'min:1'];
        }
        if ($this->efEditField['type'] === 'lookup') {
            $rules['efEditField.lookup_id'] = ['required', 'integer', 'exists:core_lookups,id'];
        }
        if ($this->efEditField['type'] === 'regex') {
            $rules['efEditField.regex_pattern'] = ['required', 'string', 'max:500'];
        }

        $this->validate($rules);

        try {
            $definition = CoreExtraFieldDefinition::find($this->efEditingDefinitionId);
            if (! $definition) {
                return;
            }

            if ($this->efEditField['type'] === 'regex') {
                $pattern = trim($this->efEditField['regex_pattern'] ?? '');
                if (@preg_match('/' . $pattern . '/', '') === false) {
                    $this->addError('efEditField.regex_pattern', 'Ungültiges reguläres Ausdrucksmuster.');
                    return;
                }
            }

            $options = match ($this->efEditField['type']) {
                'select' => [
                    'choices' => $this->efEditField['options'],
                    'multiple' => $this->efEditField['is_multiple'] ?? false,
                ],
                'lookup' => [
                    'lookup_id' => (int) $this->efEditField['lookup_id'],
                    'multiple' => $this->efEditField['is_multiple'] ?? false,
                ],
                'file' => [
                    'multiple' => $this->efEditField['is_multiple'] ?? false,
                ],
                'regex' => [
                    'pattern' => trim($this->efEditField['regex_pattern'] ?? ''),
                    'pattern_description' => trim($this->efEditField['regex_description'] ?? '') ?: null,
                    'pattern_error' => trim($this->efEditField['regex_error'] ?? '') ?: null,
                ],
                default => [],
            };

            $placeholder = trim($this->efEditField['placeholder'] ?? '');
            if ($placeholder !== '' && in_array($this->efEditField['type'], ['text', 'number', 'textarea', 'regex'])) {
                $options['placeholder'] = $placeholder;
            }

            $visibility = $this->efEditField['visibility'] ?? ExtraFieldConditionEvaluator::createEmptyConfig();
            $visibilityConfig = ($visibility['enabled'] ?? false) ? $visibility : null;

            if ($visibilityConfig) {
                $detector = new ExtraFieldCircularDependencyDetector();
                $cycle = $detector->detectCycle(
                    $definition->name,
                    $visibilityConfig,
                    $this->efDefinitions
                );

                if ($cycle !== null) {
                    $fieldLabels = [];
                    foreach ($this->efDefinitions as $def) {
                        $fieldLabels[$def['name']] = $def['label'];
                    }
                    $fieldLabels[$definition->name] = trim($this->efEditField['label']);
                    $cycleDescription = $detector->describeCycle($cycle, $fieldLabels);

                    $this->addError('efEditField.visibility', "Zirkuläre Abhängigkeit erkannt: {$cycleDescription}");
                    return;
                }
            }

            $definition->update([
                'label' => trim($this->efEditField['label']),
                'description' => ! empty($this->efEditField['description']) ? trim($this->efEditField['description']) : null,
                'type' => $this->efEditField['type'],
                'is_required' => $this->efEditField['is_required'] ?? false,
                'is_mandatory' => $this->efEditField['is_mandatory'] ?? false,
                'is_encrypted' => $this->efEditField['is_encrypted'] ?? false,
                'options' => $options,
                'visibility_config' => $visibilityConfig,
                'verify_by_llm' => $this->efEditField['type'] === 'file' && ($this->efEditField['verify_by_llm'] ?? false),
                'verify_instructions' => $this->efEditField['type'] === 'file' ? ($this->efEditField['verify_instructions'] ?? null) : null,
                'auto_fill_source' => ! empty($this->efEditField['auto_fill_source']) ? $this->efEditField['auto_fill_source'] : null,
                'auto_fill_prompt' => ! empty($this->efEditField['auto_fill_prompt']) ? $this->efEditField['auto_fill_prompt'] : null,
            ]);

            $this->efCancelEditDefinition();
            $this->efLoadDefinitions();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Feld aktualisiert.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Aktualisieren.']);
        }
    }

    public function efDeleteDefinition(int $definitionId): void
    {
        try {
            $definition = CoreExtraFieldDefinition::find($definitionId);
            if (! $definition) {
                return;
            }

            CoreExtraFieldValue::where('definition_id', $definitionId)->delete();
            $definition->delete();

            if ($this->efEditingDefinitionId === $definitionId) {
                $this->efCancelEditDefinition();
            }

            $this->efLoadDefinitions();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Feld gelöscht.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen.']);
        }
    }

    // ── EF: Options Management ──────────────────────────────

    public function efAddNewOption(): void
    {
        $text = trim($this->efNewOptionText);
        if ($text !== '' && ! in_array($text, $this->efNewField['options'])) {
            $this->efNewField['options'][] = $text;
        }
        $this->efNewOptionText = '';
    }

    public function efRemoveNewOption(int $index): void
    {
        unset($this->efNewField['options'][$index]);
        $this->efNewField['options'] = array_values($this->efNewField['options']);
    }

    public function efAddEditOption(): void
    {
        $text = trim($this->efEditOptionText);
        if ($text !== '' && ! in_array($text, $this->efEditField['options'])) {
            $this->efEditField['options'][] = $text;
        }
        $this->efEditOptionText = '';
    }

    public function efRemoveEditOption(int $index): void
    {
        unset($this->efEditField['options'][$index]);
        $this->efEditField['options'] = array_values($this->efEditField['options']);
    }

    // ── EF: Lookups CRUD ────────────────────────────────────

    public function efLoadLookups(): void
    {
        $teamId = $this->efGetTeamId();
        if (! $teamId) {
            $this->efLookups = [];
            return;
        }

        try {
            if (! Schema::hasTable('core_lookups')) {
                $this->efLookups = [];
                return;
            }

            $this->efLookups = CoreLookup::forTeam($teamId)
                ->orderBy('label')
                ->get()
                ->map(fn ($lookup) => [
                    'id' => $lookup->id,
                    'name' => $lookup->name,
                    'label' => $lookup->label,
                    'description' => $lookup->description,
                    'is_system' => $lookup->is_system,
                    'values_count' => $lookup->values()->count(),
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->efLookups = [];
        }
    }

    public function efCreateLookup(): void
    {
        $this->validate([
            'efNewLookup.label' => ['required', 'string', 'max:255'],
        ]);

        $teamId = $this->efGetTeamId();
        if (! $teamId) {
            $this->addError('efNewLookup.label', 'Kein Team-Kontext vorhanden.');
            return;
        }

        $name = Str::slug($this->efNewLookup['label'], '_');

        $exists = CoreLookup::forTeam($teamId)->where('name', $name)->exists();
        if ($exists) {
            $this->addError('efNewLookup.label', 'Ein Lookup mit diesem Namen existiert bereits.');
            return;
        }

        try {
            CoreLookup::create([
                'team_id' => $teamId,
                'created_by_user_id' => Auth::id(),
                'name' => $name,
                'label' => trim($this->efNewLookup['label']),
                'description' => trim($this->efNewLookup['description']) ?: null,
                'is_system' => false,
            ]);

            $this->efNewLookup = ['name' => '', 'label' => '', 'description' => ''];
            $this->efLoadLookups();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lookup erstellt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Erstellen.']);
        }
    }

    public function efStartEditLookup(int $lookupId): void
    {
        $lookup = collect($this->efLookups)->firstWhere('id', $lookupId);
        if (! $lookup) {
            return;
        }

        $this->efEditingLookupId = $lookupId;
        $this->efEditLookup = [
            'label' => $lookup['label'],
            'description' => $lookup['description'] ?? '',
        ];
    }

    public function efCancelEditLookup(): void
    {
        $this->efEditingLookupId = null;
        $this->efEditLookup = ['label' => '', 'description' => ''];
    }

    public function efSaveEditLookup(): void
    {
        if (! $this->efEditingLookupId) {
            return;
        }

        $this->validate([
            'efEditLookup.label' => ['required', 'string', 'max:255'],
        ]);

        try {
            $lookup = CoreLookup::find($this->efEditingLookupId);
            if (! $lookup) {
                return;
            }

            $lookup->update([
                'label' => trim($this->efEditLookup['label']),
                'description' => trim($this->efEditLookup['description']) ?: null,
            ]);

            $this->efCancelEditLookup();
            $this->efLoadLookups();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lookup aktualisiert.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Aktualisieren.']);
        }
    }

    public function efDeleteLookup(int $lookupId): void
    {
        try {
            $lookup = CoreLookup::find($lookupId);
            if (! $lookup) {
                return;
            }

            if ($lookup->is_system) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'System-Lookups können nicht gelöscht werden.']);
                return;
            }

            $inUse = CoreExtraFieldDefinition::where('type', 'lookup')
                ->where('options->lookup_id', $lookupId)
                ->exists();

            if ($inUse) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Lookup wird noch verwendet und kann nicht gelöscht werden.']);
                return;
            }

            $lookup->delete();

            if ($this->efSelectedLookupId === $lookupId) {
                $this->efSelectedLookupId = null;
                $this->efLookupValues = [];
            }

            $this->efLoadLookups();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lookup gelöscht.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen.']);
        }
    }

    // ── EF: Lookup Values ───────────────────────────────────

    public function efSelectLookup(int $lookupId): void
    {
        $this->efSelectedLookupId = $lookupId;
        $this->efLoadLookupValues();
    }

    public function efDeselectLookup(): void
    {
        $this->efSelectedLookupId = null;
        $this->efLookupValues = [];
        $this->efNewLookupValueText = '';
        $this->efNewLookupValueLabel = '';
    }

    public function efLoadLookupValues(): void
    {
        if (! $this->efSelectedLookupId) {
            $this->efLookupValues = [];
            return;
        }

        try {
            $this->efLookupValues = CoreLookupValue::where('lookup_id', $this->efSelectedLookupId)
                ->orderBy('order')
                ->orderBy('label')
                ->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'value' => $v->value,
                    'label' => $v->label,
                    'order' => $v->order,
                    'is_active' => $v->is_active,
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->efLookupValues = [];
        }
    }

    public function efAddLookupValue(): void
    {
        if (! $this->efSelectedLookupId) {
            return;
        }

        $label = trim($this->efNewLookupValueLabel);
        $value = trim($this->efNewLookupValueText) ?: $label;

        if ($label === '') {
            return;
        }

        $exists = CoreLookupValue::where('lookup_id', $this->efSelectedLookupId)
            ->where('value', $value)
            ->exists();

        if ($exists) {
            $this->addError('efNewLookupValueText', 'Dieser Wert existiert bereits.');
            return;
        }

        try {
            $maxOrder = CoreLookupValue::where('lookup_id', $this->efSelectedLookupId)->max('order') ?? 0;

            CoreLookupValue::create([
                'lookup_id' => $this->efSelectedLookupId,
                'value' => $value,
                'label' => $label,
                'order' => $maxOrder + 1,
                'is_active' => true,
            ]);

            $this->efNewLookupValueText = '';
            $this->efNewLookupValueLabel = '';
            $this->efLoadLookupValues();
            $this->efLoadLookups();
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Hinzufügen.']);
        }
    }

    public function efToggleLookupValue(int $valueId): void
    {
        try {
            $value = CoreLookupValue::find($valueId);
            if ($value) {
                $value->update(['is_active' => ! $value->is_active]);
                $this->efLoadLookupValues();
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    public function efDeleteLookupValue(int $valueId): void
    {
        try {
            CoreLookupValue::where('id', $valueId)->delete();
            $this->efLoadLookupValues();
            $this->efLoadLookups();
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen.']);
        }
    }

    public function efMoveLookupValueUp(int $valueId): void
    {
        $this->efMoveLookupValue($valueId, -1);
    }

    public function efMoveLookupValueDown(int $valueId): void
    {
        $this->efMoveLookupValue($valueId, 1);
    }

    protected function efMoveLookupValue(int $valueId, int $direction): void
    {
        $values = collect($this->efLookupValues);
        $currentIndex = $values->search(fn ($v) => $v['id'] === $valueId);

        if ($currentIndex === false) {
            return;
        }

        $newIndex = $currentIndex + $direction;
        if ($newIndex < 0 || $newIndex >= $values->count()) {
            return;
        }

        $currentValue = CoreLookupValue::find($valueId);
        $swapValue = CoreLookupValue::find($values[$newIndex]['id']);

        if ($currentValue && $swapValue) {
            $tempOrder = $currentValue->order;
            $currentValue->update(['order' => $swapValue->order]);
            $swapValue->update(['order' => $tempOrder]);
            $this->efLoadLookupValues();
        }
    }

    // ── EF: Condition Builder ───────────────────────────────

    public function efGetOperatorsForField(string $fieldName): array
    {
        $field = collect($this->efDefinitions)->firstWhere('name', $fieldName);
        if (! $field) {
            return [];
        }

        return ExtraFieldConditionEvaluator::getOperatorsForType($field['type']);
    }

    public function efToggleVisibilityEnabled(): void
    {
        $this->efEditField['visibility']['enabled'] = ! ($this->efEditField['visibility']['enabled'] ?? false);

        if ($this->efEditField['visibility']['enabled'] && empty($this->efEditField['visibility']['groups'])) {
            $this->efAddConditionGroup();
        }
    }

    public function efSetVisibilityLogic(string $logic): void
    {
        $this->efEditField['visibility']['logic'] = $logic;
    }

    public function efAddConditionGroup(): void
    {
        $this->efEditField['visibility']['groups'][] = ExtraFieldConditionEvaluator::createEmptyGroup();
    }

    public function efRemoveConditionGroup(int $groupIndex): void
    {
        unset($this->efEditField['visibility']['groups'][$groupIndex]);
        $this->efEditField['visibility']['groups'] = array_values($this->efEditField['visibility']['groups']);

        if (empty($this->efEditField['visibility']['groups'])) {
            $this->efEditField['visibility']['enabled'] = false;
        }
    }

    public function efSetGroupLogic(int $groupIndex, string $logic): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['logic'] = $logic;
        }
    }

    public function efAddCondition(int $groupIndex): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][] = ExtraFieldConditionEvaluator::createEmptyCondition();
        }
    }

    public function efRemoveCondition(int $groupIndex, int $conditionIndex): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]);
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'] = array_values(
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions']
            );
        }
    }

    public function efUpdateConditionField(int $groupIndex, int $conditionIndex, string $fieldName): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['field'] = $fieldName;

            $operators = $this->efGetOperatorsForField($fieldName);
            if (! empty($operators)) {
                $firstOperator = array_key_first($operators);
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['operator'] = $firstOperator;
            }

            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = null;
        }
    }

    public function efUpdateConditionOperator(int $groupIndex, int $conditionIndex, string $operator): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['operator'] = $operator;

            $operatorMeta = ExtraFieldConditionEvaluator::OPERATORS[$operator] ?? null;
            if ($operatorMeta && ! ($operatorMeta['requiresValue'] ?? true)) {
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = null;
            }

            if (in_array($operator, ['is_in', 'is_not_in'])) {
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source'] = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source'] ?? 'manual';
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] ?? null;
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] ?? [];
            } else {
                unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source']);
                unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id']);
            }
        }
    }

    public function efUpdateConditionValue(int $groupIndex, int $conditionIndex, mixed $value): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = $value;
        }
    }

    public function efUpdateConditionListSource(int $groupIndex, int $conditionIndex, string $source): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source'] = $source;
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = [];
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] = null;
        }
    }

    public function efUpdateConditionListLookup(int $groupIndex, int $conditionIndex, mixed $lookupId): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] = $lookupId ? (int) $lookupId : null;
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = [];
        }
    }

    public function efAddConditionListValue(int $groupIndex, int $conditionIndex, string $value): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $value = trim($value);
            if ($value === '') {
                return;
            }
            $currentValues = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] ?? [];
            if (! is_array($currentValues)) {
                $currentValues = [];
            }
            if (! in_array($value, $currentValues)) {
                $currentValues[] = $value;
            }
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = $currentValues;
        }
    }

    public function efRemoveConditionListValue(int $groupIndex, int $conditionIndex, int $valueIndex): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'][$valueIndex])) {
            unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'][$valueIndex]);
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = array_values(
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value']
            );
        }
    }

    // ── EF: Computed Properties & Helpers ────────────────────

    public function efContextLabel(): ?string
    {
        if (! $this->efContextType || ! $this->efContextId) {
            return null;
        }

        if (! class_exists($this->efContextType)) {
            return null;
        }

        try {
            $context = $this->efContextType::find($this->efContextId);
            if (! $context) {
                return null;
            }

            if (method_exists($context, 'getDisplayName')) {
                return $context->getDisplayName();
            }
            if (method_exists($context, 'getContact')) {
                $contact = $context->getContact();
                if ($contact && isset($contact->full_name)) {
                    return $contact->full_name;
                }
            }
            if (isset($context->title)) {
                return $context->title;
            }
            if (isset($context->name)) {
                return $context->name;
            }

            return class_basename($this->efContextType) . ' #' . $this->efContextId;
        } catch (\Exception $e) {
            return class_basename($this->efContextType) . ' #' . $this->efContextId;
        }
    }

    public function efAvailableTypes(): array
    {
        return CoreExtraFieldDefinition::TYPES;
    }

    public function efAutoFillSources(): array
    {
        return CoreExtraFieldDefinition::AUTO_FILL_SOURCES;
    }

    public function efTypeDescriptions(): array
    {
        return CoreExtraFieldDefinition::TYPE_DESCRIPTIONS;
    }

    public function efAutoFillSourceDescriptions(): array
    {
        return CoreExtraFieldDefinition::AUTO_FILL_SOURCE_DESCRIPTIONS;
    }

    public function efConditionFields(): array
    {
        $fields = [];
        foreach ($this->efDefinitions as $def) {
            if ($def['id'] === $this->efEditingDefinitionId) {
                continue;
            }
            $fields[] = [
                'name' => $def['name'],
                'label' => $def['label'],
                'type' => $def['type'],
                'options' => $def['options'] ?? [],
            ];
        }
        return $fields;
    }

    public function efAllOperators(): array
    {
        return ExtraFieldConditionEvaluator::getAllOperators();
    }

    public function efAvailableLookupsForCondition(): array
    {
        $teamId = $this->efGetTeamId();
        if (! $teamId) {
            return [];
        }

        return CoreLookup::where('team_id', $teamId)
            ->orderBy('label')
            ->get()
            ->map(fn ($lookup) => [
                'id' => $lookup->id,
                'label' => $lookup->label,
                'name' => $lookup->name,
            ])
            ->all();
    }

    public function efVisibilityDescription(): string
    {
        if (! ($this->efEditField['visibility']['enabled'] ?? false)) {
            return 'Immer sichtbar';
        }

        $fieldLabels = [];
        foreach ($this->efDefinitions as $def) {
            $fieldLabels[$def['name']] = $def['label'];
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        return $evaluator->toHumanReadable($this->efEditField['visibility'], $fieldLabels);
    }

    public function efSelectedLookup(): ?array
    {
        if (! $this->efSelectedLookupId) {
            return null;
        }
        return collect($this->efLookups)->firstWhere('id', $this->efSelectedLookupId);
    }

    protected function efResetForm(): void
    {
        $this->efNewField = [
            'name' => '',
            'label' => '',
            'description' => '',
            'type' => 'text',
            'is_required' => false,
            'is_mandatory' => false,
            'is_encrypted' => false,
            'options' => [],
            'is_multiple' => false,
            'verify_by_llm' => false,
            'verify_instructions' => '',
            'auto_fill_source' => '',
            'auto_fill_prompt' => '',
            'lookup_id' => null,
            'regex_pattern' => '',
            'regex_description' => '',
            'regex_error' => '',
            'placeholder' => '',
            'visibility' => ExtraFieldConditionEvaluator::createEmptyConfig(),
        ];
        $this->efNewOptionText = '';
    }

    protected function efResetLookupForm(): void
    {
        $this->efNewLookup = ['name' => '', 'label' => '', 'description' => ''];
        $this->efEditLookup = ['label' => '', 'description' => ''];
        $this->efEditingLookupId = null;
        $this->efSelectedLookupId = null;
        $this->efLookupValues = [];
        $this->efNewLookupValueText = '';
        $this->efNewLookupValueLabel = '';
    }

    protected function efGetTeamId(): ?int
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return null;
            }

            $baseTeam = $user->currentTeamRelation;
            if (! $baseTeam) {
                return null;
            }

            return $baseTeam->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ── Agenda App ─────────────────────────────────────────────

    #[Computed]
    public function agendas(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        return TerminalAgenda::forTeam($teamId)
            ->whereHas('members', fn ($q) => $q->where('user_id', auth()->id()))
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon ?? '📋',
                'item_count' => $a->item_count,
                'role' => $a->members()->where('user_id', auth()->id())->value('role') ?? 'member',
            ])
            ->toArray();
    }


    public function selectAgenda(int $agendaId): void
    {
        $this->activeAgendaId = $agendaId;
        if ($this->agendaView === 'day') {
            $this->agendaView = 'board';
        }
        $this->broadcastAgendaState();
    }

    public function openMyDay(): void
    {
        $this->activeAgendaId = null;
        $this->agendaView = 'day';
        $this->agendaDayDate = now()->toDateString();
        $this->broadcastAgendaState();
    }

    public function navigateDay(string $direction): void
    {
        if ($direction === 'today') {
            $this->agendaDayDate = now()->toDateString();
        } else {
            $current = $this->agendaDayDate ?: now()->toDateString();
            $date = \Carbon\Carbon::parse($current);
            $this->agendaDayDate = $direction === 'next'
                ? $date->addDay()->toDateString()
                : $date->subDay()->toDateString();
        }

        $this->broadcastAgendaState();
    }

    public function createAgenda(string $name, ?string $description = null, ?string $icon = null): void
    {
        $teamId = $this->teamId();
        if (! $teamId || empty(trim($name))) {
            return;
        }

        $agenda = TerminalAgenda::create([
            'team_id' => $teamId,
            'name' => trim($name),
            'description' => $description ? trim($description) : null,
            'icon' => $icon,
        ]);

        TerminalAgendaMember::create([
            'agenda_id' => $agenda->id,
            'user_id' => auth()->id(),
            'role' => 'owner',
        ]);

        $this->activeAgendaId = $agenda->id;
        $this->agendaView = 'board';
        unset($this->agendas);
        $this->broadcastAgendaState();
    }

    protected function broadcastAgendaState(): void
    {
        $this->dispatch('terminal-agenda-state',
            agendaId: $this->activeAgendaId,
            view: $this->agendaView,
            dayDate: $this->agendaDayDate,
        );
    }

    public function updateAgenda(int $agendaId, string $name, ?string $description = null, ?string $icon = null): void
    {
        $agenda = TerminalAgenda::find($agendaId);
        if (! $agenda || empty(trim($name))) {
            return;
        }

        $agenda->update([
            'name' => trim($name),
            'description' => $description ? trim($description) : null,
            'icon' => $icon ?? $agenda->icon,
        ]);

        unset($this->agendas);
    }

    public function deleteAgenda(int $agendaId): void
    {
        $agenda = TerminalAgenda::find($agendaId);
        if (! $agenda) {
            return;
        }

        $isOwner = TerminalAgendaMember::where('agenda_id', $agenda->id)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner) {
            return;
        }

        $agenda->delete();

        if ($this->activeAgendaId === $agendaId) {
            $this->activeAgendaId = null;
            $this->broadcastAgendaState();
        }

        unset($this->agendas);
    }

    public function getAgendaMembers(): array
    {
        if (! $this->activeAgendaId) {
            return [];
        }

        return TerminalAgendaMember::where('agenda_id', $this->activeAgendaId)
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

    public function addAgendaMember(int $userId): void
    {
        if (! $this->activeAgendaId) {
            return;
        }

        TerminalAgendaMember::firstOrCreate(
            ['agenda_id' => $this->activeAgendaId, 'user_id' => $userId],
            ['role' => 'member']
        );
    }

    public function removeAgendaMember(int $userId): void
    {
        if (! $this->activeAgendaId) {
            return;
        }

        $isOwner = TerminalAgendaMember::where('agenda_id', $this->activeAgendaId)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner || $userId === auth()->id()) {
            return;
        }

        TerminalAgendaMember::where('agenda_id', $this->activeAgendaId)
            ->where('user_id', $userId)
            ->delete();
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
