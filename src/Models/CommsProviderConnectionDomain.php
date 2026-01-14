<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommsProviderConnectionDomain extends Model
{
    protected $table = 'comms_provider_connection_domains';

    protected $fillable = [
        'comms_provider_connection_id',
        'domain',
        'purpose',
        'is_primary',
        'is_verified',
        'verified_at',
        'last_checked_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'meta' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CommsProviderConnection::class, 'comms_provider_connection_id');
    }
}

