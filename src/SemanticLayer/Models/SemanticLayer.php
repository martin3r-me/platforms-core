<?php

namespace Platform\Core\SemanticLayer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Platform\Core\Models\Team;

/**
 * SemanticLayer — Scope-aware Identitäts-Layer.
 *
 * Ein SemanticLayer ist entweder `global` (BHG Digital Core) oder
 * `team`-scoped (Venture-Extension). Pro Scope+Label existiert max.
 * ein Layer. Mehrere Labels pro Scope ermöglichen Leitbild + Modul-Layer.
 *
 * `enabled_modules` Semantik:
 *   - `[]` (leer) → ungated, greift überall (Leitbild)
 *   - `["mcp"]` → nur MCP-Kontext
 *   - `["mcp","planner"]` → MCP und Planner
 */
class SemanticLayer extends Model
{
    protected $table = 'semantic_layers';

    protected $fillable = [
        'scope_type',
        'scope_id',
        'label',
        'sort_order',
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

    public const LABEL_LEITBILD = 'leitbild';

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

    // ── Multi-Layer Queries ──────────────────────────────────────────

    /**
     * Alle Global-Layer, sortiert nach sort_order.
     */
    public static function globalLayers(): Collection
    {
        return static::where('scope_type', self::SCOPE_GLOBAL)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Alle Team-Layer für ein Team, sortiert nach sort_order.
     */
    public static function forTeamLayers(int $teamId): Collection
    {
        return static::where('scope_type', self::SCOPE_TEAM)
            ->where('scope_id', $teamId)
            ->orderBy('sort_order')
            ->get();
    }

    // ── Compat (single-layer convenience) ────────────────────────────

    /**
     * Liefert das erste Global-Layer (Compat — bevorzuge globalLayers()).
     */
    public static function global(): ?self
    {
        return static::where('scope_type', self::SCOPE_GLOBAL)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Liefert den ersten Team-Scope-Layer (Compat — bevorzuge forTeamLayers()).
     */
    public static function forTeam(int $teamId): ?self
    {
        return static::where('scope_type', self::SCOPE_TEAM)
            ->where('scope_id', $teamId)
            ->orderBy('sort_order')
            ->first();
    }

    // ── Instance Helpers ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PILOT, self::STATUS_PRODUCTION], true);
    }

    /**
     * Ungated = enabled_modules ist leer → greift überall (Leitbild).
     */
    public function isUngated(): bool
    {
        return empty($this->enabled_modules);
    }

    /**
     * Prüft, ob dieser Layer für einen gegebenen Kontext-Key gilt.
     *
     * - Ungated (empty enabled_modules) → true für jeden Key (auch null)
     * - Gated → true nur wenn $key in enabled_modules enthalten
     */
    public function appliesToContext(?string $key): bool
    {
        if ($this->isUngated()) {
            return true;
        }

        if ($key === null || $key === '') {
            return false;
        }

        return in_array($key, $this->enabled_modules ?? [], true);
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
