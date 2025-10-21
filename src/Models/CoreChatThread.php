<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreChatThread extends Model
{
    protected $table = 'core_chat_threads';

    protected $fillable = [
        'core_chat_id',
        'title',
        'status',
        'meta',
        'started_at',
        'finished_at',
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

    public function messages(): HasMany
    {
        return $this->hasMany(CoreChatMessage::class, 'thread_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function start(): void
    {
        $this->update([
            'status' => 'open',
            'started_at' => now(),
        ]);
    }

    public function finish(): void
    {
        $this->update([
            'status' => 'finished',
            'finished_at' => now(),
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'finished_at' => now(),
        ]);
    }
}
