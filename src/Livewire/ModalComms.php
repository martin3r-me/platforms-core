<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

/**
 * UI-only Comms v2 shell (no data, no logic).
 * Triggered from the navbar via the `open-modal-comms` event.
 */
class ModalComms extends Component
{
    public bool $open = false;

    #[On('open-modal-comms')]
    public function openModal(array $payload = []): void
    {
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    public function render()
    {
        return view('platform::livewire.modal-comms');
    }
}

