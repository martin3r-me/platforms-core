<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model für Undo/Redo-Operationen
 */
class UndoOperation extends Model
{
    protected $fillable = [
        'operation_type',
        'status',
        'model_version_id',
        'user_id',
        'team_id',
        'trace_id',
        'success',
        'error_message',
        'result_data',
    ];

    protected $casts = [
        'success' => 'boolean',
        'result_data' => 'array',
    ];

    /**
     * Model-Version, die rückgängig gemacht wird
     */
    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }

    /**
     * User, der die Undo-Operation ausführt
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team, in dem die Undo-Operation stattfindet
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope: Nur bestimmte Operation
     */
    public function scopeForType($query, string $type)
    {
        return $query->where('operation_type', $type);
    }

    /**
     * Scope: Nur bestimmter Status
     */
    public function scopeForStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}

