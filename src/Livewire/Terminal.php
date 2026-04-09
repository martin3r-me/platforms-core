<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\ContextFileReference;
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
use Platform\Core\Models\Tag;
use Platform\Core\Services\ContextFileService;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Services\StoreTimeEntry;
use Platform\Organization\Services\StorePlannedTime;
use Platform\Organization\Traits\HasTimeEntries;
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
    public array $availableApps = ['chat' => true, 'activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false];
    public string $activityFilter = 'all'; // all | manual | system
    public string $filesFilter = 'all'; // all | images | documents

    // ── Tagging ──────────────────────────────────────────────
    public string $taggingTab = 'tags'; // 'tags', 'color', 'overview'
    public string $tagFilter = 'all';   // 'all', 'team', 'personal'
    public array $teamTags = [];
    public array $personalTags = [];
    public array $availableTags = [];
    public array $allTags = [];
    public array $allColors = [];
    public string $tagInput = '';
    public array $tagSuggestions = [];
    public bool $showTagSuggestions = false;
    public ?string $newTagColor = null;
    public bool $newTagIsPersonal = false;
    public ?string $contextColor = null;
    public ?string $newContextColor = null;

    // ── Time App ─────────────────────────────────────────────
    public string $timeWorkDate = '';
    public int $timeMinutes = 60;
    public ?string $timeRate = null;
    public ?string $timeNote = null;
    public ?int $timePlannedMinutes = null;
    public ?string $timePlannedNote = null;
    public string $timeOverviewRange = 'all';
    public ?int $timeSelectedUserId = null;

    // ── File Picker/Assign ───────────────────────────────────
    public bool $filePickerActive = false;
    public bool $filePickerMultiple = true;
    public ?string $filePickerCallback = null;
    public array $filePickerSelected = [];
    public ?string $filePickerReferenceType = null;
    public ?int $filePickerReferenceId = null;
    public ?int $filePickerAssignReferenceId = null;

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
            $this->availableApps = ['chat' => true, 'activity' => false, 'files' => false, 'tags' => false, 'time' => false, 'okr' => false, 'extrafields' => false];
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
    public function addActivityNote(string $text, ?string $_unused = null, array $attachmentIds = []): void
    {
        $plain = trim($text);
        if (empty($plain) && empty($attachmentIds)) {
            return;
        }

        // Use contextType/contextId directly (set by comms dispatch), not channelId
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        $model = $this->contextType::find($this->contextId);
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
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        $model = $this->contextType::find($this->contextId);
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
     * Upload files to the current context (Files app).
     */
    public function uploadContextFiles(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (empty($this->pendingFiles)) {
            return;
        }

        $service = app(ContextFileService::class);

        foreach ($this->pendingFiles as $file) {
            try {
                $service->uploadForContext(
                    $file,
                    $this->contextType,
                    $this->contextId,
                    [
                        'keep_original' => false,
                        'generate_variants' => true,
                    ]
                );
            } catch (\Exception $e) {
                // Continue with remaining files
            }
        }

        $this->pendingFiles = [];
        unset($this->contextFiles);
    }

    /**
     * Delete a context file (Files app).
     */
    public function deleteContextFile(int $fileId): void
    {
        $file = ContextFile::find($fileId);
        if (! $file) {
            unset($this->contextFiles);
            return;
        }

        // Verify the file belongs to the current context (prevent deleting arbitrary files)
        if ($this->contextType && $this->contextId) {
            if ($file->context_type !== $this->contextType || $file->context_id !== $this->contextId) {
                return;
            }
        }

        try {
            $service = app(ContextFileService::class);
            $service->delete($fileId);

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Datei gelöscht.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()]);
        }

        unset($this->contextFiles);
    }

    /**
     * Open terminal in file-picker mode (select files → return IDs).
     * Modules fire dispatch('terminal:files:pick', [...]) to trigger this.
     */
    #[On('terminal:files:pick')]
    public function openFilePicker(array $payload = []): void
    {
        $this->filePickerActive = true;
        $this->filePickerMultiple = $payload['multiple'] ?? true;
        $this->filePickerCallback = $payload['callback'] ?? null;
        $this->filePickerReferenceType = $payload['reference_type'] ?? null;
        $this->filePickerReferenceId = isset($payload['reference_id']) ? (int) $payload['reference_id'] : null;
        $this->filePickerAssignReferenceId = null;
        $this->filePickerSelected = [];
        $this->activeApp = 'files';
        $this->dispatch('toggle-terminal-open');
    }

    /**
     * Open terminal in file-assign mode (select file → update existing reference).
     */
    #[On('terminal:files:assign')]
    public function openFileAssign(array $payload = []): void
    {
        $this->filePickerActive = true;
        $this->filePickerMultiple = false;
        $this->filePickerAssignReferenceId = isset($payload['reference_id']) ? (int) $payload['reference_id'] : null;
        $this->filePickerReferenceType = null;
        $this->filePickerReferenceId = null;
        $this->filePickerCallback = null;
        $this->filePickerSelected = [];
        $this->activeApp = 'files';
        $this->dispatch('toggle-terminal-open');
    }

    /**
     * Toggle a file's selection in picker mode.
     */
    public function toggleFilePickerSelection(int $fileId): void
    {
        if (in_array($fileId, $this->filePickerSelected)) {
            $this->filePickerSelected = array_values(array_diff($this->filePickerSelected, [$fileId]));
        } else {
            if ($this->filePickerMultiple) {
                $this->filePickerSelected[] = $fileId;
            } else {
                $this->filePickerSelected = [$fileId];
            }
        }
    }

    /**
     * Confirm file picker selection.
     */
    public function confirmFilePicker(): void
    {
        if (empty($this->filePickerSelected)) {
            return;
        }

        // Assign mode: update existing reference
        if ($this->filePickerAssignReferenceId) {
            $reference = ContextFileReference::find($this->filePickerAssignReferenceId);
            if ($reference) {
                $file = ContextFile::find($this->filePickerSelected[0]);
                if ($file) {
                    $reference->update([
                        'context_file_id' => $file->id,
                        'context_file_variant_id' => null,
                        'meta' => array_merge($reference->meta ?? [], ['title' => $file->original_name]),
                    ]);
                    $this->dispatch('terminal:files:reference-updated', [
                        'reference_id' => $this->filePickerAssignReferenceId,
                    ]);
                }
            }
            $this->resetFilePicker();

            return;
        }

        // Picker mode with reference_type: create ContextFileReference(s)
        if ($this->filePickerReferenceType && $this->filePickerReferenceId) {
            foreach ($this->filePickerSelected as $fileId) {
                $file = ContextFile::find($fileId);
                if (! $file) {
                    continue;
                }

                $exists = ContextFileReference::where('context_file_id', $file->id)
                    ->where('context_file_variant_id', null)
                    ->where('reference_type', $this->filePickerReferenceType)
                    ->where('reference_id', $this->filePickerReferenceId)
                    ->exists();

                if (! $exists) {
                    ContextFileReference::create([
                        'context_file_id' => $file->id,
                        'context_file_variant_id' => null,
                        'reference_type' => $this->filePickerReferenceType,
                        'reference_id' => $this->filePickerReferenceId,
                        'meta' => ['title' => $file->original_name],
                    ]);
                }
            }

            $this->dispatch('terminal:files:reference-created', [
                'reference_type' => $this->filePickerReferenceType,
                'reference_id' => $this->filePickerReferenceId,
            ]);
            $this->resetFilePicker();

            return;
        }

        // Simple picker mode: return selected file data
        $files = ContextFile::whereIn('id', $this->filePickerSelected)
            ->with('variants')
            ->get();

        $this->dispatch('terminal:files:picked', [
            'files' => $files->map(fn ($f) => [
                'id' => $f->id,
                'token' => $f->token,
                'original_name' => $f->original_name,
                'url' => $f->url,
                'thumbnail' => $f->variants()->where('variant_type', 'thumbnail_4_3')->first()?->url
                    ?? $f->variants()->where('variant_type', 'like', 'thumbnail_%')->first()?->url
                    ?? $f->url,
            ])->toArray(),
            'callback' => $this->filePickerCallback,
        ]);
        $this->resetFilePicker();
    }

    /**
     * Cancel file picker and reset state.
     */
    public function cancelFilePicker(): void
    {
        $this->resetFilePicker();
    }

    protected function resetFilePicker(): void
    {
        $this->filePickerActive = false;
        $this->filePickerMultiple = true;
        $this->filePickerCallback = null;
        $this->filePickerSelected = [];
        $this->filePickerReferenceType = null;
        $this->filePickerReferenceId = null;
        $this->filePickerAssignReferenceId = null;
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

    /**
     * Open tags app from sidebar button (uses context from active channel).
     */
    public function openTagsApp(): void
    {
        $this->availableApps['tags'] = true;
        $this->activeApp = 'tags';
        $this->taggingTab = ($this->contextType && $this->contextId) ? 'tags' : 'overview';
        $this->tagFilter = 'all';
        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;
        $this->newContextColor = null;

        if ($this->contextType && $this->contextId) {
            $this->loadTags();
            $this->loadColor();
        }
        $this->loadAllTags();
        $this->loadAllColors();
    }

    /**
     * Open time app from sidebar button.
     */
    public function openTimeApp(): void
    {
        $this->availableApps['time'] = true;
        $this->activeApp = 'time';
        $this->timeWorkDate = now()->format('Y-m-d');
        $this->timeMinutes = 60;
        $this->timeRate = null;
        $this->timeNote = null;
        $this->timePlannedMinutes = null;
        $this->timePlannedNote = null;
        $this->timeOverviewRange = 'all';
        $this->timeSelectedUserId = null;
    }

    // ── Tagging Methods ──────────────────────────────────────

    public function loadTags(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        try {
            if (! Schema::hasTable('tags') || ! Schema::hasTable('taggables')) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context || ! method_exists($context, 'tags')) {
                return;
            }

            $user = auth()->user();
            if (! $user) {
                return;
            }

            $this->teamTags = $context->teamTags()
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                ])
                ->toArray();

            $this->personalTags = $context->personalTags()
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                ])
                ->toArray();

            $assignedTagIds = collect($this->teamTags)->pluck('id')
                ->merge(collect($this->personalTags)->pluck('id'))
                ->unique()
                ->toArray();

            $this->availableTags = Tag::query()
                ->availableForUser($user)
                ->whereNotIn('id', $assignedTagIds)
                ->orderBy('label')
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->teamTags = [];
            $this->personalTags = [];
            $this->availableTags = [];
        }
    }

    public function loadColor(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->contextColor = null;
            return;
        }

        if (! class_exists($this->contextType)) {
            $this->contextColor = null;
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                $this->contextColor = null;
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->contextColor = null;
                return;
            }

            $this->contextColor = $context->color;
        } catch (\Exception $e) {
            $this->contextColor = null;
        }
    }

    public function toggleTag(int $tagId, bool $personal = false): void
    {
        if (! $this->contextType || ! $this->contextId || ! class_exists($this->contextType)) {
            return;
        }

        $context = $this->contextType::find($this->contextId);
        if (! $context || ! method_exists($context, 'tags')) {
            return;
        }

        $tag = Tag::find($tagId);
        if (! $tag) {
            return;
        }

        $hasTag = $context->hasTag($tag, $personal);

        if ($hasTag) {
            $context->untag($tag, $personal);
        } else {
            $context->tag($tag, $personal);
        }

        $this->loadTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $hasTag ? 'Tag entfernt.' : 'Tag zugeordnet.',
        ]);
    }

    public function setColor(): void
    {
        if (! $this->contextType || ! $this->contextId || ! $this->newContextColor) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $this->newContextColor)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ungültige Farbangabe.']);
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieses Model unterstützt keine Farben.']);
                return;
            }

            $context->setColor($this->newContextColor, false);
            $this->contextColor = $this->newContextColor;
            $this->newContextColor = null;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Farbe gesetzt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Setzen der Farbe.']);
        }
    }

    public function setColorPreset(string $color): void
    {
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ungültige Farbangabe.']);
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieses Model unterstützt keine Farben.']);
                return;
            }

            $context->setColor($color, false);
            $this->contextColor = $color;
            $this->newContextColor = null;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Farbe gesetzt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Setzen der Farbe.']);
        }
    }

    public function removeColor(): void
    {
        if (! $this->contextType || ! $this->contextId || ! class_exists($this->contextType)) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieses Model unterstützt keine Farben.']);
                return;
            }

            $context->removeColor(false);
            $this->contextColor = null;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Farbe entfernt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Entfernen der Farbe.']);
        }
    }

    public function updatedTagInput(): void
    {
        $this->searchTagSuggestions();
    }

    public function searchTagSuggestions(): void
    {
        if (empty($this->tagInput)) {
            $this->tagSuggestions = [];
            $this->showTagSuggestions = false;
            return;
        }

        try {
            if (! Schema::hasTable('tags')) {
                $this->tagSuggestions = [];
                return;
            }

            $user = auth()->user();
            if (! $user) {
                $this->tagSuggestions = [];
                return;
            }

            $assignedTagIds = collect($this->teamTags)->pluck('id')
                ->merge(collect($this->personalTags)->pluck('id'))
                ->unique()
                ->toArray();

            $tags = Tag::query()
                ->availableForUser($user)
                ->whereNotIn('id', $assignedTagIds)
                ->where(function ($q) {
                    $q->where('label', 'like', '%' . $this->tagInput . '%')
                      ->orWhere('name', 'like', '%' . $this->tagInput . '%');
                })
                ->orderBy('label')
                ->limit(10)
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'label' => $tag->label,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                ])
                ->toArray();

            $this->tagSuggestions = $tags;
            $this->showTagSuggestions = count($tags) > 0 || strlen($this->tagInput) >= 2;
        } catch (\Exception $e) {
            $this->tagSuggestions = [];
        }
    }

    public function addTagFromSuggestion(int $tagId, bool $personal = false): void
    {
        $this->toggleTag($tagId, $personal);
        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;
    }

    public function createAndAddTag(): void
    {
        if (empty(trim($this->tagInput))) {
            return;
        }

        $user = auth()->user();
        $baseTeam = $user->currentTeamRelation;

        if (! $baseTeam) {
            return;
        }

        $rootTeam = $baseTeam->getRootTeam();

        $existingTag = Tag::query()
            ->where('label', trim($this->tagInput))
            ->where(function ($q) use ($rootTeam) {
                if ($this->newTagIsPersonal) {
                    $q->whereNull('team_id');
                } else {
                    $q->where('team_id', $rootTeam->id)->orWhereNull('team_id');
                }
            })
            ->first();

        if ($existingTag) {
            $this->toggleTag($existingTag->id, $this->newTagIsPersonal);
            $this->tagInput = '';
            $this->tagSuggestions = [];
            $this->showTagSuggestions = false;
            return;
        }

        $tagData = [
            'label' => trim($this->tagInput),
            'name' => Str::slug(trim($this->tagInput)),
            'color' => $this->newTagColor,
            'created_by_user_id' => $user->id,
        ];

        if (! $this->newTagIsPersonal) {
            $tagData['team_id'] = $rootTeam->id;
        }

        $tag = Tag::create($tagData);

        if ($this->contextType && $this->contextId && class_exists($this->contextType)) {
            $context = $this->contextType::find($this->contextId);
            if ($context && method_exists($context, 'tag')) {
                $context->tag($tag, $this->newTagIsPersonal);
            }
        }

        $this->loadTags();
        $this->loadAllTags();

        $this->tagInput = '';
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Tag erstellt und zugeordnet.']);
    }

    public function deleteTag(int $tagId): void
    {
        $tag = Tag::find($tagId);
        if (! $tag) {
            return;
        }

        $user = auth()->user();

        if ($tag->created_by_user_id !== $user->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Sie haben keine Berechtigung, dieses Tag zu löschen.']);
            return;
        }

        $usageCount = DB::table('taggables')->where('tag_id', $tagId)->count();

        if ($usageCount > 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Tag wird noch verwendet und kann nicht gelöscht werden.']);
            return;
        }

        $tag->delete();

        $this->loadTags();
        $this->loadAllTags();

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Tag gelöscht.']);
    }

    public function loadAllTags(): void
    {
        try {
            if (! Schema::hasTable('tags') || ! Schema::hasTable('taggables')) {
                $this->allTags = [];
                return;
            }

            $user = auth()->user();
            if (! $user) {
                $this->allTags = [];
                return;
            }

            $tags = Tag::query()
                ->availableForUser($user)
                ->with('createdBy')
                ->orderBy('label')
                ->get();

            $this->allTags = $tags->map(function ($tag) {
                $teamCount = DB::table('taggables')->where('tag_id', $tag->id)->whereNull('user_id')->count();
                $personalCount = DB::table('taggables')->where('tag_id', $tag->id)->whereNotNull('user_id')->count();

                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                    'is_global' => $tag->isGlobal(),
                    'total_count' => $teamCount + $personalCount,
                    'team_count' => $teamCount,
                    'personal_count' => $personalCount,
                    'created_at' => $tag->created_at?->format('d.m.Y'),
                    'created_by' => $tag->createdBy?->name ?? 'Unbekannt',
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->allTags = [];
        }
    }

    public function loadAllColors(): void
    {
        try {
            if (! Schema::hasTable('colorables')) {
                $this->allColors = [];
                return;
            }

            $user = auth()->user();
            if (! $user) {
                $this->allColors = [];
                return;
            }

            $colors = DB::table('colorables')
                ->select('color', DB::raw('COUNT(*) as total_count'))
                ->groupBy('color')
                ->orderBy('total_count', 'desc')
                ->get();

            $this->allColors = $colors->map(function ($color) {
                $teamCount = DB::table('colorables')->where('color', $color->color)->whereNull('user_id')->count();
                $personalCount = DB::table('colorables')->where('color', $color->color)->whereNotNull('user_id')->count();

                return [
                    'color' => $color->color,
                    'total_count' => $color->total_count,
                    'team_count' => $teamCount,
                    'personal_count' => $personalCount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->allColors = [];
        }
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

    // ── Time App Methods ────────────────────────────────────────

    #[Computed]
    public function timeEntries(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        if (! class_exists($this->contextType) || ! in_array(HasTimeEntries::class, class_uses_recursive($this->contextType))) {
            return [];
        }

        $contextPairs = [$this->contextType => [$this->contextId]];
        $this->collectTimeChildContextPairs($contextPairs);

        $baseQuery = OrganizationTimeEntry::query()
            ->where(function ($q) use ($contextPairs) {
                foreach ($contextPairs as $type => $ids) {
                    $q->orWhere(function ($sq) use ($type, $ids) {
                        $sq->where('context_type', $type)
                           ->whereIn('context_id', array_unique($ids));
                    });
                }
            });

        if ($this->timeSelectedUserId) {
            $baseQuery->where('user_id', $this->timeSelectedUserId);
        }

        $baseQuery = $this->applyTimeOverviewRangeFilter($baseQuery);

        return $baseQuery
            ->with('user')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'work_date' => $e->work_date->format('d.m.Y'),
                'minutes' => $e->minutes,
                'rate_cents' => $e->rate_cents,
                'amount_cents' => $e->amount_cents,
                'is_billed' => $e->is_billed,
                'note' => $e->note,
                'user_name' => $e->user?->name ?? 'Unbekannt',
                'user_initials' => $this->initials($e->user?->name),
                'user_avatar' => $e->user?->avatar,
            ])
            ->toArray();
    }

    #[Computed]
    public function timePlannedEntries(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        return OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('is_active', true)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'planned_minutes' => $e->planned_minutes,
                'note' => $e->note,
                'user_name' => $e->user?->name ?? 'Unbekannt',
                'created_at' => $e->created_at->format('d.m.Y'),
            ])
            ->toArray();
    }

    #[Computed]
    public function timeStats(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return ['totalMinutes' => 0, 'billedMinutes' => 0, 'unbilledMinutes' => 0, 'unbilledAmountCents' => 0, 'totalPlannedMinutes' => null];
        }

        if (! class_exists($this->contextType) || ! in_array(HasTimeEntries::class, class_uses_recursive($this->contextType))) {
            return ['totalMinutes' => 0, 'billedMinutes' => 0, 'unbilledMinutes' => 0, 'unbilledAmountCents' => 0, 'totalPlannedMinutes' => null];
        }

        $contextPairs = [$this->contextType => [$this->contextId]];
        $this->collectTimeChildContextPairs($contextPairs);

        $baseQuery = OrganizationTimeEntry::query()
            ->where(function ($q) use ($contextPairs) {
                foreach ($contextPairs as $type => $ids) {
                    $q->orWhere(function ($sq) use ($type, $ids) {
                        $sq->where('context_type', $type)
                           ->whereIn('context_id', array_unique($ids));
                    });
                }
            });

        if ($this->timeSelectedUserId) {
            $baseQuery->where('user_id', $this->timeSelectedUserId);
        }

        $baseQuery = $this->applyTimeOverviewRangeFilter($baseQuery);

        $totalMinutes = (int) (clone $baseQuery)->sum('minutes');
        $billedMinutes = (int) (clone $baseQuery)->where('is_billed', true)->sum('minutes');
        $unbilledAmountCents = (int) (clone $baseQuery)->where('is_billed', false)->sum('amount_cents');

        $totalPlannedMinutes = (int) OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->active()
            ->sum('planned_minutes');

        return [
            'totalMinutes' => $totalMinutes,
            'billedMinutes' => $billedMinutes,
            'unbilledMinutes' => max(0, $totalMinutes - $billedMinutes),
            'unbilledAmountCents' => $unbilledAmountCents,
            'totalPlannedMinutes' => $totalPlannedMinutes > 0 ? $totalPlannedMinutes : null,
        ];
    }

    #[Computed]
    public function timeAvailableUsers(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        $userIds = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->toArray();

        if (empty($userIds)) {
            return [];
        }

        $user = Auth::user();
        $team = $user?->currentTeamRelation;
        if (! $team) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'initials' => $this->initials($u->name)])
            ->toArray();
    }

    public function saveTimeEntry(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $user || ! $team) {
            return;
        }

        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        $this->validate([
            'timeWorkDate' => ['required', 'date'],
            'timeMinutes' => ['required', 'integer', 'min:1'],
            'timeRate' => ['nullable', 'string'],
            'timeNote' => ['nullable', 'string', 'max:500'],
        ]);

        $rateCents = $this->timeRateToCents($this->timeRate);
        if ($this->timeRate && $rateCents === null) {
            $this->addError('timeRate', 'Bitte einen gültigen Betrag eingeben.');
            return;
        }

        $minutes = max(1, (int) $this->timeMinutes);
        $amountCents = $rateCents !== null ? (int) round($rateCents * ($minutes / 60)) : null;

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);
        if (! $context) {
            return;
        }

        $storeTimeEntry = app(StoreTimeEntry::class);

        $storeTimeEntry->store([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'work_date' => $this->timeWorkDate,
            'minutes' => $minutes,
            'rate_cents' => $rateCents,
            'amount_cents' => $amountCents,
            'is_billed' => false,
            'metadata' => null,
            'note' => $this->timeNote,
        ]);

        $this->timeWorkDate = now()->format('Y-m-d');
        $this->timeMinutes = 60;
        $this->timeRate = null;
        $this->timeNote = null;
        $this->resetValidation();

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Zeit erfasst.']);
    }

    public function toggleTimeBilled(int $id): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team) {
            return;
        }

        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($id);

        if ($entry->team_id !== $team->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Keine Berechtigung.']);
            return;
        }

        $entry->is_billed = ! $entry->is_billed;
        $entry->save();

        unset($this->timeEntries, $this->timeStats);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $entry->is_billed ? 'Als abgerechnet markiert.' : 'Wieder auf offen gesetzt.',
        ]);
    }

    public function deleteTimeEntry(int $id): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team) {
            return;
        }

        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($id);

        if ($entry->team_id !== $team->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Keine Berechtigung.']);
            return;
        }

        $entry->delete();

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Zeiteintrag gelöscht.']);
    }

    public function saveTimePlanned(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $user || ! $team) {
            return;
        }

        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        $this->validate([
            'timePlannedMinutes' => ['required', 'integer', 'min:1'],
            'timePlannedNote' => ['nullable', 'string', 'max:500'],
        ]);

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);
        if (! $context) {
            return;
        }

        $storePlannedTime = app(StorePlannedTime::class);

        $storePlannedTime->store([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'planned_minutes' => (int) $this->timePlannedMinutes,
            'note' => $this->timePlannedNote,
            'is_active' => true,
        ]);

        $this->timePlannedMinutes = null;
        $this->timePlannedNote = null;
        $this->resetValidation();

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Budget hinzugefügt.']);
    }

    public function deleteTimePlanned(int $id): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team) {
            return;
        }

        $entry = OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('id', $id)
            ->where('is_active', true)
            ->firstOrFail();

        if ($entry->team_id !== $team->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Keine Berechtigung.']);
            return;
        }

        $entry->update(['is_active' => false]);

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Budget deaktiviert.']);
    }

    public function updatedTimeOverviewRange(): void
    {
        unset($this->timeEntries, $this->timeStats);
    }

    public function updatedTimeSelectedUserId(): void
    {
        unset($this->timeEntries, $this->timeStats);
    }

    protected function applyTimeOverviewRangeFilter($query)
    {
        if ($this->timeOverviewRange === 'all') {
            return $query;
        }

        $now = now();

        return match ($this->timeOverviewRange) {
            'current_week' => $query->whereBetween('work_date', [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()]),
            'current_month' => $query->whereBetween('work_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()]),
            'current_year' => $query->whereBetween('work_date', [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()]),
            default => $query,
        };
    }

    protected function collectTimeChildContextPairs(array &$pairs): void
    {
        $orgContext = OrganizationContext::query()
            ->where('contextable_type', $this->contextType)
            ->where('contextable_id', $this->contextId)
            ->where('is_active', true)
            ->first();

        if (! $orgContext || empty($orgContext->include_children_relations)) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model) {
            return;
        }

        foreach ($orgContext->include_children_relations as $relationPath) {
            $this->resolveTimeRelationPathForPairs($model, $relationPath, $pairs);
        }
    }

    protected function resolveTimeRelationPathForPairs($model, string $path, array &$pairs): void
    {
        $segments = explode('.', $path);
        $currentModels = collect([$model]);

        foreach ($segments as $segment) {
            $nextModels = collect();
            foreach ($currentModels as $currentModel) {
                if (! method_exists($currentModel, $segment)) {
                    continue;
                }
                $related = $currentModel->{$segment};
                if ($related instanceof \Illuminate\Database\Eloquent\Collection) {
                    $nextModels = $nextModels->merge($related);
                } elseif ($related instanceof \Illuminate\Database\Eloquent\Model) {
                    $nextModels->push($related);
                }
            }
            $currentModels = $nextModels;
        }

        foreach ($currentModels as $leafModel) {
            $type = get_class($leafModel);
            $pairs[$type][] = $leafModel->id;
        }
    }

    protected function timeRateToCents(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace([' ', "'"], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;
        if ($float <= 0) {
            return null;
        }

        return (int) round($float * 100);
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
