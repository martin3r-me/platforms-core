<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\CheckinTodo;
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
            'checkinData.mood' => 'nullable|in:excellent,good,okay,tired,stressed,frustrated',
            'checkinData.happiness' => 'nullable|integer|min:1|max:10',
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
        $this->dispatch('checkin-updated');
    }

    public function addTodo()
    {
        if (empty($this->newTodoTitle)) return;

        if (!$this->checkin) {
            $this->save();
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
            $this->dispatch('checkin-updated');
        }
    }

    public function deleteTodo($todoId)
    {
        CheckinTodo::find($todoId)?->delete();
        $this->loadCheckinForDate($this->selectedDate);
        
        // Dispatch Event für Badge-Update
        $this->dispatch('checkin-updated');
    }

    public function getMoodOptions()
    {
        return Checkin::getMoodOptions();
    }

    public function getHappinessOptions()
    {
        return Checkin::getHappinessOptions();
    }

    public function render()
    {
        return view('platform::livewire.modal-checkin');
    }
}
