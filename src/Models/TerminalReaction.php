<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalReaction extends Model
{
    protected $table = 'terminal_reactions';

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    // ── Relations ──────────────────────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(TerminalMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
