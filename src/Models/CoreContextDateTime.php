<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Database\Factories\CoreContextDateTimeFactory;
use Platform\Core\Enums\ContextDateTimeKind;

/**
 * Ein kontextgebundener Datums-/Zeitpunkt.
 *
 * Hängt polymorph an einer beliebigen Entity (context_type/context_id) und
 * beschreibt einen einzelnen Zeitpunkt oder – via `recurrence_rrule` – eine
 * Wiederholung. Zeitpunkte werden stets in UTC gespeichert; `timezone` hält die
 * IANA-Zone für Anzeige und RRULE-Expansion. Die aufgelösten Einzeltermine
 * liegen in {@see CoreContextDateTimeOccurrence}.
 */
class CoreContextDateTime extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'core_context_date_times';

    protected $fillable = [
        'context_type',
        'context_id',
        'kind',
        'label',
        'starts_at',
        'ends_at',
        'timezone',
        'recurrence_rrule',
        'is_all_day',
        'source',
        'calendar_sync_enabled',
        'icalendar_uid',
        'last_synced_at',
        'team_id',
    ];

    protected $casts = [
        'context_id' => 'int',
        'team_id' => 'int',
        'kind' => ContextDateTimeKind::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_all_day' => 'boolean',
        'calendar_sync_enabled' => 'boolean',
    ];

    // ── Relations ──────────────────────────────────────────────

    public function context(): MorphTo
    {
        return $this->morphTo('context');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(CoreContextDateTimeOccurrence::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForContext(Builder $query, string $type, int $id): Builder
    {
        return $query->where('context_type', $type)->where('context_id', $id);
    }

    public function scopeOfKind(Builder $query, ContextDateTimeKind|string $kind): Builder
    {
        return $query->where('kind', $kind instanceof ContextDateTimeKind ? $kind->value : $kind);
    }

    /**
     * Zeitpunkte, die sich mit dem Fenster [$from, $to] überschneiden.
     */
    public function scopeInRange(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->where('starts_at', '<=', $to)
            ->where(function (Builder $q) use ($from) {
                $q->whereNull('ends_at')->where('starts_at', '>=', $from)
                    ->orWhere('ends_at', '>=', $from);
            });
    }

    // ── Helpers ────────────────────────────────────────────────

    public function isRecurring(): bool
    {
        return filled($this->recurrence_rrule);
    }

    protected static function newFactory(): Factory
    {
        return CoreContextDateTimeFactory::new();
    }
}
