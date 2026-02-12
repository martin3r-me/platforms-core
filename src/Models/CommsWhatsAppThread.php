<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class CommsWhatsAppThread extends Model
{
    use SoftDeletes;

    protected $table = 'comms_whatsapp_threads';

    protected $fillable = [
        'team_id',
        'comms_channel_id',
        'token',
        'remote_phone_number',
        'contact_type',
        'contact_id',
        'context_model',
        'context_model_id',
        'last_inbound_at',
        'last_outbound_at',
        'last_message_preview',
        'is_unread',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
        'is_unread' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CommsChannel::class, 'comms_channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommsWhatsAppMessage::class, 'comms_whatsapp_thread_id');
    }

    public function contact(): MorphTo
    {
        return $this->morphTo('contact', 'contact_type', 'contact_id');
    }

    /**
     * Find or create a thread for a phone number on the given channel.
     */
    public static function findOrCreateForPhone(CommsChannel $channel, string $phone): self
    {
        return static::query()->firstOrCreate(
            [
                'comms_channel_id' => $channel->id,
                'remote_phone_number' => $phone,
            ],
            [
                'team_id' => $channel->team_id,
                'token' => Str::ulid()->toBase32(),
            ]
        );
    }

    /**
     * Get the latest activity timestamp (inbound or outbound).
     */
    public function getLastActivityAtAttribute(): ?\Illuminate\Support\Carbon
    {
        if ($this->last_inbound_at && $this->last_outbound_at) {
            return $this->last_inbound_at->greaterThan($this->last_outbound_at)
                ? $this->last_inbound_at
                : $this->last_outbound_at;
        }
        return $this->last_inbound_at ?? $this->last_outbound_at;
    }

    /**
     * Mark thread as read.
     */
    public function markAsRead(): void
    {
        if ($this->is_unread) {
            $this->update(['is_unread' => false]);
        }
    }

    /**
     * Mark thread as unread.
     */
    public function markAsUnread(): void
    {
        if (!$this->is_unread) {
            $this->update(['is_unread' => true]);
        }
    }
}
