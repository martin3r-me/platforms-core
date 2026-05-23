<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToolRegistryEntry extends Model
{
    protected $table = 'tool_registry_entries';

    protected $fillable = [
        'name',
        'kind',
        'module',
        'namespace',
        'tier',
        'intent',
        'description',
        'required_params',
        'optional_params',
        'cost_class',
        'cost_per_call_eur',
        'read_only',
        'deprecated',
        'successor_name',
        'usage_7d',
        'usage_30d',
        'usage_90d',
    ];

    protected $casts = [
        'required_params' => 'array',
        'optional_params' => 'array',
        'read_only' => 'boolean',
        'deprecated' => 'boolean',
        'cost_per_call_eur' => 'decimal:4',
        'usage_7d' => 'integer',
        'usage_30d' => 'integer',
        'usage_90d' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations

    public function tags(): HasMany
    {
        return $this->hasMany(ToolRegistryTag::class);
    }

    public function requires(): HasMany
    {
        return $this->hasMany(ToolRegistryRequires::class);
    }

    // Scopes

    public function scopeAvailable($query)
    {
        return $query->where('deprecated', false);
    }

    public function scopeInTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeInNamespace($query, string $namespace)
    {
        return $query->where('namespace', $namespace);
    }

    public function scopeInModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Einfaches Scoring gegen eine Suchanfrage.
     */
    public function matchesQuery(string $query): int
    {
        $score = 0;
        $queryLower = mb_strtolower($query);

        // Name-Match
        $nameLower = mb_strtolower($this->name ?? '');
        if ($nameLower === $queryLower) {
            $score += 10;
        } elseif (str_contains($nameLower, $queryLower)) {
            $score += 6;
        }

        // Intent-Match
        if (str_contains(mb_strtolower($this->intent ?? ''), $queryLower)) {
            $score += 5;
        }

        // Description-Match
        if (str_contains(mb_strtolower($this->description ?? ''), $queryLower)) {
            $score += 2;
        }

        // Tag-Match
        if ($this->relationLoaded('tags')) {
            foreach ($this->tags as $tag) {
                if (str_contains(mb_strtolower($tag->tag), $queryLower)) {
                    $score += 8;
                    break;
                }
            }
        }

        return $score;
    }
}
