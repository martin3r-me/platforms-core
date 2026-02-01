<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ModalSimpleToolPlayground extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public function boot(): void
    {
        \Log::info('[Playground] Component BOOT', ['component_id' => $this->getId()]);
    }

    /** @var array File uploads for the current message */
    public $pendingFiles = [];

    /** @var array Uploaded files ready to be attached */
    public array $uploadedAttachments = [];

    /** @var array<string,mixed>|null */
    public ?array $context = null;

    /** @var bool Ob Context mitgesendet werden soll */
    public bool $sendContext = true;

    /** @var array<int, array<string,mixed>> */
    public array $pricingEdits = [];

    /** @var array<int, array<string,mixed>> */
    public array $modelEdits = [];

    public ?string $pricingSaveMessage = null;

    public ?int $activeThreadId = null;
    public ?int $chatId = null;

    #[On('playground')]
    public function setPlaygroundContext(array $payload = []): void
    {
        try {
            \Log::info('[Playground] setPlaygroundContext CALLED', ['payload' => $payload, 'component_id' => $this->getId()]);
            $this->context = $payload;
            $this->sendContext = true; // Bei neuem Context: Checkbox aktivieren
            \Log::info('[Playground] setPlaygroundContext DONE', ['context' => $this->context]);
        } catch (\Throwable $e) {
            \Log::error('[Playground] setPlaygroundContext ERROR', ['error' => $e->getMessage()]);
        }
    }

    #[Computed]
    public function hasContext(): bool
    {
        return !empty($this->context['title']);
    }

    #[Computed]
    public function contextType(): string
    {
        return $this->context['type'] ?? 'Kontext';
    }

    #[Computed]
    public function contextTitle(): string
    {
        return $this->context['title'] ?? '';
    }

    /** Context für API-Request (nur wenn Checkbox aktiv) */
    public function getContextForRequest(): ?array
    {
        if (!$this->sendContext || empty($this->context)) {
            return null;
        }
        // Nach dem Senden: Checkbox deaktivieren
        $this->sendContext = false;
        return $this->context;
    }

    #[On('playground:open')]
    public function openModal(array $payload = []): void
    {
        $ctx = $payload['context'] ?? $payload['terminal_context'] ?? null;
        $this->context = is_array($ctx) ? $ctx : null;
        $this->open = true;

        // Pre-fill pricing edits for the "Model settings" tab.
        $this->pricingEdits = [];
        $this->modelEdits = [];
        $models = CoreAiModel::query()->orderBy('provider_id')->orderBy('model_id')->get();
        foreach ($models as $m) {
            $this->pricingEdits[(int)$m->id] = [
                'pricing_currency' => (string)($m->pricing_currency ?? 'USD'),
                'price_input_per_1m' => $m->price_input_per_1m,
                'price_cached_input_per_1m' => $m->price_cached_input_per_1m,
                'price_output_per_1m' => $m->price_output_per_1m,
            ];
            // Model settings edits (incl. param support flags)
            $this->modelEdits[(int)$m->id] = [
                'pricing_currency' => (string)($m->pricing_currency ?? 'USD'),
                'price_input_per_1m' => $m->price_input_per_1m,
                'price_cached_input_per_1m' => $m->price_cached_input_per_1m,
                'price_output_per_1m' => $m->price_output_per_1m,
                'context_window' => $m->context_window,
                'max_output_tokens' => $m->max_output_tokens,
                // tri-state select values: '' (unknown), '1' (true), '0' (false)
                'supports_temperature' => $m->supports_temperature === null ? '' : ($m->supports_temperature ? '1' : '0'),
                'supports_top_p' => $m->supports_top_p === null ? '' : ($m->supports_top_p ? '1' : '0'),
                'supports_presence_penalty' => $m->supports_presence_penalty === null ? '' : ($m->supports_presence_penalty ? '1' : '0'),
                'supports_frequency_penalty' => $m->supports_frequency_penalty === null ? '' : ($m->supports_frequency_penalty ? '1' : '0'),
            ];
        }
        $this->pricingSaveMessage = null;

        // Load or create chat for this user
        $this->loadOrCreateChat();

        // Ensure the client-side playground initializes AFTER the modal is open and context is rendered.
        // (DOMContentLoaded might have happened long before; modal is opened later.)
        $this->dispatch('simple-playground-modal-opened');
    }

    private function loadOrCreateChat(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $chat = CoreChat::firstOrCreate(
            [
                'user_id' => $user->id,
                'title' => 'Simple Playground',
            ],
            [
                'status' => 'active',
                'total_tokens_in' => 0,
                'total_tokens_out' => 0,
            ]
        );

        $this->chatId = $chat->id;

        // Load most recent open thread or create one
        $thread = $chat->threads()
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$thread) {
            $thread = $chat->threads()->create([
                'title' => 'Thread ' . ($chat->threads()->count() + 1),
                'status' => 'open',
                'started_at' => now(),
            ]);
        }

        $this->activeThreadId = $thread->id;
    }

    public function createThread(): void
    {
        if (!$this->chatId) {
            $this->loadOrCreateChat();
        }

        $chat = $this->chat;
        if (!$chat) {
            return;
        }

        $thread = $chat->threads()->create([
            'title' => 'Thread ' . ($chat->threads()->count() + 1),
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->activeThreadId = $thread->id;
    }

    public function switchThread(int $threadId): void
    {
        $chat = $this->chat;
        if (!$chat) {
            return;
        }

        $thread = $chat->threads()->find($threadId);
        if (!$thread) {
            return;
        }

        $this->activeThreadId = $threadId;
    }

    /**
     * Get the chat model on demand to avoid Livewire synth issues with Eloquent models.
     */
    #[Computed]
    public function chat(): ?CoreChat
    {
        if (!$this->chatId) {
            return null;
        }
        return CoreChat::find($this->chatId);
    }

    #[Computed]
    public function threads()
    {
        if (!$this->chat) {
            return collect();
        }
        return $this->chat->threads()->orderBy('created_at', 'desc')->get();
    }

    #[Computed]
    public function activeThread(): ?CoreChatThread
    {
        if (!$this->chat || !$this->activeThreadId) {
            return null;
        }
        // Ensure we never leak threads across users/chats
        return $this->chat->threads()->with('messages')->find($this->activeThreadId);
    }

    #[Computed]
    public function activeThreadMessages()
    {
        $t = $this->activeThread;
        if (!$t) {
            return collect();
        }
        return $t->messages()->orderBy('created_at')->get();
    }

    public function updateThreadModel(int $threadId, string $modelId): void
    {
        $chat = $this->chat;
        if (!$chat) {
            return;
        }

        $thread = $chat->threads()->find($threadId);
        if (!$thread) {
            return;
        }

        $thread->update(['model_id' => $modelId]);
    }

    public function updateThreadTitle(int $threadId, string $title): void
    {
        $chat = $this->chat;
        if (!$chat) {
            return;
        }

        $thread = $chat->threads()->find($threadId);
        if (!$thread) {
            return;
        }

        $title = trim($title);
        if (empty($title)) {
            $title = 'Thread ' . $threadId;
        }

        $thread->update(['title' => $title]);
    }

    public function deleteActiveThread(): void
    {
        $chat = $this->chat;
        if (!$chat || !$this->activeThreadId) {
            return;
        }

        $threadId = (int) $this->activeThreadId;

        // Ensure we never delete across chats/users
        $thread = $chat->threads()->find($threadId);
        if (!$thread) {
            return;
        }

        DB::transaction(function () use ($threadId, $thread) {
            // Delete messages explicitly (safer than relying on FK cascade)
            CoreChatMessage::query()->where('thread_id', $threadId)->delete();
            $thread->delete();
        });

        // Pick newest open thread or create a new one
        $next = $chat->threads()
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$next) {
            $next = $chat->threads()->create([
                'title' => 'Thread ' . ($chat->threads()->count() + 1),
                'status' => 'open',
                'started_at' => now(),
            ]);
        }

        $this->activeThreadId = $next->id;
    }

    public function saveModelPricing(int $coreAiModelId): void
    {
        // Backwards-compatible: keep old method, but only update pricing fields.
        $this->pricingSaveMessage = null;

        $this->validate([
            "pricingEdits.{$coreAiModelId}.pricing_currency" => ['required', 'string', 'size:3'],
            "pricingEdits.{$coreAiModelId}.price_input_per_1m" => ['nullable', 'numeric', 'min:0'],
            "pricingEdits.{$coreAiModelId}.price_cached_input_per_1m" => ['nullable', 'numeric', 'min:0'],
            "pricingEdits.{$coreAiModelId}.price_output_per_1m" => ['nullable', 'numeric', 'min:0'],
        ]);

        $m = CoreAiModel::findOrFail($coreAiModelId);
        $row = $this->pricingEdits[$coreAiModelId] ?? [];

        $m->update([
            'pricing_currency' => strtoupper((string)($row['pricing_currency'] ?? 'USD')),
            'price_input_per_1m' => $row['price_input_per_1m'] !== '' ? $row['price_input_per_1m'] : null,
            'price_cached_input_per_1m' => $row['price_cached_input_per_1m'] !== '' ? $row['price_cached_input_per_1m'] : null,
            'price_output_per_1m' => $row['price_output_per_1m'] !== '' ? $row['price_output_per_1m'] : null,
        ]);

        $this->pricingSaveMessage = "✅ Preise gespeichert: {$m->model_id}";
    }

    public function saveModelSettings(int $coreAiModelId): void
    {
        $this->pricingSaveMessage = null;

        if (!$this->canManageAiModels()) {
            $this->pricingSaveMessage = '⛔️ Keine Berechtigung: nur Owner des Root/Eltern-Teams kann Model-Settings ändern.';
            return;
        }

        $this->validate([
            "modelEdits.{$coreAiModelId}.pricing_currency" => ['required', 'string', 'size:3'],
            "modelEdits.{$coreAiModelId}.price_input_per_1m" => ['nullable', 'numeric', 'min:0'],
            "modelEdits.{$coreAiModelId}.price_cached_input_per_1m" => ['nullable', 'numeric', 'min:0'],
            "modelEdits.{$coreAiModelId}.price_output_per_1m" => ['nullable', 'numeric', 'min:0'],
            "modelEdits.{$coreAiModelId}.context_window" => ['nullable', 'integer', 'min:1', 'max:2000000'],
            "modelEdits.{$coreAiModelId}.max_output_tokens" => ['nullable', 'integer', 'min:1', 'max:200000'],
            "modelEdits.{$coreAiModelId}.supports_temperature" => ['nullable', 'in:,0,1'],
            "modelEdits.{$coreAiModelId}.supports_top_p" => ['nullable', 'in:,0,1'],
            "modelEdits.{$coreAiModelId}.supports_presence_penalty" => ['nullable', 'in:,0,1'],
            "modelEdits.{$coreAiModelId}.supports_frequency_penalty" => ['nullable', 'in:,0,1'],
        ]);

        $m = CoreAiModel::findOrFail($coreAiModelId);
        $row = $this->modelEdits[$coreAiModelId] ?? [];

        $toBoolOrNull = function ($v): ?bool {
            if ($v === '' || $v === null) return null;
            if ($v === true || $v === 1 || $v === '1') return true;
            if ($v === false || $v === 0 || $v === '0') return false;
            return null;
        };

        $m->update([
            'pricing_currency' => strtoupper((string)($row['pricing_currency'] ?? 'USD')),
            'price_input_per_1m' => ($row['price_input_per_1m'] ?? '') !== '' ? $row['price_input_per_1m'] : null,
            'price_cached_input_per_1m' => ($row['price_cached_input_per_1m'] ?? '') !== '' ? $row['price_cached_input_per_1m'] : null,
            'price_output_per_1m' => ($row['price_output_per_1m'] ?? '') !== '' ? $row['price_output_per_1m'] : null,
            'context_window' => ($row['context_window'] ?? '') !== '' ? (int)$row['context_window'] : null,
            'max_output_tokens' => ($row['max_output_tokens'] ?? '') !== '' ? (int)$row['max_output_tokens'] : null,
            'supports_temperature' => $toBoolOrNull($row['supports_temperature'] ?? null),
            'supports_top_p' => $toBoolOrNull($row['supports_top_p'] ?? null),
            'supports_presence_penalty' => $toBoolOrNull($row['supports_presence_penalty'] ?? null),
            'supports_frequency_penalty' => $toBoolOrNull($row['supports_frequency_penalty'] ?? null),
        ]);

        $this->pricingSaveMessage = "✅ Model settings gespeichert: {$m->model_id}";
    }

    public function canManageAiModels(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        $team = $user->currentTeam; // dynamic (root-scoped aware), but for core we want root owner anyway
        if (!$team) return false;
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;
        return $rootTeam->users()
            ->where('user_id', $user->id)
            ->wherePivot('role', \Platform\Core\Enums\TeamRole::OWNER->value)
            ->exists();
    }

    public function setDefaultModel(int $coreAiModelId): void
    {
        $m = CoreAiModel::with('provider')->findOrFail($coreAiModelId);
        if (!$m->provider) {
            return;
        }

        $m->provider->update(['default_model_id' => $m->id]);
        $this->pricingSaveMessage = "✅ Default Model gesetzt: {$m->provider->key} → {$m->model_id}";
    }

    /**
     * Refresh messages after streaming completes.
     * Forces Livewire to re-fetch computed properties from the database.
     */
    public function refreshMessages(): void
    {
        // Clear the computed property cache so Livewire fetches fresh data from DB.
        // Computed properties in Livewire 3 are cached per-request; calling unset() forces re-evaluation.
        unset($this->activeThreadMessages);
        unset($this->activeThread);
    }

    /**
     * Upload pending files and add them to uploadedAttachments.
     */
    public function uploadPendingFiles(): void
    {
        if (empty($this->pendingFiles)) {
            return;
        }

        $user = Auth::user();
        if (!$user || !$user->currentTeamRelation) {
            return;
        }

        $service = app(ContextFileService::class);

        foreach ($this->pendingFiles as $file) {
            try {
                $result = $service->uploadForContext(
                    $file,
                    CoreChatThread::class,
                    $this->activeThreadId ?? 0,
                    [
                        'keep_original' => false,
                        'generate_variants' => true,
                    ]
                );

                $this->uploadedAttachments[] = [
                    'id' => $result['id'],
                    'token' => $result['token'],
                    'original_name' => $result['original_name'],
                    'url' => $result['url'],
                    'mime_type' => $result['mime_type'],
                    'file_size' => $result['file_size'],
                    'width' => $result['width'] ?? null,
                    'height' => $result['height'] ?? null,
                    'is_image' => str_starts_with($result['mime_type'], 'image/'),
                ];
            } catch (\Exception $e) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Fehler beim Hochladen: ' . $e->getMessage(),
                ]);
            }
        }

        $this->pendingFiles = [];
    }

    /**
     * Remove an uploaded attachment before sending.
     */
    public function removeAttachment(int $index): void
    {
        if (isset($this->uploadedAttachments[$index])) {
            // Optionally delete the file from storage
            try {
                $attachment = $this->uploadedAttachments[$index];
                if (isset($attachment['id'])) {
                    $service = app(ContextFileService::class);
                    $service->delete($attachment['id']);
                }
            } catch (\Exception $e) {
                // Ignore deletion errors
            }

            array_splice($this->uploadedAttachments, $index, 1);
        }
    }

    /**
     * Clear all uploaded attachments (called after message is sent).
     */
    public function clearAttachments(): void
    {
        $this->uploadedAttachments = [];
        $this->pendingFiles = [];
    }

    /**
     * Get current attachments as JSON for JavaScript.
     */
    public function getAttachmentsJson(): string
    {
        return json_encode($this->uploadedAttachments);
    }

    /**
     * Get attachments for a specific message by its meta.
     */
    public function getMessageAttachments(int $messageId): array
    {
        $message = CoreChatMessage::find($messageId);
        if (!$message || empty($message->meta['attachments'])) {
            return [];
        }

        $attachmentIds = $message->meta['attachments'];
        return ContextFile::whereIn('id', $attachmentIds)
            ->with('variants')
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'token' => $file->token,
                    'original_name' => $file->original_name,
                    'url' => $file->url,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'width' => $file->width,
                    'height' => $file->height,
                    'is_image' => $file->isImage(),
                    'thumbnail' => $file->thumbnail?->url ?? null,
                ];
            })
            ->toArray();
    }

    public function render()
    {
        $models = CoreAiModel::query()
            ->with(['provider', 'provider.defaultModel'])
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get();

        // Prepare model options for select component (only active models)
        $activeModels = $models->filter(fn($m) => $m->is_active);
        $modelOptions = $activeModels->mapWithKeys(function ($model) {
            return [$model->model_id => $model->model_id];
        })->toArray();

        $activeThread = $this->activeThread;

        // Get default model from OpenAI provider
        $openaiProvider = CoreAiProvider::where('key', 'openai')->where('is_active', true)->with('defaultModel')->first();
        $defaultModelId = $openaiProvider?->defaultModel?->model_id ?? 'gpt-5.2';

        // Get active thread's model for initial selection, fallback to default
        $activeThreadModel = $activeThread?->model_id ?? $defaultModelId;

        return view('platform::livewire.modal-simple-tool-playground', [
            'coreAiModels' => $models,
            'modelOptions' => $modelOptions,
            'threads' => $this->threads,
            'activeThreadId' => $this->activeThreadId,
            'activeThread' => $activeThread,
            'activeThreadModel' => $activeThreadModel,
            'defaultModelId' => $defaultModelId,
            'activeThreadMessages' => $this->activeThreadMessages,
            'canManageAiModels' => $this->canManageAiModels(),
        ]);
    }
}


