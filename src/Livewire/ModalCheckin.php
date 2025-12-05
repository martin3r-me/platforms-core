<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\CheckinTodo;
use Platform\Core\Models\PomodoroSession;
use Platform\Core\Enums\GoalCategory;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Platform\Core\Services\Checkins\CheckinReminderService;

class ModalCheckin extends Component
{
    public $modalShow = false;
    public $selectedDate;
    public $checkin;
    public $checkinData = [];
    public $todos = [];
    public $newTodoTitle = '';
    public $currentMonth;
    public $currentYear;
    public $checkins = [];

    // Pomodoro Properties
    public $pomodoroStats = [];

    protected $listeners = ['open-modal-checkin' => 'openModal'];

    // Computed Properties
    public function getRemainingTime()
    {
        if (isset($this->pomodoroStats['active_session']) && $this->pomodoroStats['active_session']) {
            $session = $this->pomodoroStats['active_session'];
            $remainingMinutes = $session['remaining_minutes'] ?? 0;
            return max(0, $remainingMinutes);
        }
        return 0;
    }

    public function mount(CheckinReminderService $checkinReminderService)
    {
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadCheckins();
        $this->loadCheckinForDate($this->selectedDate);
        $this->loadPomodoroStats(); // Load pomodoro stats on mount

        if (auth()->check() && $checkinReminderService->shouldForceModal(auth()->user())) {
            $this->modalShow = true;
        }
    }

    public function goToToday()
    {
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadCheckins();
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function openModal()
    {
        $this->modalShow = true;
        $this->loadCheckins();
    }

    public function selectDate($date)
    {
        $this->selectedDate = $date;
        $this->loadCheckinForDate($date);
    }

    public function loadCheckinForDate($date)
    {
        $this->checkin = Checkin::where('user_id', auth()->id())
            ->where('date', $date)
            ->with('todos')
            ->first();

        if ($this->checkin) {
            $this->checkinData = $this->checkin->toArray();
            $this->todos = $this->checkin->todos->toArray();
        } else {
            $this->checkinData = [
                'date' => $date,
                'daily_goal' => '',
                'goal_category' => null,
                'mood' => null,
                'happiness' => null,
                'mood_score' => null,
                'energy_score' => null,
                'needs_support' => false,
                'hydrated' => false,
                'exercised' => false,
                'slept_well' => false,
                'focused_work' => false,
                'social_time' => false,
                'notes' => ''
            ];
            $this->todos = [];
        }
    }

    public function loadCheckins()
    {
        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1)->endOfMonth();

        $this->checkins = Checkin::where('user_id', auth()->id())
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->pluck('date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();
    }

    public function previousMonth()
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadCheckins();
    }

