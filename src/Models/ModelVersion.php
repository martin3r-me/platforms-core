<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Model für Model-Versionierung
 * 
 * Speichert Snapshots von Models vor/nach Änderungen für Undo/Redo
 */
class ModelVersion extends Model
{
    protected $fillable = [
        'versionable_type',
        'versionable_id',
        'version_number',
        'operation',
        'snapshot_before',
        'snapshot_after',
        'changed_fields',
        'user_id',
        'team_id',
        'tool_name',
        'trace_id',
        'chain_id',
        'tool_execution_id',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'snapshot_before' => 'array',
        'snapshot_after' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
        'version_number' => 'integer',
        'tool_execution_id' => 'integer',
    ];

    /**
     * Polymorphe Beziehung zum Model
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User, der die Änderung verursacht hat
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team, in dem die Änderung stattfand
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Tool-Execution, die die Änderung verursacht hat
     */
    public function toolExecution(): BelongsTo
    {
        return $this->belongsTo(ToolExecution::class);
    }

    /**
     * Scope: Nur bestimmte Operation
     */
    public function scopeForOperation($query, string $operation)
    {
        return $query->where('operation', $operation);
    }

    /**
     * Scope: Nach Tool-Name filtern
     */
    public function scopeForTool($query, string $toolName)
    {
        return $query->where('tool_name', $toolName);
    }

    /**
     * Scope: Nach Trace-ID filtern
     */
    public function scopeForTrace($query, string $traceId)
    {
        return $query->where('trace_id', $traceId);
    }
}

