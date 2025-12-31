<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model für Circuit Breaker State
 */
class ToolCircuitBreakerState extends Model
{
    protected $fillable = [
        'service_name',
        'state',
        'failure_count',
        'success_count',
        'last_failure_at',
        'last_success_at',
        'opened_at',
    ];

    protected $casts = [
        'failure_count' => 'integer',
        'success_count' => 'integer',
        'last_failure_at' => 'datetime',
        'last_success_at' => 'datetime',
        'opened_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Prüft ob Circuit geschlossen ist
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Prüft ob Circuit offen ist
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Prüft ob Circuit im Half-Open State ist
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }
}

