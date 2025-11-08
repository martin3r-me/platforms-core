<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    use HasFactory;

    /**
     * Die Tabelle, falls abweichend vom Standard.
     *
     * @var string
     */
    protected $table = 'teams';

    /**
     * Massenzuweisbare Felder.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'parent_team_id',
        'personal_team',
        'mollie_customer_id',
        'mollie_payment_method_id',
        'payment_method_last_4',
        'payment_method_brand',
        'payment_method_expires_at',
    ];

    /**
     * Typkonvertierungen.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_team' => 'boolean',
        'payment_method_expires_at' => 'datetime',
    ];

    /**
     * Mitglieder dieses Teams.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Einladungen zu diesem Team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function modules()
    {
        return $this->morphToMany(Module::class, 'modulable')
            ->withPivot(['role', 'enabled', 'guard'])
            ->withTimestamps();
    }

    /**
     * Parent-Team Beziehung (wenn dieses Team ein Kind-Team ist).
     */
    public function parentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }

    /**
     * Kind-Teams Beziehung (wenn dieses Team ein Parent-Team ist).
     */
    public function childTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'parent_team_id');
    }

    /**
     * Gibt das Root-Team zurück (rekursiv nach oben).
     * Wenn dieses Team bereits ein Root-Team ist, wird es selbst zurückgegeben.
     *
     * @return self
     */
    public function getRootTeam(): self
    {
        if ($this->isRootTeam()) {
            return $this;
        }

        $parent = $this->parentTeam;
        if (!$parent) {
            return $this;
        }

        return $parent->getRootTeam();
    }

    /**
     * Prüft ob dieses Team ein Root-Team ist (kein Parent-Team hat).
     *
     * @return bool
     */
    public function isRootTeam(): bool
    {
        return $this->parent_team_id === null;
    }

    /**
     * Prüft ob dieses Team ein Kind-Team von $team ist.
     *
     * @param Team $team
     * @return bool
     */
    public function isChildOf(Team $team): bool
    {
        $current = $this;
        while ($current->parent_team_id !== null) {
            if ($current->parent_team_id === $team->id) {
                return true;
            }
            $current = $current->parentTeam;
            if (!$current) {
                break;
            }
        }
        return false;
    }

    /**
     * Prüft ob dieses Team ein Parent-Team von $team ist.
     *
     * @param Team $team
     * @return bool
     */
    public function isParentOf(Team $team): bool
    {
        return $team->isChildOf($this);
    }
}