<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommsChannelContext extends Model
{
    protected $table = 'comms_channel_contexts';

    protected $fillable = [
        'comms_channel_id',
        'context_model',
        'context_model_id',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CommsChannel::class, 'comms_channel_id');
    }
}
