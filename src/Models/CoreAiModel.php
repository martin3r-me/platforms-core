<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\Uid\UuidV7;

class CoreAiModel extends Model
{
    protected $table = 'core_ai_models';

    protected $fillable = [
        'uuid',
        'provider_id',
        'model_id',
        'name',
        'description',
        'category',
        'is_active',
        'is_deprecated',
        'deprecated_at',
        'context_window',
        'max_output_tokens',
        'knowledge_cutoff_date',
        'supports_reasoning_tokens',
        'supports_streaming',
        'supports_function_calling',
        'supports_structured_outputs',
        'supports_temperature',
        'supports_top_p',
        'supports_presence_penalty',
        'supports_frequency_penalty',
        'pricing_currency',
        'price_input_per_1m',
        'price_cached_input_per_1m',
        'price_output_per_1m',
        'modalities',
        'endpoints',
        'features',
        'tools',
        'api_metadata',
        'last_api_check',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deprecated' => 'boolean',
        'deprecated_at' => 'datetime',
        'knowledge_cutoff_date' => 'date',
        'supports_reasoning_tokens' => 'boolean',
        'supports_streaming' => 'boolean',
        'supports_function_calling' => 'boolean',
        'supports_structured_outputs' => 'boolean',
        'supports_temperature' => 'boolean',
        'supports_top_p' => 'boolean',
        'supports_presence_penalty' => 'boolean',
        'supports_frequency_penalty' => 'boolean',
        'modalities' => 'array',
        'endpoints' => 'array',
        'features' => 'array',
        'tools' => 'array',
        'api_metadata' => 'array',
        'last_api_check' => 'datetime',
        'price_input_per_1m' => 'decimal:4',
        'price_cached_input_per_1m' => 'decimal:4',
        'price_output_per_1m' => 'decimal:4',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CoreAiProvider::class, 'provider_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)->where('is_deprecated', false);
    }

    /**
     * Teams, die dieses Modell in ihrer Konfiguration haben.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_core_ai_models', 'core_ai_model_id', 'scope_team_id')
            ->withPivot(['is_enabled', 'created_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Filtert Models auf die für ein Team erlaubten.
     *
     * Wenn für das Root-Team keine Records in team_core_ai_models existieren,
     * wird der Query unverändert zurückgegeben (= alle erlaubt, rückwärtskompatibel).
     * Ansonsten werden nur Modelle zurückgegeben, die is_enabled=true haben.
     */
    public function scopeAllowedForTeam(Builder $query, Team $team): Builder
    {
        $rootTeam = $team->getRootTeam();

        $hasRecords = TeamCoreAiModel::where('scope_team_id', $rootTeam->id)->exists();

        if (!$hasRecords) {
            return $query; // keine Einschränkung
        }

        $enabledIds = TeamCoreAiModel::where('scope_team_id', $rootTeam->id)
            ->where('is_enabled', true)
            ->pluck('core_ai_model_id');

        return $query->whereIn('core_ai_models.id', $enabledIds);
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) UuidV7::generate();
            }
        });
    }
}


