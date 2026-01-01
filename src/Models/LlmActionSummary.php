<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model für LLM-Action-Zusammenfassungen
 * 
 * Speichert eine Zusammenfassung aller Aktionen, die die LLM in einem Request ausgeführt hat
 */
class LlmActionSummary extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'trace_id',
        'chain_id',
        'user_message',
        'summary',
        'actions',
        'created_models',
        'updated_models',
        'deleted_models',
        'tools_executed',
        'models_created',
        'models_updated',
        'models_deleted',
        'metadata',
    ];

    protected $casts = [
        'actions' => 'array',
        'created_models' => 'array',
        'updated_models' => 'array',
        'deleted_models' => 'array',
        'metadata' => 'array',
        'tools_executed' => 'integer',
        'models_created' => 'integer',
        'models_updated' => 'integer',
        'models_deleted' => 'integer',
    ];

    /**
     * User, der die Aktion ausgelöst hat
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team, in dem die Aktion stattfand
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope: Nach Trace-ID filtern
     */
    public function scopeForTrace($query, string $traceId)
    {
        return $query->where('trace_id', $traceId);
    }
}

