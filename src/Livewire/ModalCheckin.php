<?php

namespace Platform\Core\Livewire;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Core\Enums\GoalCategory;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\CheckinTodo;

class ModalCheckin extends Component
{
    public bool $modalShow = false;
    public string $selectedDate;
    public string $windowStart;
    public ?Checkin $checkin = null;
    public array $checkinData = [];
    public array $todos = [];
    public string $newTodoTitle = '';
    public array $checkins = [];

    public string $activeTab = 'today';
    public array $trendData = [];

    protected $listeners = ['open-modal-checkin' => 'openModal'];

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->windowStart = now()->subDays(6)->toDateString();
        $this->loadCheckins();
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function openModal(): void
    {
        $this->modalShow = true;
        $this->loadCheckins();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        if ($tab === 'trends') {
            $this->loadTrends();
        }
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->loadCheckinForDate($date);
    }

    public function goToToday(): void
    {
        $this->selectedDate = now()->toDateString();
        $this->windowStart = now()->subDays(6)->toDateString();
        $this->loadCheckins();
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function previousWeek(): void
    {
        $this->windowStart = Carbon::parse($this->windowStart)->subDays(7)->toDateString();
        $this->loadCheckins();
    }

    public function nextWeek(): void
    {
        $next = Carbon::parse($this->windowStart)->addDays(7);
        if ($next->copy()->addDays(6)->gt(now())) {
            $this->goToToday();
            return;
        }
        $this->windowStart = $next->toDateString();
        $this->loadCheckins();
    }

    public function loadCheckinForDate(string $date): void
    {
        $this->checkin = Checkin::where('user_id', auth()->id())
            ->where('date', $date)
            ->with('todos')
            ->first();

        if ($this->checkin) {
            $this->checkinData = $this->checkin->toArray();
            $this->todos = $this->checkin->todos->toArray();
        } else {
            $this->checkinData = $this->emptyCheckinData($date);
            $this->todos = [];
        }
    }

    public function loadCheckins(): void
    {
        $start = Carbon::parse($this->windowStart);
        $end = $start->copy()->addDays(6);

        $this->checkins = Checkin::where('user_id', auth()->id())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();
    }

    #[Computed]
    public function visibleDays(): array
    {
        $start = Carbon::parse($this->windowStart);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $days[] = [
                'date' => $key,
                'day' => $date->format('d.'),
                'weekday' => $date->locale('de')->isoFormat('dd'),
                'is_today' => $date->isToday(),
                'is_selected' => $key === $this->selectedDate,
                'is_future' => $date->isFuture(),
                'has_checkin' => in_array($key, $this->checkins),
            ];
        }
        return $days;
    }

    public function save(bool $close = true): void
    {
        $this->validate([
            'checkinData.daily_goal' => 'nullable|string|max:1000',
            'checkinData.goal_category' => ['nullable', 'string', Rule::in(GoalCategory::values())],
            'checkinData.mood_score' => 'nullable|integer|min:0|max:4',
            'checkinData.energy_score' => 'nullable|integer|min:0|max:4',
            'checkinData.hydrated' => 'boolean',
            'checkinData.exercised' => 'boolean',
            'checkinData.slept_well' => 'boolean',
            'checkinData.focused_work' => 'boolean',
            'checkinData.social_time' => 'boolean',
            'checkinData.needs_support' => 'boolean',
            'checkinData.notes' => 'nullable|string|max:2000',
        ]);

        $data = array_merge($this->checkinData, [
            'user_id' => auth()->id(),
            'date' => $this->selectedDate,
            'mood_score' => $this->nullableInt($this->checkinData['mood_score'] ?? null),
            'energy_score' => $this->nullableInt($this->checkinData['energy_score'] ?? null),
        ]);

        if ($this->checkin) {
            $this->checkin->update($data);
        } else {
            $this->checkin = Checkin::create($data);
        }

        $this->loadCheckins();
        $this->dispatch('checkin-saved');

        if ($close) {
            $this->dispatch('notice', [
                'type' => 'success',
                'message' => 'Check-in erfolgreich gespeichert!',
            ]);
            $this->modalShow = false;
        }
    }

