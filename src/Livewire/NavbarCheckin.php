<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\Checkin;

class NavbarCheckin extends Component
{
    public ?int $moodScore = null;
    public ?int $energyScore = null;
    public ?string $dailyGoal = null;
    public bool $hasCheckin = false;
    public int $streak = 0;

    public function mount(): void
    {
        $this->loadState();
    }

    #[On('checkin-saved')]
    public function loadState(): void
    {
        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        $checkin = Checkin::where('user_id', $userId)
            ->whereDate('date', now()->toDateString())
            ->first();

        $this->hasCheckin = (bool) $checkin;
        $this->moodScore = $checkin?->mood_score;
        $this->energyScore = $checkin?->energy_score;
        $this->dailyGoal = $checkin?->daily_goal ?: null;
        $this->streak = Checkin::currentStreak($userId);
    }

    public function setMood(int $score): void
    {
        if ($score < 0 || $score > 4) {
            return;
        }
        $this->upsertCheckin(['mood_score' => $score]);
        $this->moodScore = $score;
    }

    public function setEnergy(int $score): void
    {
        if ($score < 0 || $score > 4) {
            return;
        }
        $this->upsertCheckin(['energy_score' => $score]);
        $this->energyScore = $score;
    }

    protected function upsertCheckin(array $attributes): void
    {
        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        Checkin::updateOrCreate(
            ['user_id' => $userId, 'date' => now()->toDateString()],
            $attributes
        );

        $this->hasCheckin = true;
        $this->streak = Checkin::currentStreak($userId);
        $this->dispatch('checkin-saved');
    }

    public function render()
    {
        return view('platform::livewire.navbar-checkin');
    }
}
