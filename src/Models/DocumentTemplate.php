<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'team_id',
        'key',
        'name',
        'description',
        'blade_view',
        'content',
        'schema',
        'default_data',
        'meta',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'schema' => 'array',
        'default_data' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function scopeForTeam(Builder $query, ?int $teamId): Builder
    {
        return $query->where(function ($q) use ($teamId) {
            $q->whereNull('team_id');
            if ($teamId !== null) {
                $q->orWhere('team_id', $teamId);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystemDefaults(Builder $query): Builder
    {
        return $query->whereNull('team_id');
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    public function getIsSystemDefaultAttribute(): bool
    {
        return $this->team_id === null;
    }

    /**
     * Paper config merged with platform defaults.
     */
    public function getPaperConfigAttribute(): array
    {
        $defaults = config('platform.documents.paper', []);
        $templateMeta = $this->meta ?? [];

        return array_merge($defaults, $templateMeta['paper'] ?? []);
    }
}
