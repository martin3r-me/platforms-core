<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Platform\Core\Models\Module;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function fullname(): Attribute
    {
        return Attribute::get(fn () => trim($this->name . ' ' . ($this->lastname ?? '')));
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Basis-Relationship für das aktuelle Team (für DB-Zugriffe).
     * Wird intern von currentTeam Attribute verwendet.
     */
    public function currentTeamRelation()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Dynamisches currentTeam Attribute - gibt je nach Modul das richtige Team zurück.
     * 
     * - Für root-scoped Module (scope_type = 'parent'): Gibt das Root-Team zurück
     * - Für team-spezifische Module (scope_type = 'single'): Gibt das aktuelle Team zurück
     * 
     * @return Team|null
     */
    public function currentTeam(): Attribute
    {
        return Attribute::make(
            get: function () {
                $baseTeam = $this->currentTeamRelation;
                if (!$baseTeam) {
                    return null;
                }

                // Versuche Modul-Key aus aktueller Route zu extrahieren
                $moduleKey = request()->segment(1);
                
                // Wenn kein Modul-Key oder leer, verwende Basis-Team
                if (empty($moduleKey)) {
                    return $baseTeam;
                }

                // Modul finden und Scope-Type prüfen
                $module = Module::where('key', $moduleKey)->first();
                
                // Wenn Modul nicht gefunden oder team-spezifisch, verwende Basis-Team
                if (!$module || $module->isTeamScoped()) {
                    return $baseTeam;
                }

                // Root-scoped Modul: Immer Root-Team zurückgeben
                if ($module->isRootScoped()) {
                    return $baseTeam->getRootTeam();
                }

                // Fallback: Basis-Team
                return $baseTeam;
            }
        );
    }

    public function modules()
    {
        return $this->morphToMany(Module::class, 'modulable')
            ->withPivot(['role', 'enabled', 'guard'])
            ->withTimestamps();
    }
}