<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerminalAgendaSlot extends Model
{
    protected $table = 'terminal_agenda_slots';

    protected $fillable = [
        'agenda_id',
        'name',
        'order',
        'color',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    // ── Relations ──────────────────────────────────────────────

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(TerminalAgenda::class, 'agenda_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TerminalAgendaItem::class, 'agenda_slot_id');
    }
}
