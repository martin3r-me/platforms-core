<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\CheckinTodo;
use Platform\Core\Models\PomodoroSession;
use Carbon\Carbon;

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
    public $pomodoroSession = null;
    public $pomodoroStats = [];

    protected $listeners = ['open-modal-checkin' => 'openModal'];

    public function mount()
    {
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
        $this->loadCheckins();
        $this->loadCheckinForDate($this->selectedDate);
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
                'mood' => '',
                'happiness' => null,
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
                'checkinData.mood' => 'nullable|integer|min:1|max:5',
                'checkinData.happiness' => 'nullable|integer|min:1|max:5',
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
                'mood' => $this->checkinData['mood'] ? (int)$this->checkinData['mood'] : null,
                'happiness' => $this->checkinData['happiness'] ? (int)$this->checkinData['happiness'] : null,
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
            'checkinData.mood' => 'nullable|integer|min:1|max:5',
            'checkinData.happiness' => 'nullable|integer|min:1|max:5',
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
            'mood' => $this->checkinData['mood'] ? (int)$this->checkinData['mood'] : null,
            'happiness' => $this->checkinData['happiness'] ? (int)$this->checkinData['happiness'] : null,
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

    public function getMoodOptions()
    {
        return Checkin::getMoodOptions();
    }

    public function getHappinessOptions()
    {
        return Checkin::getHappinessOptions();
    }

    // Pomodoro Methods
    public function startPomodoro($type = 'work')
    {
        // Stop any active session first
        $this->stopActivePomodoro();
        
        $duration = $type === 'work' ? 25 * 60 : 5 * 60; // 25 min work, 5 min break
        
        $this->pomodoroSession = PomodoroSession::create([
            'user_id' => auth()->id(),
            'type' => $type,
            'duration_seconds' => $duration,
            'started_at' => now(),
            'is_active' => true,
        ]);
        
        $this->loadPomodoroStats();
    }
    
    public function stopPomodoro()
    {
        if ($this->pomodoroSession && $this->pomodoroSession->is_active) {
            $this->pomodoroSession->complete();
            $this->pomodoroSession = null;
        }
    }
    
    public function stopActivePomodoro()
    {
        PomodoroSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->update(['is_active' => false, 'completed_at' => now()]);
    }
    
    public function loadPomodoroStats()
    {
        $today = now()->startOfDay();
        $activeSession = PomodoroSession::where('user_id', auth()->id())
            ->where('is_active', true)
            ->first();
        
        // Check if session is expired and auto-complete it
        if ($activeSession && $activeSession->is_expired) {
            $activeSession->complete();
            $this->dispatch('timer-expired');
            $activeSession = null;
        }
        
        $this->pomodoroStats = [
            'today_count' => PomodoroSession::where('user_id', auth()->id())
                ->where('type', 'work')
                ->where('started_at', '>=', $today)
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
