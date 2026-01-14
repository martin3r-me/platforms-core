<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommsProviderConnection extends Model
{
    use SoftDeletes;

    protected $table = 'comms_provider_connections';

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'provider',
        'name',
        'is_active',
        'credentials',
        'meta',
        'last_verified_at',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'encrypted:array',
        'meta' => 'array',
        'last_verified_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(CommsProviderConnectionDomain::class, 'comms_provider_connection_id');
    }

    /**
     * Resolve an active connection for a team, stored at the root/parent team level.
     */
    public static function forTeamProvider(Team $team, string $provider): ?self
    {
        $rootTeam = method_exists($team, 'getRootTeam') ? $team->getRootTeam() : $team;

        return static::query()
            ->where('team_id', $rootTeam->id)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
    }
}

