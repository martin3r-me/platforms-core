<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\Uid\UuidV7;

class CoreEntityLink extends Model
{
    protected $table = 'core_entity_links';

    protected $fillable = [
        'uuid',
        'team_id',
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'link_type',
        'sort_order',
        'metadata',
        'created_by_user_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sort_order' => 'integer',
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

    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    public function target(): MorphTo
    {
        return $this->morphTo('target');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Sucht Links, bei denen die Entity entweder source ODER target ist.
     */
    public function scopeForEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where(function (Builder $q) use ($type, $id) {
            $q->where(function (Builder $q2) use ($type, $id) {
                $q2->where('source_type', $type)->where('source_id', $id);
            })->orWhere(function (Builder $q2) use ($type, $id) {
                $q2->where('target_type', $type)->where('target_id', $id);
            });
        });
    }

    public function scopeOfLinkType(Builder $query, string $type): Builder
    {
        return $query->where('link_type', $type);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /**
     * Gibt die "andere Seite" des Links zurück, gesehen von der übergebenen Entity.
     *
     * @return array{type: string, id: int}
     */
    public function getLinkedEntity(string $forType, int $forId): array
    {
        if ($this->source_type === $forType && (int) $this->source_id === $forId) {
            return ['type' => $this->target_type, 'id' => (int) $this->target_id];
        }

        return ['type' => $this->source_type, 'id' => (int) $this->source_id];
    }
}
