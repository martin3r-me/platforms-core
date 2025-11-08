<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'url',
        'config',
        'scope_type',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function modulables()
    {
        return $this->morphedByMany(User::class, 'modulable')
            ->withPivot(['role', 'enabled', 'guard'])
            ->withTimestamps();
        // oder analog für Team etc.
    }

    /**
     * Prüft ob dieses Modul root-scoped ist (parent scope_type).
     * Root-scoped Module verwenden immer die Root-Team-ID, unabhängig vom aktuellen Team.
     *
     * @return bool
     */
    public function isRootScoped(): bool
    {
        return $this->scope_type === 'parent';
    }

    /**
     * Prüft ob dieses Modul team-spezifisch ist (single scope_type).
     * Team-spezifische Module verwenden die aktuelle Team-ID.
     *
     * @return bool
     */
    public function isTeamScoped(): bool
    {
        return $this->scope_type === 'single';
    }
}