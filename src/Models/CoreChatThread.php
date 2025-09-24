<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreChatThread extends Model
{
    protected $table = 'core_chat_threads';

    protected $fillable = [
        'core_chat_id','title','status','meta','started_at','finished_at'
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(CoreChat::class, 'core_chat_id');
    }
}


