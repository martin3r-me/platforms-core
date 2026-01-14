<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommsEmailOutboundMail extends Model
{
    protected $table = 'comms_email_outbound_mails';

    protected $fillable = [
        'thread_id',
        'comms_channel_id',
        'created_by_user_id',
        'from',
        'to',
        'cc',
        'bcc',
        'reply_to',
        'subject',
        'html_body',
        'text_body',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'sent_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommsEmailThread::class, 'thread_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CommsChannel::class, 'comms_channel_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CommsEmailMailAttachment::class, 'outbound_mail_id');
    }
}

