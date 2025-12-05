<?php

namespace Platform\Core\Repositories;

use Illuminate\Support\Carbon;
use Platform\Core\Models\CheckinReminder;

class CheckinReminderRepository
{
    public function findOrCreateForDate(int $userId, Carbon|string $date): CheckinReminder
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return CheckinReminder::firstOrCreate(
            [
                'user_id' => $userId,
                'date' => $date->toDateString(),
            ],
            [
                'forced_count' => 0,
            ]
        );
    }

    public function markForced(CheckinReminder $reminder): CheckinReminder
    {
        $reminder->forceFill([
            'forced_count' => $reminder->forced_count + 1,
            'last_forced_at' => now(),
        ])->save();

        return $reminder->refresh();
    }
}

