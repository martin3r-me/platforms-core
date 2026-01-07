<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class ModalSimpleToolPlayground extends Component
{
    public bool $open = false;

    /** @var array<string,mixed>|null */
    public ?array $context = null;

    #[On('playground:open')]
    public function openModal(array $payload = []): void
    {
        $ctx = $payload['context'] ?? $payload['terminal_context'] ?? null;
        $this->context = is_array($ctx) ? $ctx : null;
        $this->open = true;
    }

    public function render()
    {
        return view('platform::livewire.modal-simple-tool-playground');
    }
}


