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
            'excellent' => 'Ausgezeichnet',
            'good' => 'Gut',
            'okay' => 'Okay',
            'tired' => 'Müde',
            'stressed' => 'Gestresst',
            'frustrated' => 'Frustriert',
        ];
    }

    public static function getHappinessOptions(): array
    {
        return [
            1 => 'Sehr unzufrieden',
            2 => 'Unzufrieden',
            3 => 'Eher unzufrieden',
            4 => 'Neutral',
            5 => 'Eher zufrieden',
            6 => 'Zufrieden',
            7 => 'Sehr zufrieden',
            8 => 'Glücklich',
            9 => 'Sehr glücklich',
            10 => 'Extrem glücklich',
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
