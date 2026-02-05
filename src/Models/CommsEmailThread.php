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
        'context_model',
        'context_model_id',
        'last_inbound_from',
        'last_inbound_from_address',
        'last_inbound_at',
        'last_outbound_to',
        'last_outbound_to_address',
        'last_outbound_at',
    ];

    protected $casts = [
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
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

