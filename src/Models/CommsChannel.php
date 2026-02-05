<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommsChannel extends Model
{
    use SoftDeletes;

    protected $table = 'comms_channels';

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'comms_provider_connection_id',
        'type',
        'provider',
        'name',
        'sender_identifier',
        'visibility',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(CommsProviderConnection::class, 'comms_provider_connection_id');
    }

    public function contexts(): HasMany
    {
        return $this->hasMany(CommsChannelContext::class, 'comms_channel_id');
    }
}

