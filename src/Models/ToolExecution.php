<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model für Tool-Execution-Tracking
 * 
 * Speichert alle Tool-Ausführungen für Observability und Analytics
 */
class ToolExecution extends Model
{
    protected $fillable = [
        'tool_name',
        'user_id',
        'team_id',
        'arguments',
        'success',
        'error_code',
        'error_message',
        'error_trace',
        'duration_ms',
        'memory_usage_bytes',
        'token_usage_input',
        'token_usage_output',
        'result_data',
        'result_message',
        'trace_id',
        'chain_id',
        'chain_position',
    ];

    protected $casts = [
        'arguments' => 'array',
        'result_data' => 'array',
        'success' => 'boolean',
        'duration_ms' => 'integer',
        'memory_usage_bytes' => 'integer',
        'token_usage_input' => 'integer',
        'token_usage_output' => 'integer',
        'chain_position' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User, der das Tool ausgeführt hat
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team, in dem das Tool ausgeführt wurde
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope: Nur erfolgreiche Ausführungen
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope: Nur fehlgeschlagene Ausführungen
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope: Nach Tool-Name filtern
     */
    public function scopeForTool($query, string $toolName)
    {
        return $query->where('tool_name', $toolName);
    }

    /**
     * Scope: Nach User filtern
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Nach Team filtern
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope: Nach Zeitraum filtern
     */
    public function scopeInTimeRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Nach Chain-ID filtern
     */
    public function scopeForChain($query, string $chainId)
    {
        return $query->where('chain_id', $chainId);
    }
}

