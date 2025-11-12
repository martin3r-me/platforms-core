<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class TeamUserLastModule extends Model
{
    protected $table = 'team_user_last_modules';

    protected $fillable = [
        'user_id',
        'team_id',
        'module_key',
    ];

    /**
     * Beziehung zum User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Beziehung zum Team
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Speichert oder aktualisiert das zuletzt verwendete Modul für einen User/Team
     */
    public static function updateLastModule(int $userId, int $teamId, ?string $moduleKey): void
    {
        self::updateOrCreate(
            [
                'user_id' => $userId,
                'team_id' => $teamId,
            ],
            [
                'module_key' => $moduleKey,
            ]
        );
    }

    /**
     * Holt das zuletzt verwendete Modul für einen User/Team
     */
    public static function getLastModule(int $userId, int $teamId): ?string
    {
        $record = self::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->first();

        return $record?->module_key;
    }

}

