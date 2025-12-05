<?php

return [
    'forced_modal' => [
        'start_hour' => (int) env('CHECKIN_FORCED_START_HOUR', 12),
        'max_per_day' => (int) env('CHECKIN_FORCED_MAX_PER_DAY', 2),
        'cooldown_minutes' => (int) env('CHECKIN_FORCED_COOLDOWN_MINUTES', 90),
    ],
];

