<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkin extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'daily_goal',
        'mood',
        'happiness',
        'needs_support',
        'hydrated',
        'exercised',
        'slept_well',
        'focused_work',
        'social_time',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'needs_support' => 'boolean',
        'happiness' => 'integer',
        'hydrated' => 'boolean',
        'exercised' => 'boolean',
        'slept_well' => 'boolean',
        'focused_work' => 'boolean',
        'social_time' => 'boolean',
    ];

        public static function getMoodOptions(): array
        {
            return [
                1 => 'Sehr schlecht',
                2 => 'Schlecht',
                3 => 'Okay',
                4 => 'Gut',
                5 => 'Ausgezeichnet',
            ];
        }

        public static function getHappinessOptions(): array
        {
            return [
                1 => 'Sehr unzufrieden',
                2 => 'Unzufrieden',
                3 => 'Neutral',
                4 => 'Zufrieden',
                5 => 'Sehr zufrieden',
            ];
        }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(CheckinTodo::class);
    }
}
