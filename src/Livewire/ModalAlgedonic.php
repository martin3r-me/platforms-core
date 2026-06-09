<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\Computed;
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
 * Bewusst minimal — Algedonic ist per Definition immer dringend, daher
 * keine Severity-Auswahl. Identitaet ist Default, optional anonym
 * (Selbstschutz, damit Menschen sich trauen zu melden).
 */
class ModalAlgedonic extends Component
{
    public bool $modalShow = false;
    public string $message = '';
    public string $entitySearch = '';
    public ?int $entityId = null;
    public ?string $entityName = null;
    public bool $anonymous = false;
    public bool $sent = false;

    protected array $rules = [
        'message' => 'required|string|min:3|max:2000',
        'entityId' => 'nullable|integer|min:1',
        'anonymous' => 'boolean',
    ];

    #[On('open-modal-algedonic')]
    public function openModal(?array $payload = null): void
    {
        $this->reset(['message', 'entitySearch', 'entityId', 'entityName', 'anonymous', 'sent']);
        if (is_array($payload) && isset($payload['entity_id'])) {
            $this->entityId = (int) $payload['entity_id'];
            $this->entityName = isset($payload['entity_name']) ? (string) $payload['entity_name'] : null;
            $this->entitySearch = $this->entityName ?? '';
        }
        $this->modalShow = true;
    }

    #[Computed]
    public function entitySuggestions(): array
    {
        $term = trim($this->entitySearch);
        if (strlen($term) < 2) {
            return [];
        }

        // Wenn bereits selektiert und Suchbegriff = Name, keine Vorschlaege mehr
        if ($this->entityId && $this->entityName === $term) {
            return [];
        }

        $entityClass = '\\Platform\\Organization\\Models\\OrganizationEntity';
        if (! class_exists($entityClass)) {
            return [];
        }

        $teamId = auth()->user()?->currentTeam?->id;
        if (! $teamId) {
            return [];
        }

        return $entityClass::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->where('name', 'like', '%' . $term . '%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name'])
            ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])
            ->all();
    }

    public function selectEntity(int $id, string $name): void
    {
        $this->entityId = $id;
        $this->entityName = $name;
        $this->entitySearch = $name;
    }

    public function clearEntity(): void
    {
        $this->entityId = null;
        $this->entityName = null;
        $this->entitySearch = '';
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
            $this->anonymous ? 0 : (int) $userId,
            (int) $teamId,
            $this->entityId,
            null,
        );

        // Inline-Bestaetigung im Modal, dann self-close via JS-Dispatch
        $this->sent = true;
        $this->dispatch('algedonic-sent');
    }

    public function close(): void
    {
        $this->modalShow = false;
        $this->reset(['message', 'entitySearch', 'entityId', 'entityName', 'anonymous', 'sent']);
    }

    public function render()
    {
        return view('platform::livewire.modal-algedonic');
    }
}
