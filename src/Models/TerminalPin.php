<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalPin extends Model
{
    protected $table = 'terminal_pins';

    protected $fillable = [
        'channel_id',
        'message_id',
        'pinned_by_user_id',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TerminalChannel::class, 'channel_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TerminalMessage::class, 'message_id');
    }

    public function pinnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by_user_id');
    }
}
