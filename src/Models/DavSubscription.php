<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Ein abonnierbarer DAV-Zugang (CardDAV-Adressbuch, CalDAV-Kalender, ...).
 *
 * `module` + `type` bestimmen das zuständige {@see \Platform\Core\Contracts\DavModuleInterface};
 * `resource_id` verweist auf die konkrete Ressource des Moduls (z. B. eine
 * Kontaktliste). Das {@see $secret} ist das Basic-Auth-Passwort des Clients und
 * bindet die Sichtbarkeit an {@see $user}.
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class DavSubscription extends Model
{
    protected $table = 'dav_subscriptions';

    protected $fillable = [
        'user_id',
        'team_id',
        'module',
        'type',
        'resource_id',
        'secret',
        'name',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->secret)) {
                do {
                    $secret = Str::random(64);
                } while (self::where('secret', $secret)->exists());

                $model->secret = $secret;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Aktiv = nicht widerrufen und nicht abgelaufen.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
