<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Traits\HasContextFileReferences;

class CommsWhatsAppMessage extends Model
{
    use HasContextFileReferences;

    protected $table = 'comms_whatsapp_messages';

    protected $fillable = [
        'comms_whatsapp_thread_id',
        'direction',
        'meta_message_id',
        'body',
        'message_type',
        'template_name',
        'template_params',
        'status',
        'status_updated_at',
        'sent_by_user_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'meta_payload',
    ];

    protected $casts = [
        'template_params' => 'array',
        'meta_payload' => 'array',
        'status_updated_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommsWhatsAppThread::class, 'comms_whatsapp_thread_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Get attachments via ContextFileReferences.
     */
    public function getAttachmentsAttribute(): array
    {
        return $this->getFileReferencesArray();
    }

    /**
     * Check if this is an inbound message.
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Check if this is an outbound message.
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * Check if the message has media attachments.
     */
    public function hasMedia(): bool
    {
        return in_array($this->message_type, ['image', 'video', 'audio', 'document']);
    }
}
