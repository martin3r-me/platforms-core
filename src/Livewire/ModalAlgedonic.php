<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Events\AlgedonicTriggered;

/**
 * Algedonic-Modal: roter "Schmerz-Knopf" aus der Navbar.
 *
 * Erfasst eine kurze Meldung des Menschen ("das System hat ein Problem")
 * und feuert das AlgedonicTriggered-Event. Was daraus entsteht, entscheiden
 * registrierte Listener in den Modulen (z.B. Organization erzeugt ein
 * Signal mit vsm_level=s5, Carrier-Owner und kurzer Deadline).
 *
 * Bewusst minimal: ein Textfeld, optional eine Entity-ID. Keine Severity-
 * Auswahl — Algedonic ist per Definition immer dringend.
 */
class ModalAlgedonic extends Component
{
    public bool $modalShow = false;
    public string $message = '';
    public ?int $entityId = null;

    protected array $rules = [
        'message' => 'required|string|min:3|max:2000',
        'entityId' => 'nullable|integer|min:1',
    ];

    #[On('open-modal-algedonic')]
    public function openModal(?array $payload = null): void
    {
        $this->reset(['message', 'entityId']);
        if (is_array($payload) && isset($payload['entity_id'])) {
            $this->entityId = (int) $payload['entity_id'];
        }
        $this->modalShow = true;
    }

    public function send(): void
    {
        $this->validate();

        $userId = auth()->id();
        $teamId = auth()->user()?->currentTeam?->id;

        if (! $userId || ! $teamId) {
            return;
        }

        AlgedonicTriggered::dispatch(
            trim($this->message),
            (int) $userId,
            (int) $teamId,
            $this->entityId,
            null,
        );

        $this->modalShow = false;
        $this->reset(['message', 'entityId']);

        $this->dispatch('notify', message: 'Algedonic-Signal gesendet', level: 'success');
    }

    public function render()
    {
        return view('platform::livewire.modal-algedonic');
    }
}
