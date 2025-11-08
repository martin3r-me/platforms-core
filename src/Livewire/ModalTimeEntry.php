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
use Platform\Core\Models\CoreTimePlanned;
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

    public string $activeTab = 'entry'; // 'entry', 'overview', or 'planned'

    public $entries = [];
    public $plannedEntries = [];

    public ?int $plannedMinutes = null;
    public ?string $plannedNote = null;

    protected array $minuteOptions = [15, 30, 45, 60, 90, 120, 180, 240, 300, 360, 420, 480];

    public function mount(): void
    {
        // Initialisierung
    }

    #[On('timeEntryContextSet')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;
        $this->linkedContexts = $payload['linked_contexts'] ?? [];

        // Wenn Modal bereits offen ist, Daten neu laden
        if ($this->open && $this->contextType && $this->contextId) {
            $this->loadEntries();
            $this->loadPlannedEntries();
            $this->loadCurrentPlanned();
        }
    }

    #[On('time-entry:open')]
    public function open(array $payload = []): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeam) {
            return;
        }

        // Kontext wurde bereits durch time-entry-context:set Event gesetzt
        // Wenn kein Kontext vorhanden, Modal trotzdem öffnen (Fallback-Modus)
        if (! $this->contextType || ! $this->contextId) {
            $this->workDate = now()->toDateString();
            $this->minutes = $payload['minutes'] ?? 60;
            $this->rate = $payload['rate'] ?? null;
            $this->note = $payload['note'] ?? null;
            $this->activeTab = 'entry';
            $this->open = true;
            return;
        }

        if (! class_exists($this->contextType) || ! $this->contextSupportsTimeEntries($this->contextType)) {
            $this->workDate = now()->toDateString();
            $this->minutes = $payload['minutes'] ?? 60;
            $this->rate = $payload['rate'] ?? null;
            $this->note = $payload['note'] ?? null;
            $this->activeTab = 'entry';
            $this->open = true;
            return;
        }

        $this->workDate = now()->toDateString();
        $this->minutes = $payload['minutes'] ?? 60;
        $this->rate = $payload['rate'] ?? null;
        $this->note = $payload['note'] ?? null;
        $this->activeTab = 'entry';

        $this->loadEntries();
        $this->loadPlannedEntries();
        $this->loadCurrentPlanned();
        $this->open = true;
    }

    #[On('time-entry:close')]
    public function close(): void
    {
        $this->resetValidation();
        $this->reset('open', 'contextType', 'contextId', 'linkedContexts', 'workDate', 'minutes', 'rate', 'note', 'activeTab', 'entries', 'plannedEntries', 'plannedMinutes', 'plannedNote');
    }

    #[On('time-entry:saved')]
    public function reload(): void
    {
        $this->loadEntries();
        $this->activeTab = 'overview';
        $this->resetValidation();
        $this->reset('workDate', 'minutes', 'rate', 'note');
        $this->workDate = now()->toDateString();
        $this->minutes = 60;
    }

    protected function loadEntries(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->entries = collect();
            return;
        }

        $this->entries = CoreTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->with('user')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function getTotalMinutesProperty(): int
    {
        if (! $this->contextType || ! $this->contextId) {
            return 0;
        }

        return (int) CoreTimeEntry::query()->forContextKey($this->contextType, $this->contextId)->sum('minutes');
    }

    public function getBilledMinutesProperty(): int
    {
        if (! $this->contextType || ! $this->contextId) {
            return 0;
        }

        return (int) CoreTimeEntry::query()->forContextKey($this->contextType, $this->contextId)->where('is_billed', true)->sum('minutes');
    }

    public function getUnbilledMinutesProperty(): int
    {
        return max(0, $this->totalMinutes - $this->billedMinutes);
    }

    public function getUnbilledAmountCentsProperty(): int
    {
        if (! $this->contextType || ! $this->contextId) {
            return 0;
        }

        return (int) CoreTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('is_billed', false)
            ->sum('amount_cents');
    }

    public function toggleBilled(int $entryId): void
    {
        $entry = CoreTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($entryId);

        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $team || $entry->team_id !== $team->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung für diesen Eintrag.',
            ]);
            return;
        }

        $entry->is_billed = ! $entry->is_billed;
        $entry->save();

        $this->loadEntries();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $entry->is_billed ? 'Eintrag als abgerechnet markiert.' : 'Eintrag wieder auf offen gesetzt.',
        ]);
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = CoreTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($entryId);

        $user = Auth::user();
        $team = $user?->currentTeam;

        if (! $team || $entry->team_id !== $team->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung für diesen Eintrag.',
            ]);
            return;
        }

        $entry->delete();
        $this->loadEntries();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Zeiteintrag gelöscht.',
        ]);
    }

    protected function loadPlannedEntries(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->plannedEntries = collect();
            return;
        }

        $this->plannedEntries = CoreTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    protected function loadCurrentPlanned(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->plannedMinutes = null;
            return;
        }

        $current = CoreTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->active()
            ->first();

        $this->plannedMinutes = $current?->planned_minutes;
        $this->plannedNote = $current?->note;
    }

    public function getCurrentPlannedMinutesProperty(): ?int
    {
        if (! $this->contextType || ! $this->contextId) {
            return null;
        }

        $current = CoreTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->active()
            ->first();

        return $current?->planned_minutes;
    }

    public function savePlanned(): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeam) {
            $this->addError('plannedMinutes', 'Kein Team-Kontext vorhanden.');
            return;
        }

        $this->validate([
            'plannedMinutes' => ['required', 'integer', 'min:1'],
            'plannedNote' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        $team = $user?->currentTeam;

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);

        if (! $context) {
            $this->addError('plannedMinutes', 'Kontext nicht gefunden.');
            return;
        }

        if (property_exists($context, 'team_id') && (int) $context->team_id !== $team->id) {
            $this->addError('plannedMinutes', 'Kontext gehört nicht zu Ihrem Team.');
            return;
        }

        CoreTimePlanned::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'planned_minutes' => (int) $this->plannedMinutes,
            'note' => $this->plannedNote,
            'is_active' => true,
        ]);

        $this->loadPlannedEntries();
        $this->loadCurrentPlanned();
        $this->resetValidation();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Soll-Zeit aktualisiert.',
        ]);
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

        $this->loadEntries();
        $this->activeTab = 'overview';
        $this->resetValidation();
        $this->reset('workDate', 'minutes', 'rate', 'note');
        $this->workDate = now()->toDateString();
        $this->minutes = 60;

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


