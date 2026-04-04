<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalMention extends Model
{
    protected $table = 'terminal_mentions';

    protected $fillable = [
        'message_id',
        'user_id',
        'channel_id',
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

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TerminalChannel::class, 'channel_id');
    }
}
