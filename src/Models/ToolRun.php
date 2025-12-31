<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model für Tool-Runs (Multi-Step-Flows)
 * 
 * Speichert den State von Multi-Step-Tool-Ausführungen für Replay und Resume
 */
class ToolRun extends Model
{
    protected $fillable = [
        'conversation_id',
        'step',
        'tool_name',
        'arguments',
        'status',
        'waiting_for_input',
        'input_options',
        'next_tool',
        'next_tool_args',
        'user_id',
        'team_id',
        'resumed_at',
    ];

    protected $casts = [
        'arguments' => 'array',
        'input_options' => 'array',
        'next_tool_args' => 'array',
        'waiting_for_input' => 'boolean',
        'step' => 'integer',
        'resumed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Status-Konstanten
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_WAITING_INPUT = 'waiting_input';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * User, der den Run erstellt hat
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team, in dem der Run erstellt wurde
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope: Nur pending Runs
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope: Nur waiting_input Runs
     */
    public function scopeWaitingInput($query)
    {
        return $query->where('status', self::STATUS_WAITING_INPUT);
    }

    /**
     * Scope: Nach Conversation-ID filtern
     */
    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Scope: Nach User filtern
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

