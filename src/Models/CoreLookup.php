<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class CoreLookup extends Model
{
    protected $table = 'core_lookups';

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'name',
        'label',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CoreLookupValue::class, 'lookup_id')->orderBy('order')->orderBy('label');
    }

    public function activeValues(): HasMany
    {
        return $this->hasMany(CoreLookupValue::class, 'lookup_id')
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('label');
    }

    /**
     * Scopes
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Get options array for select fields
     * Returns ['value' => 'label', ...]
     */
    public function getOptionsArray(): array
    {
        return $this->activeValues()
            ->pluck('label', 'value')
            ->toArray();
    }

    /**
     * Get options with full data
     * Returns [['value' => '...', 'label' => '...', 'meta' => [...]], ...]
     */
    public function getOptionsWithMeta(): array
    {
        return $this->activeValues()
            ->get()
            ->map(fn($v) => [
                'value' => $v->value,
                'label' => $v->label,
                'meta' => $v->meta,
            ])
            ->toArray();
    }
}
