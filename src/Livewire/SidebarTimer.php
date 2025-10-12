<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Models\PomodoroSession;

class SidebarTimer extends Component
{
    public $pomodoroStats = [];

    public function mount()
    {
        $this->loadPomodoroStats();
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

    public function render()
    {
        $this->loadPomodoroStats();
        return view('platform::livewire.sidebar-timer');
    }
}
