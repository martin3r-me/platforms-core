<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatThread;
use Platform\Core\Models\CoreChatMessage;
use Illuminate\Support\Facades\Auth;

class ModalSimpleToolPlayground extends Component
{
    public bool $open = false;

    /** @var array<string,mixed>|null */
    public ?array $context = null;

    /** @var array<int, array<string,mixed>> */
    public array $pricingEdits = [];

    public ?string $pricingSaveMessage = null;

    public ?int $activeThreadId = null;
    public ?CoreChat $chat = null;

    #[On('playground:open')]
    public function openModal(array $payload = []): void
    {
        $ctx = $payload['context'] ?? $payload['terminal_context'] ?? null;
        $this->context = is_array($ctx) ? $ctx : null;
        $this->open = true;

        // Pre-fill pricing edits for the "Model settings" tab.
        $this->pricingEdits = [];
        $models = CoreAiModel::query()->orderBy('provider_id')->orderBy('model_id')->get();
        foreach ($models as $m) {
            $this->pricingEdits[(int)$m->id] = [
                'pricing_currency' => (string)($m->pricing_currency ?? 'USD'),
                'price_input_per_1m' => $m->price_input_per_1m,
                'price_cached_input_per_1m' => $m->price_cached_input_per_1m,
                'price_output_per_1m' => $m->price_output_per_1m,
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

        $this->chat = CoreChat::firstOrCreate(
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

        // Load most recent open thread or create one
        $thread = $this->chat->threads()
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$thread) {
            $thread = $this->chat->threads()->create([
                'title' => 'Thread ' . ($this->chat->threads()->count() + 1),
                'status' => 'open',
                'started_at' => now(),
            ]);
        }

        $this->activeThreadId = $thread->id;
    }

    public function createThread(): void
    {
        if (!$this->chat) {
            $this->loadOrCreateChat();
        }
        if (!$this->chat) {
            return;
        }

        $thread = $this->chat->threads()->create([
            'title' => 'Thread ' . ($this->chat->threads()->count() + 1),
            'status' => 'open',
            'started_at' => now(),
        ]);

        $this->activeThreadId = $thread->id;
        $this->dispatch('simple-playground:thread-changed', ['thread_id' => $thread->id]);
    }

    public function switchThread(int $threadId): void
    {
        if (!$this->chat) {
            return;
        }

        $thread = $this->chat->threads()->find($threadId);
        if (!$thread) {
            return;
        }

        $this->activeThreadId = $threadId;
        $this->dispatch('simple-playground:thread-changed', ['thread_id' => $threadId]);
    }

    public function saveModelPricing(int $coreAiModelId): void
    {
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

    public function setDefaultModel(int $coreAiModelId): void
    {
        $m = CoreAiModel::with('provider')->findOrFail($coreAiModelId);
        if (!$m->provider) {
            return;
        }

        $m->provider->update(['default_model_id' => $m->id]);
        $this->pricingSaveMessage = "✅ Default Model gesetzt: {$m->provider->key} → {$m->model_id}";
    }

    public function render()
    {
        $models = CoreAiModel::query()
            ->with(['provider', 'provider.defaultModel'])
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get();

        $threads = $this->chat
            ? $this->chat->threads()->orderBy('created_at', 'desc')->get()
            : collect();

        $activeThread = $this->activeThreadId
            ? CoreChatThread::find($this->activeThreadId)
            : null;

        return view('platform::livewire.modal-simple-tool-playground', [
            'coreAiModels' => $models,
            'threads' => $threads,
            'activeThreadId' => $this->activeThreadId,
            'activeThread' => $activeThread,
        ]);
    }
}


