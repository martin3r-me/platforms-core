<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DocumentFolder extends Model
{
    protected $fillable = [
        'team_id',
        'parent_id',
        'name',
        'description',
        'color',
        'share_token',
        'created_by_user_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Recursive children (for tree building).
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'document_folder_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Full path from root: "Kunden / Rechnungen / 2026"
     */
    public function getPathAttribute(): string
    {
        $parts = [$this->name];
        $current = $this;

        // Walk up max 10 levels to prevent infinite loops
        for ($i = 0; $i < 10 && $current->parent_id; $i++) {
            $current = $current->parent;
            if (!$current) break;
            array_unshift($parts, $current->name);
        }

        return implode(' / ', $parts);
    }

    public function getShareUrlAttribute(): ?string
    {
        if (!$this->share_token) {
            return null;
        }

        return route('core.documents.folder.share', ['token' => $this->share_token]);
    }

    public function ensureShareToken(): string
    {
        if (!$this->share_token) {
            $this->update(['share_token' => Str::random(48)]);
        }

        return $this->share_token;
    }

    /**
     * Collect all descendant folder IDs (for recursive document queries).
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        $this->collectDescendantIds($this->id, $ids);
        return $ids;
    }

    private function collectDescendantIds(int $parentId, array &$ids): void
    {
        $childIds = self::where('parent_id', $parentId)->pluck('id')->toArray();
        foreach ($childIds as $childId) {
            $ids[] = $childId;
            $this->collectDescendantIds($childId, $ids);
        }
    }
}
