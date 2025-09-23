<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreChatMessage extends Model
{
    protected $table = 'core_chat_messages';

    protected $fillable = [
        'core_chat_id','role','content','meta','tokens_in','tokens_out'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(CoreChat::class, 'core_chat_id');
    }
}
