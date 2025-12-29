<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ModalFiles extends Component
{
    public bool $open = false;

    public ?string $contextType = null;
    public ?int $contextId = null;

    public function mount(): void
    {
        // Initialisierung
    }

    #[On('files')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;
    }

    #[On('files:open')]
    public function open(): void
    {
        if (!Auth::check() || !Auth::user()->currentTeamRelation) {
            return;
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->open = false;
        $this->reset('contextType', 'contextId');
    }

    public function getContextLabelProperty(): ?string
    {
        if (!$this->contextType || !$this->contextId) {
            return null;
        }

        if (!class_exists($this->contextType)) {
            return null;
        }

        $context = $this->contextType::find($this->contextId);
        if (!$context) {
            return null;
        }

        // Versuche verschiedene Methoden für Label
        if (method_exists($context, 'getDisplayName')) {
            return $context->getDisplayName();
        }

        if (isset($context->title)) {
            return $context->title;
        }

        if (isset($context->name)) {
            return $context->name;
        }

        return class_basename($this->contextType) . ' #' . $this->contextId;
    }

    public function getContextBreadcrumbProperty(): array
    {
        if (!$this->contextType || !$this->contextId) {
            return [];
        }

        return [
            [
                'type' => class_basename($this->contextType),
                'label' => $this->contextLabel,
            ],
        ];
    }

    public function render()
    {
        return view('platform::livewire.modal-files');
    }
}

