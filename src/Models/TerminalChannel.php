<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Component\Uid\UuidV7;

class TerminalChannel extends Model
{
    protected $table = 'terminal_channels';

    protected $fillable = [
        'uuid',
        'team_id',
        'type',
        'name',
        'description',
        'icon',
        'context_type',
        'context_id',
        'participant_hash',
        'message_count',
        'last_message_id',
        'meta',
        'created_by_user_id',
    ];

    protected $casts = [
        'meta' => 'array',
        'message_count' => 'integer',
        'last_message_id' => 'integer',
        'context_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }

            if (empty($model->created_by_user_id) && auth()->check()) {
                $model->created_by_user_id = auth()->id();
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TerminalChannelMember::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TerminalMessage::class, 'channel_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(TerminalMessage::class, 'last_message_id');
    }

    public function context(): MorphTo
    {
        return $this->morphTo('context');
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForContext(Builder $query, string $contextType, int $contextId): Builder
    {
        return $query->where('context_type', $contextType)->where('context_id', $contextId);
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function isChannel(): bool
    {
        return $this->type === 'channel';
    }

    public function isContext(): bool
    {
        return $this->type === 'context';
    }

    public function isDm(): bool
    {
        return $this->type === 'dm';
    }

    /**
     * Generate participant hash from sorted user IDs for DM lookup.
     */
    public static function makeParticipantHash(array $userIds): string
    {
        sort($userIds);

        return hash('sha256', implode(',', $userIds));
    }
}
