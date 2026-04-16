<?php

namespace Platform\Core\SemanticLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Models\Team;

/**
 * SemanticLayer — Scope-aware Identitäts-Layer.
 *
 * Ein SemanticLayer ist entweder `global` (BHG Digital Core) oder
 * `team`-scoped (Venture-Extension). Pro Scope existiert max. ein Layer.
 * Der Inhalt liegt versioniert in `semantic_layer_versions`.
 */
class SemanticLayer extends Model
{
    protected $table = 'semantic_layers';

    protected $fillable = [
        'scope_type',
        'scope_id',
        'current_version_id',
        'status',
        'enabled_modules',
    ];

    protected $casts = [
        'enabled_modules' => 'array',
    ];

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_TEAM = 'team';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PILOT = 'pilot';
    public const STATUS_PRODUCTION = 'production';
    public const STATUS_ARCHIVED = 'archived';

    public function versions(): HasMany
    {
        return $this->hasMany(SemanticLayerVersion::class, 'semantic_layer_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SemanticLayerVersion::class, 'current_version_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scope_id');
    }

    public function audit(): HasMany
    {
        return $this->hasMany(SemanticLayerAudit::class, 'semantic_layer_id');
    }

    /**
     * Liefert das Global-Layer (wenn vorhanden).
     */
    public static function global(): ?self
    {
        return static::where('scope_type', self::SCOPE_GLOBAL)->first();
    }

    /**
     * Liefert den Team-Scope-Layer für ein Team (wenn vorhanden).
     */
    public static function forTeam(int $teamId): ?self
    {
        return static::where('scope_type', self::SCOPE_TEAM)
            ->where('scope_id', $teamId)
            ->first();
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PILOT, self::STATUS_PRODUCTION], true);
    }

    public function hasModuleEnabled(?string $module): bool
    {
        if ($module === null || $module === '') {
            return false;
        }
        $enabled = $this->enabled_modules ?? [];
        return in_array($module, $enabled, true);
    }
}
