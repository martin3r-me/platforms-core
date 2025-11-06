<?php

namespace Platform\Core\Livewire;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\CoreTimeEntry;
use Platform\Core\Models\CoreTimeEntryContext;
use Platform\Core\Traits\HasTimeEntries;

class ModalTimeEntry extends Component
{
    use AuthorizesRequests;

    public bool $open = false;

    public ?string $contextType = null;
    public ?int $contextId = null;

    public array $linkedContexts = [];

    public string $workDate;
    public int $minutes = 60;
    public ?string $rate = null;
    public ?string $note = null;

    protected array $minuteOptions = [15, 30, 45, 60, 90, 120, 180, 240, 300, 360, 420, 480];

    #[On('time-entry:open')]
    public function open(array $payload): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeam) {
            return;
        }

        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;
        $this->linkedContexts = $payload['linked_contexts'] ?? [];

        if (! $this->contextType || ! class_exists($this->contextType) || ! $this->contextSupportsTimeEntries($this->contextType)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Dieser Kontext unterstützt keine Zeiterfassung.',
            ]);
            return;
        }

        $this->workDate = now()->toDateString();
        $this->minutes = $payload['minutes'] ?? 60;
        $this->rate = $payload['rate'] ?? null;
        $this->note = $payload['note'] ?? null;

        $this->open = true;
    }

    #[On('time-entry:close')]
    public function close(): void
    {
        $this->resetValidation();
        $this->reset('open', 'contextType', 'contextId', 'linkedContexts', 'workDate', 'minutes', 'rate', 'note');
    }

    public function getMinuteOptionsProperty(): array
    {
        return $this->minuteOptions;
    }

    public function rules(): array
    {
        return [
            'contextType' => ['required', 'string'],
            'contextId' => ['required', 'integer'],
            'workDate' => ['required', 'date'],
            'minutes' => ['required', 'integer', Rule::in($this->minuteOptions)],
            'rate' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function updatedMinutes($value): void
    {
        $this->minutes = (int) $value;
    }

    public function save(): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeam) {
            $this->addError('contextType', 'Kein Team-Kontext vorhanden.');
            return;
        }

        $this->validate();

        $user = Auth::user();
        $team = $user?->currentTeam;

        $rateCents = $this->rateToCents($this->rate);
        if ($this->rate && $rateCents === null) {
            $this->addError('rate', 'Bitte einen gültigen Betrag eingeben.');
            return;
        }

        $minutes = max(1, (int) $this->minutes);
        $amountCents = $rateCents !== null
            ? (int) round($rateCents * ($minutes / 60))
            : null;

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);

        if (! $context) {
            $this->addError('contextType', 'Kontext nicht gefunden.');
            return;
        }

        if (property_exists($context, 'team_id') && (int) $context->team_id !== $team->id) {
            $this->addError('contextType', 'Kontext gehört nicht zu Ihrem Team.');
            return;
        }

        $entry = CoreTimeEntry::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'work_date' => $this->workDate,
            'minutes' => $minutes,
            'rate_cents' => $rateCents,
            'amount_cents' => $amountCents,
            'is_billed' => false,
            'metadata' => null,
            'note' => $this->note,
        ]);

        foreach ($this->linkedContexts as $context) {
            if (! isset($context['type'], $context['id'])) {
                continue;
            }

            CoreTimeEntryContext::create([
                'time_entry_id' => $entry->id,
                'context_type' => $context['type'],
                'context_id' => (int) $context['id'],
            ]);
        }

        $this->dispatch('time-entry:saved', id: $entry->id);

        $this->reset('open');
        $this->resetValidation();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Zeit erfasst',
        ]);
    }

    protected function contextSupportsTimeEntries(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return in_array(HasTimeEntries::class, class_uses_recursive($class));
    }

    protected function rateToCents(?string $value): ?int
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

    public function render()
    {
        return view('platform::livewire.modal-time-entry');
    }
}


