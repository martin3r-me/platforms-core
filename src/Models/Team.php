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

    /**
     * Gibt alle Team-IDs zurück (inkl. diesem Team und allen Kind-Teams rekursiv)
     * 
     * Nützlich für Datawarehouse-Abfragen, die alle Tasks eines Teams inkl. Kind-Teams benötigen.
     *
     * @return array<int>
     */
    public function getAllTeamIdsIncludingChildren(): array
    {
        $teamIds = [$this->id];
        $this->collectChildTeamIds($this, $teamIds);
        return $teamIds;
    }

    /**
     * Rekursiv alle Kind-Team-IDs sammeln
     *
     * @param Team $team
     * @param array<int> $teamIds
     * @return void
     */
    protected function collectChildTeamIds(Team $team, array &$teamIds): void
    {
        $childTeams = $team->childTeams()->get();
        
        foreach ($childTeams as $childTeam) {
            if (!in_array($childTeam->id, $teamIds)) {
                $teamIds[] = $childTeam->id;
                $this->collectChildTeamIds($childTeam, $teamIds);
            }
        }
    }

    /**
     * Prüft, ob dieses Team ein Vorfahre des gegebenen Teams ist
     * (Alias für isParentOf, für Konsistenz mit customerCMS Code)
     */
    public function isAncestorOf(Team $team): bool
    {
        return $this->isParentOf($team);
    }

    /**
     * Gibt alle Nachkommen-Teams zurück (rekursiv)
     */
    public function getAllDescendants()
    {
        $descendants = collect();
        
        foreach ($this->childTeams as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Gibt alle Vorfahren-Teams zurück
     */
    public function getAllAncestors()
    {
        $ancestors = collect();
        $current = $this->parentTeam;
        
        while ($current) {
            $ancestors->push($current);
            $current = $current->parentTeam;
        }
        
        return $ancestors;
    }

    /**
     * Alias für parentTeam() für Konsistenz
     */
    public function parent()
    {
        return $this->parentTeam();
    }

    /**
     * Alias für childTeams() für Konsistenz
     */
    public function children()
    {
        return $this->childTeams();
    }

    /**
     * AI-Modelle, die für dieses Team (als Scope-Team) konfiguriert sind.
     */
    public function coreAiModels(): BelongsToMany
    {
        return $this->belongsToMany(CoreAiModel::class, 'team_core_ai_models', 'scope_team_id', 'core_ai_model_id')
            ->withPivot(['is_enabled', 'created_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Gibt die erlaubten AI-Model-IDs für dieses Team zurück.
     *
     * Geht intern über getRootTeam(). Wenn keine Records in team_core_ai_models
     * für das Root-Team existieren, wird null zurückgegeben (= alle Modelle erlaubt).
     * Ansonsten Array der core_ai_model IDs, die is_enabled=true haben.
     *
     * @return array<int>|null
     */
    public function getAllowedAiModelIds(): ?array
    {
        $rootTeam = $this->getRootTeam();

        $records = TeamCoreAiModel::where('scope_team_id', $rootTeam->id)->get();

        if ($records->isEmpty()) {
            return null; // keine Einschränkung → alle erlaubt
        }

        return $records->where('is_enabled', true)
            ->pluck('core_ai_model_id')
            ->values()
            ->all();
    }
}