    public function nextMonth()
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->loadCheckins();
    }

    public function save()
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

            $checkinData = array_merge($this->checkinData, [
                'user_id' => auth()->id(),
                'date' => $this->selectedDate,
                'mood_score' => isset($this->checkinData['mood_score']) && $this->checkinData['mood_score'] !== '' ? (int)$this->checkinData['mood_score'] : null,
                'energy_score' => isset($this->checkinData['energy_score']) && $this->checkinData['energy_score'] !== '' ? (int)$this->checkinData['energy_score'] : null,
                // Alte Felder bereinigen (leere Strings zu null)
                'mood' => !empty($this->checkinData['mood']) ? (int)$this->checkinData['mood'] : null,
                'happiness' => !empty($this->checkinData['happiness']) ? (int)$this->checkinData['happiness'] : null,
            ]);

        if ($this->checkin) {
            $this->checkin->update($checkinData);
        } else {
            $this->checkin = Checkin::create($checkinData);
        }

            $this->dispatch('notice', [
                'type' => 'success',
                'message' => 'Check-in erfolgreich gespeichert!'
            ]);

            $this->loadCheckins();

            // Dispatch Event für Badge-Update
            
            // Modal schließen
            $this->modalShow = false;
    }

    public function saveWithoutClosing()
    {
        $this->validate([
            'checkinData.daily_goal' => 'nullable|string|max:1000',
            'checkinData.goal_category' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\Platform\Core\Enums\GoalCategory::values())],
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

        $checkinData = array_merge($this->checkinData, [
            'user_id' => auth()->id(),
            'date' => $this->selectedDate,
            'mood_score' => isset($this->checkinData['mood_score']) && $this->checkinData['mood_score'] !== '' ? (int)$this->checkinData['mood_score'] : null,
            'energy_score' => isset($this->checkinData['energy_score']) && $this->checkinData['energy_score'] !== '' ? (int)$this->checkinData['energy_score'] : null,
            // Alte Felder bereinigen (leere Strings zu null)
            'mood' => !empty($this->checkinData['mood']) ? (int)$this->checkinData['mood'] : null,
            'happiness' => !empty($this->checkinData['happiness']) ? (int)$this->checkinData['happiness'] : null,
        ]);

        if ($this->checkin) {
            $this->checkin->update($checkinData);
        } else {
            $this->checkin = Checkin::create($checkinData);
        }

        $this->loadCheckins();
    }

    public function addTodo()
    {
        if (empty($this->newTodoTitle)) return;

        if (!$this->checkin) {
            $this->saveWithoutClosing();
        }

        CheckinTodo::create([
            'checkin_id' => $this->checkin->id,
            'title' => $this->newTodoTitle,
            'done' => false
        ]);

        $this->newTodoTitle = '';
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function toggleTodo($todoId)
    {
        $todo = CheckinTodo::find($todoId);
        if ($todo) {
            $todo->update(['done' => !$todo->done]);
            $this->loadCheckinForDate($this->selectedDate);
            
            // Dispatch Event für Badge-Update
        }
    }

    public function deleteTodo($todoId)
    {
        CheckinTodo::find($todoId)?->delete();
        $this->loadCheckinForDate($this->selectedDate);
        
        // Dispatch Event für Badge-Update
    }

    public function postponeTodo($todoId)
    {
        $todo = CheckinTodo::with('checkin')->find($todoId);
        if (!$todo || !$todo->checkin) {
            return;
        }

        // Sicherstellen, dass der Todo dem aktuellen Nutzer gehört
        if ((int)$todo->checkin->user_id !== (int)auth()->id()) {
            return;
        }

        // Nächsten Tag bestimmen basierend auf dem ursprünglichen Check-in Datum
        $nextDate = Carbon::parse($todo->checkin->date)->addDay()->format('Y-m-d');

        // Ziel-Check-in für den nächsten Tag finden oder anlegen
        $targetCheckin = Checkin::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'date' => $nextDate,
            ],
            [
                'daily_goal' => '',
                'goal_category' => null,
                'mood' => null,
                'happiness' => null,
                'mood_score' => null,
                'energy_score' => null,
                'needs_support' => false,
                'hydrated' => false,
                'exercised' => false,
                'slept_well' => false,
                'focused_work' => false,
                'social_time' => false,
                'notes' => '',
            ]
        );

        // Todo auf den nächsten Tag verschieben
        $todo->update([
            'checkin_id' => $targetCheckin->id,
        ]);

        // Aktuelle Liste neu laden
        $this->loadCheckinForDate($this->selectedDate);
    }

    public function getMoodOptions()
    {
        return Checkin::getMoodOptions();
    }

    public function getHappinessOptions()
    {
        return Checkin::getHappinessOptions();
    }

    public function getMoodScoreOptions()
    {
        return Checkin::getMoodScoreOptions();
    }

    public function getEnergyScoreOptions()
    {
        return Checkin::getEnergyScoreOptions();
    }

    public function getGoalCategoryOptions()
    {
        return Checkin::getGoalCategoryOptions();
    }

    // Pomodoro Methods
    public function startPomodoro($type = 'work', $minutes = 25)
    {
        // Stop any active session first
        $this->stopActivePomodoro();
        
        $duration = $minutes * 60; // Convert minutes to seconds
        
        PomodoroSession::create([
            'user_id' => auth()->id(),
            'type' => $type,
            'duration_seconds' => $duration,
            'started_at' => now(),
            'is_active' => true,
        ]);
        
        $this->loadPomodoroStats();
        
        // Dispatch to sidebar for real-time update
        $this->dispatch('pomodoro-started');
    }
    
    public function stopPomodoro()
    {
        $activeSession = PomodoroSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->first();
            
        if ($activeSession) {
            $activeSession->complete();
        }
        $this->loadPomodoroStats();
        
        // Dispatch to sidebar for real-time update
        $this->dispatch('pomodoro-stopped');
    }
    
    public function stopActivePomodoro()
    {
        PomodoroSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->update(['is_active' => false, 'completed_at' => now()]);
        $this->loadPomodoroStats();
        
        // Dispatch to sidebar for real-time update
        $this->dispatch('pomodoro-stopped');
    }
    
    public function loadPomodoroStats()
    {
        $today = now()->startOfDay();
        
        // Get all active sessions for this user
        $activeSessions = PomodoroSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->get();
        
        // Check each active session and complete expired ones
        foreach ($activeSessions as $session) {
            if ($session->is_expired) {
                $session->complete();
                $this->dispatch('timer-expired');
            }
        }
        
        // Get the current active session (after completing expired ones)
        $activeSession = PomodoroSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->first();
        
        $this->pomodoroStats = [
            'today_count' => PomodoroSession::where('user_id', auth()->id())
                ->where('type', 'work')
                ->where('started_at', '>=', $today)
                ->where('is_active', false) // Only count completed sessions
                ->count(),
            'active_session' => $activeSession ? [
                'id' => $activeSession->id,
                'type' => $activeSession->type,
                'duration_seconds' => $activeSession->duration_seconds,
                'started_at' => $activeSession->started_at->toISOString(),
                'is_active' => $activeSession->is_active,
                'remaining_minutes' => $activeSession->remaining_minutes,
                'progress_percentage' => $activeSession->progress_percentage,
            ] : null,
        ];
    }
    
    public function getActivePomodoro()
    {
        $active = PomodoroSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->first();
            
        if ($active && $active->is_expired) {
            $active->complete();
            return null;
        }
        
        return $active;
    }
    
    public function clearPomodoroData()
    {
        PomodoroSession::where('user_id', auth()->id())->delete();
        $this->loadPomodoroStats();
    }

    public function render()
    {
        $this->loadPomodoroStats();
        return view('platform::livewire.modal-checkin');
    }
}
