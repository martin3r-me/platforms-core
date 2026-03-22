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

    protected static function booted(): void
    {
        static::creating(function (self $vault) {
            if (empty($vault->slug)) {
                $vault->slug = Str::slug($vault->name);
            }
        });
    }
}
