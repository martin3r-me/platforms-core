<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PomodoroSession extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'duration_seconds',
        'started_at',
        'completed_at',
        'is_active',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRemainingSecondsAttribute(): int
    {
        if (!$this->is_active) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($this->started_at);
        $remaining = $this->duration_seconds - $elapsed;
        return max(0, $remaining);
    }
    
    public function getRemainingMinutesAttribute(): int
    {
        return ceil($this->remaining_seconds / 60);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->remaining_seconds <= 0;
    }

    public function getProgressPercentageAttribute(): float
    {
        if (!$this->is_active) {
            return 100;
        }

        $elapsed = now()->diffInSeconds($this->started_at);
        $progress = ($elapsed / $this->duration_seconds) * 100;
        return min(100, max(0, $progress));
    }

    public function complete(): void
    {
        $this->update([
            'completed_at' => now(),
            'is_active' => false,
        ]);
    }
}
