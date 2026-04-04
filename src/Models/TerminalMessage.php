<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Symfony\Component\Uid\UuidV7;

class TerminalMessage extends Model
{
    protected $table = 'terminal_messages';

    protected $fillable = [
        'uuid',
        'channel_id',
        'user_id',
        'parent_id',
        'reply_count',
        'last_reply_at',
        'body_html',
        'body_plain',
        'type',
        'has_attachments',
        'has_mentions',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'has_attachments' => 'boolean',
        'has_mentions' => 'boolean',
        'reply_count' => 'integer',
        'last_reply_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TerminalChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(TerminalMention::class, 'message_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(TerminalReaction::class, 'message_id');
    }

    /**
     * File attachments via the ContextFile polymorphic system.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(ContextFile::class, 'context');
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }
}
