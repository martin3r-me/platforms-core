<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Database\Factories\CoreContextDateTimeOccurrenceFactory;

/**
 * Expandierter Einzeltermin einer {@see CoreContextDateTime}.
 *
 * Shadow-Table: nicht-wiederkehrende Zeitpunkte haben genau eine Occurrence,
 * RRULE-Zeitpunkte werden in mehrere Occurrences aufgelöst. `is_exception`
 * markiert von der Regel abweichende Einzeltermine.
 */
class CoreContextDateTimeOccurrence extends Model
{
    use HasFactory;

    protected $table = 'core_context_date_time_occurrences';

    protected $fillable = [
        'core_context_date_time_id',
        'starts_at',
        'ends_at',
        'is_exception',
    ];

    protected $casts = [
        'core_context_date_time_id' => 'int',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_exception' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────

    public function dateTime(): BelongsTo
    {
        return $this->belongsTo(CoreContextDateTime::class, 'core_context_date_time_id');
    }

    // ── Scopes ─────────────────────────────────────────────────

    /**
     * Occurrences, die sich mit dem Fenster [$from, $to] überschneiden.
     */
    public function scopeInRange(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->where('starts_at', '<=', $to)
            ->where(function (Builder $q) use ($from) {
                $q->whereNull('ends_at')->where('starts_at', '>=', $from)
                    ->orWhere('ends_at', '>=', $from);
            });
    }

    protected static function newFactory(): Factory
    {
        return CoreContextDateTimeOccurrenceFactory::new();
    }
}
