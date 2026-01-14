<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommsEmailThread extends Model
{
    use SoftDeletes;

    protected $table = 'comms_email_threads';

    protected $fillable = [
        'team_id',
        'comms_channel_id',
        'token',
        'subject',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CommsChannel::class, 'comms_channel_id');
    }

    public function inboundMails(): HasMany
    {
        return $this->hasMany(CommsEmailInboundMail::class, 'thread_id');
    }

    public function outboundMails(): HasMany
    {
        return $this->hasMany(CommsEmailOutboundMail::class, 'thread_id');
    }
}

