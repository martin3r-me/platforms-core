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

    public function render()
    {
        $this->loadPomodoroStats();
        return view('platform::livewire.sidebar-timer');
    }
}
