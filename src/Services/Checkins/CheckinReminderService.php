<?php

namespace Platform\Core\Services\Checkins;

use Illuminate\Support\Carbon;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\User;
use Platform\Core\Repositories\CheckinReminderRepository;

class CheckinReminderService
{
    public function __construct(
        protected CheckinReminderRepository $reminders
    ) {
    }

    public function shouldForceModal(User $user): bool
    {
        if ($this->userHasCheckinToday($user->id)) {
            return false;
        }

        $now = Carbon::now();

        if ($now->hour < $this->forcedStartHour()) {
            return false;
        }

        $reminder = $this->reminders->findOrCreateForDate($user->id, $now);

        if ($reminder->forced_count >= $this->maxForcedPerDay()) {
            return false;
        }

        if ($reminder->last_forced_at && $reminder->last_forced_at->diffInMinutes($now) < $this->cooldownMinutes()) {
            return false;
        }

        $this->reminders->markForced($reminder);

        return true;
    }

    protected function userHasCheckinToday(int $userId): bool
    {
        return Checkin::where('user_id', $userId)
            ->whereDate('date', Carbon::now())
            ->exists();
    }

    protected function forcedStartHour(): int
    {
        return (int) config('checkins.forced_modal.start_hour', 12);
    }

    protected function maxForcedPerDay(): int
    {
        return (int) config('checkins.forced_modal.max_per_day', 2);
    }

    protected function cooldownMinutes(): int
    {
        return (int) config('checkins.forced_modal.cooldown_minutes', 60);
    }
}

