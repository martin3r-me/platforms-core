<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalBookmark extends Model
{
    protected $table = 'terminal_bookmarks';

    protected $fillable = [
        'user_id',
        'message_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TerminalMessage::class, 'message_id');
    }
}
