<?php

namespace Platform\Core\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Core\Models\Colorable;

trait HasColors
{
    /**
     * Polymorphe Beziehung zu colorables (für Eager Loading)
     *
     * @return MorphMany
     */
    public function contextColors(): MorphMany
    {
        return $this->morphMany(Colorable::class, 'colorable');
    }
    /**
     * Aktuelle Farbe dieser Entity (Team oder persönlich, persönlich hat Priorität)
     */
    public function getColorAttribute(): ?string
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('colorables')) {
                return null;
            }

            $userId = Auth::id();
            
            // Wenn contextColors eager-geladen wurde, verwende diese (vermeidet N+1)
            if ($this->relationLoaded('contextColors')) {
                $contextColors = $this->getRelation('contextColors');
                
                // Zuerst persönliche Farbe prüfen
                if ($userId) {
                    $personalColor = $contextColors->firstWhere('user_id', $userId);
                    if ($personalColor) {
                        return $personalColor->color;
                    }
                }
                
                // Dann Team-Farbe prüfen
                $teamColor = $contextColors->firstWhere('user_id', null);
                if ($teamColor) {
                    return $teamColor->color;
                }
                
                return null;
            }
            
            // Fallback: Direkte Datenbankabfrage (wenn nicht eager-geladen)
            // Zuerst persönliche Farbe prüfen
            if ($userId) {
                $personalColor = DB::table('colorables')
                    ->where('colorable_type', get_class($this))
                    ->where('colorable_id', $this->id)
                    ->where('user_id', $userId)
                    ->value('color');
                
                if ($personalColor) {
                    return $personalColor;
                }
            }

            // Dann Team-Farbe prüfen
            $teamColor = DB::table('colorables')
                ->where('colorable_type', get_class($this))
                ->where('colorable_id', $this->id)
                ->whereNull('user_id')
                ->value('color');

            return $teamColor;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Farbe setzen (Team oder persönlich)
     *
     * @param string $color Hex-Farbe (z.B. #FF5733)
     * @param bool $personal true = persönlich, false = Team-Farbe
     * @return void
     */
    public function setColor(string $color, bool $personal = false): void
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return;
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('colorables')) {
                return;
            }

            $userId = $personal ? Auth::id() : null;
            $teamId = $this->getTeamIdForColoring();

            // Prüfe ob Farbe bereits gesetzt ist
            $exists = DB::table('colorables')
                ->where('colorable_type', get_class($this))
                ->where('colorable_id', $this->id)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                // Update
                DB::table('colorables')
                    ->where('colorable_type', get_class($this))
                    ->where('colorable_id', $this->id)
                    ->where('user_id', $userId)
                    ->update([
                        'color' => $color,
                        'team_id' => $teamId,
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert
                DB::table('colorables')->insert([
                    'color' => $color,
                    'colorable_type' => get_class($this),
                    'colorable_id' => $this->id,
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Ignoriere Fehler (z.B. wenn Tabelle noch nicht existiert)
        }
    }

    /**
     * Farbe entfernen
     *
     * @param bool|null $personal true = nur persönliche, false = nur Team-Farbe, null = beide
     * @return void
     */
    public function removeColor(?bool $personal = null): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('colorables')) {
                return;
            }

            $query = DB::table('colorables')
                ->where('colorable_type', get_class($this))
                ->where('colorable_id', $this->id);

            if ($personal === true) {
                $query->where('user_id', Auth::id());
            } elseif ($personal === false) {
                $query->whereNull('user_id');
            }

            $query->delete();
        } catch (\Exception $e) {
            // Ignoriere Fehler
        }
    }

    /**
     * Prüft ob Entity eine Farbe hat
     *
     * @param bool|null $personal true = nur persönlich, false = nur Team, null = beide
     * @return bool
     */
    public function hasColor(?bool $personal = null): bool
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('colorables')) {
                return false;
            }

            $query = DB::table('colorables')
                ->where('colorable_type', get_class($this))
                ->where('colorable_id', $this->id);

            if ($personal === true) {
                $query->where('user_id', Auth::id());
            } elseif ($personal === false) {
                $query->whereNull('user_id');
            }

            return $query->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Holt Team-ID für Coloring (Root-Team, nicht Child-Team)
     *
     * @return int|null
     */
    protected function getTeamIdForColoring(): ?int
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return null;
            }

            // Hole Root-Team (Parent-Team), nicht Child-Team
            $baseTeam = $user->currentTeamRelation;
            if (!$baseTeam) {
                return null;
            }

            $rootTeam = $baseTeam->getRootTeam();
            return $rootTeam?->id;
        } catch (\Exception $e) {
            // Fallback wenn Datenbank/Auth noch nicht bereit ist (z.B. beim Bootstrap)
            return null;
        }
    }
}

