<?php

namespace Platform\Core\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

trait HasColors
{
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

