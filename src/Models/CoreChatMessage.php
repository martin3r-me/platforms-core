<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreChatMessage extends Model
{
    protected $table = 'core_chat_messages';

    protected $fillable = [
        'core_chat_id',
        'thread_id',
        'role',
        'content',
        'meta',
        'tokens_in',
        'tokens_out',
    ];

    protected $casts = [
        'meta' => 'array',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(CoreChat::class, 'core_chat_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CoreChatThread::class, 'thread_id');
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isSystem(): bool
    {
        return $this->role === 'system';
    }
}
