<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalReminder extends Model
{
    protected $table = 'terminal_reminders';

    protected $fillable = [
        'user_id',
        'message_id',
        'remind_at',
        'reminded',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'reminded' => 'boolean',
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
