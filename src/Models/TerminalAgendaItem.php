<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TerminalAgendaItem extends Model
{
    protected $table = 'terminal_agenda_items';

    protected $fillable = [
        'agenda_id',
        'agendable_type',
        'agendable_id',
        'title',
        'notes',
        'date',
        'time_start',
        'time_end',
        'is_done',
        'sort_order',
        'color',
        'created_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'is_done' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->created_by_user_id) && auth()->check()) {
                $model->created_by_user_id = auth()->id();
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(TerminalAgenda::class, 'agenda_id');
    }

    public function agendable(): MorphTo
    {
        return $this->morphTo('agendable');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeBacklog(Builder $query): Builder
    {
        return $query->whereNull('date');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_done', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('time_start')->orderBy('created_at');
    }
}
