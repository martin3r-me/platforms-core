<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class CoreAiProvider extends Model
{
    protected $table = 'core_ai_providers';

    protected $fillable = [
        'uuid',
        'key',
        'name',
        'is_active',
        'base_url',
        'catalog_url',
        'default_model_id',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function models(): HasMany
    {
        return $this->hasMany(CoreAiModel::class, 'provider_id');
    }

    public function defaultModel(): BelongsTo
    {
        return $this->belongsTo(CoreAiModel::class, 'default_model_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $provider) {
            if (empty($provider->uuid)) {
                $provider->uuid = (string) UuidV7::generate();
            }
        });
    }
}


