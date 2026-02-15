<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Traits\HasContextFileReferences;

class CommsEmailInboundMail extends Model
{
    use HasContextFileReferences;

    protected $table = 'comms_email_inbound_mails';

    protected $fillable = [
        'thread_id',
        'postmark_id',
        'from',
        'to',
        'cc',
        'reply_to',
        'subject',
        'html_body',
        'text_body',
        'headers',
        'attachments_payload',
        'spam_score',
        'received_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'attachments_payload' => 'array',
        'spam_score' => 'decimal:3',
        'received_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommsEmailThread::class, 'thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CommsEmailMailAttachment::class, 'inbound_mail_id');
    }

    /**
     * Get context files via ContextFileReferences (polymorphic).
     */
    public function getContextFilesAttribute(): array
    {
        return $this->getFileReferencesArray();
    }
}

