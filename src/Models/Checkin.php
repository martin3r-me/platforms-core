<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Enums\GoalCategory;

class Checkin extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'daily_goal',
        'goal_category',
        'mood',
        'happiness',
        'mood_score',
        'energy_score',
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
        'mood_score' => 'integer',
        'energy_score' => 'integer',
        'goal_category' => GoalCategory::class,
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

        public static function getMoodScoreOptions(): array
        {
            return [
                0 => 'Sehr schlecht',
                1 => 'Schlecht',
                2 => 'Neutral',
                3 => 'Gut',
                4 => 'Ausgezeichnet',
            ];
        }

        public static function getEnergyScoreOptions(): array
        {
            return [
                0 => 'Sehr niedrig',
                1 => 'Niedrig',
                2 => 'Mittel',
                3 => 'Hoch',
                4 => 'Sehr hoch',
            ];
        }

        public static function getGoalCategoryOptions(): array
        {
            return GoalCategory::options();
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
