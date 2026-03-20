<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpSession extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'team_id',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Findet oder erstellt eine MCP-Session.
     */
    public static function findOrCreateForSession(string $sessionId, int $userId): self
    {
        return static::firstOrCreate(
            ['id' => $sessionId],
            [
                'user_id' => $userId,
                'last_activity_at' => now(),
            ]
        );
    }

    /**
     * Aktualisiert den Aktivitäts-Timestamp (Sliding Window).
     */
    public function touch(): bool
    {
        $this->last_activity_at = now();
        return $this->save();
    }
}
