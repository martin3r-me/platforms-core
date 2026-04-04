<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalChannelMember extends Model
{
    protected $table = 'terminal_channel_members';

    protected $fillable = [
        'channel_id',
        'user_id',
        'role',
        'last_read_message_id',
        'last_read_at',
        'notification_preference',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_read_message_id' => 'integer',
        'last_read_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TerminalChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Get unread count for this member based on cursor.
     */
    public function getUnreadCountAttribute(): int
    {
        if ($this->last_read_message_id === null) {
            return $this->channel->messages()
                ->whereNull('parent_id')
                ->count();
        }

        return $this->channel->messages()
            ->where('id', '>', $this->last_read_message_id)
            ->whereNull('parent_id')
            ->count();
    }

    public function markAsRead(?int $messageId = null): void
    {
        $this->update([
            'last_read_message_id' => $messageId ?? $this->channel->last_message_id,
            'last_read_at' => now(),
        ]);
    }
}
