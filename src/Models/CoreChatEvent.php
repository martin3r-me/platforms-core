<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreChatEvent extends Model
{
    protected $table = 'core_chat_events';

    protected $fillable = [
        'core_chat_id','type','payload'
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(CoreChat::class, 'core_chat_id');
    }
}