    public function addTodo(): void
    {
        if (trim($this->newTodoTitle) === '') {
            return;
        }

        $checkin = Checkin::firstOrCreate(
            ['user_id' => auth()->id(), 'date' => $this->selectedDate],
        );

        CheckinTodo::create([
            'checkin_id' => $checkin->id,
            'title' => $this->newTodoTitle,
            'done' => false,
        ]);

        $this->newTodoTitle = '';
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function toggleTodo(int $todoId): void
    {
        $todo = CheckinTodo::find($todoId);
        if ($todo) {
            $todo->update(['done' => !$todo->done]);
            $this->loadCheckinForDate($this->selectedDate);
        }
    }

    public function deleteTodo(int $todoId): void
    {
        CheckinTodo::find($todoId)?->delete();
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function postponeTodo(int $todoId): void
    {
        $todo = CheckinTodo::with('checkin')->find($todoId);
        if (!$todo || !$todo->checkin) {
            return;
        }
        if ((int) $todo->checkin->user_id !== (int) auth()->id()) {
            return;
        }

        $nextDate = Carbon::parse($todo->checkin->date)->addDay()->toDateString();

        $target = Checkin::firstOrCreate(
            ['user_id' => auth()->id(), 'date' => $nextDate],
        );

        $todo->update(['checkin_id' => $target->id]);
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function loadTrends(): void
    {
        $userId = auth()->id();
        if (!$userId) {
            $this->trendData = [];
            return;
        }

        $start = now()->subDays(29)->startOfDay();
        $end = now()->endOfDay();

        $checkins = Checkin::where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($c) => Carbon::parse($c->date)->toDateString());

        $days = [];
        $moods = [];
        $energies = [];
        $habits = [
            'hydrated' => 0,
            'exercised' => 0,
            'slept_well' => 0,
            'focused_work' => 0,
            'social_time' => 0,
        ];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();
            $checkin = $checkins->get($key);

            $days[] = [
                'date' => $key,
                'label' => $date->format('d.m.'),
                'weekday' => $date->locale('de')->isoFormat('dd'),
                'is_today' => $date->isToday(),
                'is_weekend' => $date->isWeekend(),
                'mood' => $checkin?->mood_score,
                'energy' => $checkin?->energy_score,
                'has_checkin' => (bool) $checkin,
            ];

            if ($checkin) {
                if ($checkin->mood_score !== null) {
                    $moods[] = $checkin->mood_score;
                }
                if ($checkin->energy_score !== null) {
                    $energies[] = $checkin->energy_score;
                }
                foreach (array_keys($habits) as $habitKey) {
                    if ($checkin->$habitKey) {
                        $habits[$habitKey]++;
                    }
                }
            }
        }

        $this->trendData = [
            'days' => $days,
            'avg_mood' => count($moods) ? round(array_sum($moods) / count($moods), 1) : null,
            'avg_energy' => count($energies) ? round(array_sum($energies) / count($energies), 1) : null,
            'mood_count' => count($moods),
            'energy_count' => count($energies),
            'habits' => $habits,
            'total_checkins' => $checkins->count(),
            'current_streak' => Checkin::currentStreak($userId),
            'window_days' => 30,
        ];
    }

    public function getGoalCategoryOptions(): array
    {
        return Checkin::getGoalCategoryOptions();
    }

    protected function emptyCheckinData(string $date): array
    {
        return [
            'date' => $date,
            'daily_goal' => '',
            'goal_category' => null,
            'mood_score' => null,
            'energy_score' => null,
            'needs_support' => false,
            'hydrated' => false,
            'exercised' => false,
            'slept_well' => false,
            'focused_work' => false,
            'social_time' => false,
            'notes' => '',
        ];
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    public function render()
    {
        return view('platform::livewire.modal-checkin');
    }
}
