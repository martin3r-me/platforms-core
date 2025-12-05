<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CheckinReminder extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'forced_count',
        'last_forced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'last_forced_at' => 'datetime',
    ];

    public function scopeForDate(Builder $query, $date): Builder
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $query->whereDate('date', $date);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

