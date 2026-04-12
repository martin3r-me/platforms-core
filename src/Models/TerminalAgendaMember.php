<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalAgendaMember extends Model
{
    protected $table = 'terminal_agenda_members';

    protected $fillable = [
        'agenda_id',
        'user_id',
        'role',
    ];

    // ── Relations ──────────────────────────────────────────────

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(TerminalAgenda::class, 'agenda_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
