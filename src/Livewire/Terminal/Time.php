<?php

namespace Platform\Core\Livewire\Terminal;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Core\Models\User;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\StoreTimeEntry;
use Platform\Organization\Services\StorePlannedTime;
use Platform\Organization\Traits\HasTimeEntries;

class Time extends Component
{
    use WithTerminalContext;

    public string $timeWorkDate = '';
    public int $timeMinutes = 60;
    public ?string $timeRate = null;
    public ?string $timeNote = null;
    public ?int $timePlannedMinutes = null;
    public ?string $timePlannedNote = null;
    public string $timeOverviewRange = 'all';
    public ?int $timeSelectedUserId = null;

    public function mount(): void
    {
        $this->timeWorkDate = now()->format('Y-m-d');
    }

    protected function onContextChanged(): void
    {
        $this->timeWorkDate = now()->format('Y-m-d');
        $this->timeMinutes = 60;
        $this->timeRate = null;
        $this->timeNote = null;
        $this->timePlannedMinutes = null;
        $this->timePlannedNote = null;
        $this->timeOverviewRange = 'all';
        $this->timeSelectedUserId = null;
        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats, $this->timeAvailableUsers);
    }

    #[Computed]
    public function timeEntries(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        if (! class_exists($this->contextType) || ! in_array(HasTimeEntries::class, class_uses_recursive($this->contextType))) {
            return [];
        }

        $contextPairs = [$this->contextType => [$this->contextId]];
        $this->collectTimeChildContextPairs($contextPairs);

        $baseQuery = OrganizationTimeEntry::query()
            ->where(function ($q) use ($contextPairs) {
                foreach ($contextPairs as $type => $ids) {
                    $q->orWhere(function ($sq) use ($type, $ids) {
                        $sq->where('context_type', $type)
                           ->whereIn('context_id', array_unique($ids));
                    });
                }
            });

        if ($this->timeSelectedUserId) {
            $baseQuery->where('user_id', $this->timeSelectedUserId);
        }

        $baseQuery = $this->applyTimeOverviewRangeFilter($baseQuery);

        return $baseQuery
            ->with('user')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'work_date' => $e->work_date->format('d.m.Y'),
                'minutes' => $e->minutes,
                'rate_cents' => $e->rate_cents,
                'amount_cents' => $e->amount_cents,
                'is_billed' => $e->is_billed,
                'note' => $e->note,
                'user_name' => $e->user?->name ?? 'Unbekannt',
                'user_initials' => $this->initials($e->user?->name),
                'user_avatar' => $e->user?->avatar,
            ])
            ->toArray();
    }

    #[Computed]
    public function timePlannedEntries(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        return OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('is_active', true)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'planned_minutes' => $e->planned_minutes,
                'note' => $e->note,
                'user_name' => $e->user?->name ?? 'Unbekannt',
                'created_at' => $e->created_at->format('d.m.Y'),
            ])
            ->toArray();
    }

    #[Computed]
    public function timeStats(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return ['totalMinutes' => 0, 'billedMinutes' => 0, 'unbilledMinutes' => 0, 'unbilledAmountCents' => 0, 'totalPlannedMinutes' => null];
        }

        if (! class_exists($this->contextType) || ! in_array(HasTimeEntries::class, class_uses_recursive($this->contextType))) {
            return ['totalMinutes' => 0, 'billedMinutes' => 0, 'unbilledMinutes' => 0, 'unbilledAmountCents' => 0, 'totalPlannedMinutes' => null];
        }

        $contextPairs = [$this->contextType => [$this->contextId]];
        $this->collectTimeChildContextPairs($contextPairs);

        $baseQuery = OrganizationTimeEntry::query()
            ->where(function ($q) use ($contextPairs) {
                foreach ($contextPairs as $type => $ids) {
                    $q->orWhere(function ($sq) use ($type, $ids) {
                        $sq->where('context_type', $type)
                           ->whereIn('context_id', array_unique($ids));
                    });
                }
            });

        if ($this->timeSelectedUserId) {
            $baseQuery->where('user_id', $this->timeSelectedUserId);
        }

        $baseQuery = $this->applyTimeOverviewRangeFilter($baseQuery);

        $totalMinutes = (int) (clone $baseQuery)->sum('minutes');
        $billedMinutes = (int) (clone $baseQuery)->where('is_billed', true)->sum('minutes');
        $unbilledAmountCents = (int) (clone $baseQuery)->where('is_billed', false)->sum('amount_cents');

        $totalPlannedMinutes = (int) OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->active()
            ->sum('planned_minutes');

        return [
            'totalMinutes' => $totalMinutes,
            'billedMinutes' => $billedMinutes,
            'unbilledMinutes' => max(0, $totalMinutes - $billedMinutes),
            'unbilledAmountCents' => $unbilledAmountCents,
            'totalPlannedMinutes' => $totalPlannedMinutes > 0 ? $totalPlannedMinutes : null,
        ];
    }

    #[Computed]
    public function timeAvailableUsers(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        $userIds = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->toArray();

        if (empty($userIds)) {
            return [];
        }

        $user = Auth::user();
        $team = $user?->currentTeamRelation;
        if (! $team) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->whereHas('teams', fn ($q) => $q->where('teams.id', $team->id))
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'initials' => $this->initials($u->name)])
            ->toArray();
    }

    public function saveTimeEntry(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $user || ! $team) {
            return;
        }

        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        $this->validate([
            'timeWorkDate' => ['required', 'date'],
            'timeMinutes' => ['required', 'integer', 'min:1'],
            'timeRate' => ['nullable', 'string'],
            'timeNote' => ['nullable', 'string', 'max:500'],
        ]);

        $rateCents = $this->timeRateToCents($this->timeRate);
        if ($this->timeRate && $rateCents === null) {
            $this->addError('timeRate', 'Bitte einen gültigen Betrag eingeben.');
            return;
        }

        $minutes = max(1, (int) $this->timeMinutes);
        $amountCents = $rateCents !== null ? (int) round($rateCents * ($minutes / 60)) : null;

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);
        if (! $context) {
            return;
        }

        $storeTimeEntry = app(StoreTimeEntry::class);

        $storeTimeEntry->store([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'work_date' => $this->timeWorkDate,
            'minutes' => $minutes,
            'rate_cents' => $rateCents,
            'amount_cents' => $amountCents,
            'is_billed' => false,
            'metadata' => null,
            'note' => $this->timeNote,
        ]);

        $this->timeWorkDate = now()->format('Y-m-d');
        $this->timeMinutes = 60;
        $this->timeRate = null;
        $this->timeNote = null;
        $this->resetValidation();

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Zeit erfasst.']);
    }

    public function toggleTimeBilled(int $id): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team) {
            return;
        }

        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($id);

        if ($entry->team_id !== $team->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Keine Berechtigung.']);
            return;
        }

        $entry->is_billed = ! $entry->is_billed;
        $entry->save();

        unset($this->timeEntries, $this->timeStats);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $entry->is_billed ? 'Als abgerechnet markiert.' : 'Wieder auf offen gesetzt.',
        ]);
    }

    public function deleteTimeEntry(int $id): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team) {
            return;
        }

        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($id);

        if ($entry->team_id !== $team->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Keine Berechtigung.']);
            return;
        }

        $entry->delete();

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Zeiteintrag gelöscht.']);
    }

    public function saveTimePlanned(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $user || ! $team) {
            return;
        }

        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        $this->validate([
            'timePlannedMinutes' => ['required', 'integer', 'min:1'],
            'timePlannedNote' => ['nullable', 'string', 'max:500'],
        ]);

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);
        if (! $context) {
            return;
        }

        $storePlannedTime = app(StorePlannedTime::class);

        $storePlannedTime->store([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'planned_minutes' => (int) $this->timePlannedMinutes,
            'note' => $this->timePlannedNote,
            'is_active' => true,
        ]);

        $this->timePlannedMinutes = null;
        $this->timePlannedNote = null;
        $this->resetValidation();

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Budget hinzugefügt.']);
    }

    public function deleteTimePlanned(int $id): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team) {
            return;
        }

        $entry = OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('id', $id)
            ->where('is_active', true)
            ->firstOrFail();

        if ($entry->team_id !== $team->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Keine Berechtigung.']);
            return;
        }

        $entry->update(['is_active' => false]);

        unset($this->timeEntries, $this->timePlannedEntries, $this->timeStats);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Budget deaktiviert.']);
    }

    public function updatedTimeOverviewRange(): void
    {
        unset($this->timeEntries, $this->timeStats);
    }

    public function updatedTimeSelectedUserId(): void
    {
        unset($this->timeEntries, $this->timeStats);
    }

    protected function applyTimeOverviewRangeFilter($query)
    {
        if ($this->timeOverviewRange === 'all') {
            return $query;
        }

        $now = now();

        return match ($this->timeOverviewRange) {
            'current_week' => $query->whereBetween('work_date', [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()]),
            'current_month' => $query->whereBetween('work_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()]),
            'current_year' => $query->whereBetween('work_date', [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()]),
            default => $query,
        };
    }

    protected function collectTimeChildContextPairs(array &$pairs): void
    {
        $orgContext = OrganizationContext::query()
            ->where('contextable_type', $this->contextType)
            ->where('contextable_id', $this->contextId)
            ->where('is_active', true)
            ->first();

        if (! $orgContext || empty($orgContext->include_children_relations)) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model) {
            return;
        }

        foreach ($orgContext->include_children_relations as $relationPath) {
            $this->resolveTimeRelationPathForPairs($model, $relationPath, $pairs);
        }
    }

    protected function resolveTimeRelationPathForPairs($model, string $path, array &$pairs): void
    {
        $segments = explode('.', $path);
        $currentModels = collect([$model]);

        foreach ($segments as $segment) {
            $nextModels = collect();
            foreach ($currentModels as $currentModel) {
                if (! method_exists($currentModel, $segment)) {
                    continue;
                }
                $related = $currentModel->{$segment};
                if ($related instanceof \Illuminate\Database\Eloquent\Collection) {
                    $nextModels = $nextModels->merge($related);
                } elseif ($related instanceof \Illuminate\Database\Eloquent\Model) {
                    $nextModels->push($related);
                }
            }
            $currentModels = $nextModels;
        }

        foreach ($currentModels as $leafModel) {
            $type = get_class($leafModel);
            $pairs[$type][] = $leafModel->id;
        }
    }

    protected function timeRateToCents(?string $value): ?int
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

    protected function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }

        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }

    public function render()
    {
        return view('platform::livewire.terminal.time');
    }
}
