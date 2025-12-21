<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $table = 'tags';

    protected $fillable = [
        'name',
        'label',
        'color',
        'team_id',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $tag): void {
            // Auto-generate name (slug) from label if not provided
            if (empty($tag->name) && !empty($tag->label)) {
                $tag->name = Str::slug($tag->label);
            }

            // Ensure name is unique within team scope
            if ($tag->team_id) {
                $baseName = $tag->name;
                $counter = 1;
                while (self::where('name', $tag->name)
                    ->where('team_id', $tag->team_id)
                    ->where('id', '!=', $tag->id ?? 0)
                    ->exists()) {
                    $tag->name = $baseName . '-' . $counter;
                    $counter++;
                }
            } else {
                // Global tags: name must be globally unique
                $baseName = $tag->name;
                $counter = 1;
                while (self::where('name', $tag->name)
                    ->whereNull('team_id')
                    ->where('id', '!=', $tag->id ?? 0)
                    ->exists()) {
                    $tag->name = $baseName . '-' . $counter;
                    $counter++;
                }
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope: Nur Team-Tags (team_id gesetzt)
     */
    public function scopeForTeam($query, ?int $teamId = null)
    {
        if ($teamId === null) {
            return $query->whereNotNull('team_id');
        }
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope: Nur globale Tags (team_id null)
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('team_id');
    }

    /**
     * Scope: Verfügbare Tags für ein Team (Team-Tags + globale Tags)
     */
    public function scopeAvailableForTeam($query, ?int $teamId = null)
    {
        if ($teamId === null) {
            return $query->whereNull('team_id');
        }
        return $query->where(function ($q) use ($teamId) {
            $q->where('team_id', $teamId)
              ->orWhereNull('team_id');
        });
    }

    /**
     * Scope: Tags für einen User (Team-Tags seines Teams + globale Tags)
     */
    public function scopeAvailableForUser($query, User $user)
    {
        $teamId = $user->currentTeamRelation?->id;
        
        if ($teamId === null) {
            return $query->whereNull('team_id');
        }

        return $query->where(function ($q) use ($teamId) {
            $q->where('team_id', $teamId)
              ->orWhereNull('team_id');
        });
    }

    /**
     * Prüft ob Tag ein Team-Tag ist
     */
    public function isTeamTag(): bool
    {
        return $this->team_id !== null;
    }

    /**
     * Prüft ob Tag global ist
     */
    public function isGlobal(): bool
    {
        return $this->team_id === null;
    }
}

