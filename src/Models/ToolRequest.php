<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model für Tool-Requests
 * 
 * Speichert Anfragen des LLMs nach fehlenden Tools/Funktionen
 */
class ToolRequest extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'description',
        'use_case',
        'suggested_name',
        'category',
        'module',
        'status',
        'developer_notes',
        'assigned_to_user_id',
        'similar_tools',
        'similar_requests',
        'deduplication_key',
        'metadata',
    ];

    protected $casts = [
        'similar_tools' => 'array',
        'similar_requests' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status-Konstanten
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    /**
     * User, der den Request erstellt hat
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team, in dem der Request erstellt wurde
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Entwickler, dem der Request zugewiesen wurde
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Scope: Nur pending Requests
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Nur in_progress Requests
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope: Nur completed Requests
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Nach Modul filtern
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}

