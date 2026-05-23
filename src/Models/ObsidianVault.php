<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ObsidianVault extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'driver',
        'bucket',
        'region',
        'endpoint',
        'access_key',
        'secret_key',
        'prefix',
        'settings',
        'team_id',
    ];

    protected $casts = [
        'bucket' => 'encrypted',
        'endpoint' => 'encrypted',
        'access_key' => 'encrypted',
        'secret_key' => 'encrypted',
        'settings' => 'array',
    ];

    protected $hidden = [
        'access_key',
        'secret_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Ist dieser Vault einem Team zugeordnet?
     */
    public function isTeamVault(): bool
    {
        return $this->team_id !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $vault) {
            if (empty($vault->slug)) {
                $vault->slug = Str::slug($vault->name);
            }
        });
    }
}
