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

    /**
     * Prüft ob ein User Zugriff auf dieses Modul hat.
     * Berücksichtigt Root-Scoped vs. Team-Scoped Module.
     *
     * @param User $user Der User, dessen Berechtigung geprüft wird
     * @param Team $baseTeam Das Basis-Team des Users (currentTeamRelation)
     * @return bool True wenn der User Zugriff hat, sonst false
     */
    public function hasAccess(User $user, Team $baseTeam): bool
    {
        $rootTeam = $baseTeam->getRootTeam();
        $rootTeamId = $rootTeam->id;
        $baseTeamId = $baseTeam->id;

        // Für Root-Scoped Module: Rechte aus Root-Team prüfen
        // Für Team-Scoped Module: Rechte aus aktuellem Team prüfen
        if ($this->isRootScoped()) {
            // Zuerst User-Berechtigung prüfen
            $hasPermission = $user->modules()
                ->where('module_id', $this->id)
                ->wherePivot('team_id', $rootTeamId)
                ->wherePivot('enabled', true)
                ->exists();

            // Falls keine User-Berechtigung, Team-Berechtigung prüfen
            if (!$hasPermission) {
                $hasPermission = $rootTeam->modules()
                    ->where('module_id', $this->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            }
        } else {
            // Zuerst User-Berechtigung prüfen
            $hasPermission = $user->modules()
                ->where('module_id', $this->id)
                ->wherePivot('team_id', $baseTeamId)
                ->wherePivot('enabled', true)
                ->exists();

            // Falls keine User-Berechtigung, Team-Berechtigung prüfen
            if (!$hasPermission) {
                $hasPermission = $baseTeam->modules()
                    ->where('module_id', $this->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            }
        }

        return $hasPermission;
    }
}