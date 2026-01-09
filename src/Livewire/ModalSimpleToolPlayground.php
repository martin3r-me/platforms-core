<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\CoreAiModel;
use Illuminate\Validation\Rule;

class ModalSimpleToolPlayground extends Component
{
    public bool $open = false;

    /** @var array<string,mixed>|null */
    public ?array $context = null;

    /** @var array<int, array<string,mixed>> */
    public array $pricingEdits = [];

    public ?string $pricingSaveMessage = null;

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

        // Ensure the client-side playground initializes AFTER the modal is open and context is rendered.
        // (DOMContentLoaded might have happened long before; modal is opened later.)
        $this->dispatch('simple-playground-modal-opened');
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

    public function render()
    {
        $models = CoreAiModel::query()
            ->with('provider')
            ->orderBy('provider_id')
            ->orderBy('model_id')
            ->get();

        return view('platform::livewire.modal-simple-tool-playground', [
            'coreAiModels' => $models,
        ]);
    }
}


