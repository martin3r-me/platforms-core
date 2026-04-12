<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleUsageCount extends Model
{
    protected $fillable = [
        'user_id',
        'team_id',
        'module_key',
        'visit_count',
        'last_visited_at',
    ];

    protected function casts(): array
    {
        return [
            'last_visited_at' => 'datetime',
        ];
    }

    public static function track(int $userId, int $teamId, string $moduleKey): void
    {
        $record = self::updateOrCreate(
            ['user_id' => $userId, 'team_id' => $teamId, 'module_key' => $moduleKey],
            ['last_visited_at' => now()]
        );
        $record->increment('visit_count');
    }

    public static function topModules(int $userId, int $teamId, int $limit = 5): array
    {
        return self::where('user_id', $userId)
            ->where('team_id', $teamId)
            ->where('visit_count', '>', 0)
            ->orderByDesc('visit_count')
            ->limit($limit)
            ->pluck('module_key')
            ->toArray();
    }
}
