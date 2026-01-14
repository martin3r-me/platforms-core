<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommsEmailMailAttachment extends Model
{
    protected $table = 'comms_email_mail_attachments';

    protected $fillable = [
        'inbound_mail_id',
        'outbound_mail_id',
        'filename',
        'mime',
        'size',
        'disk',
        'path',
        'cid',
        'inline',
    ];

    protected $casts = [
        'inline' => 'boolean',
    ];

    public function inboundMail(): BelongsTo
    {
        return $this->belongsTo(CommsEmailInboundMail::class, 'inbound_mail_id');
    }

    public function outboundMail(): BelongsTo
    {
        return $this->belongsTo(CommsEmailOutboundMail::class, 'outbound_mail_id');
    }
}

