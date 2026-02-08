<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class CoreExtraFieldDefinition extends Model
{
    protected $table = 'core_extra_field_definitions';

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'context_type',
        'context_id',
        'name',
        'label',
        'type',
        'is_required',
        'is_mandatory',
        'is_encrypted',
        'order',
        'options',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_mandatory' => 'boolean',
        'is_encrypted' => 'boolean',
        'order' => 'integer',
        'options' => 'array',
        'context_id' => 'integer',
    ];

    /**
     * Verfügbare Feldtypen
     */
    public const TYPES = [
        'text' => 'Text',
        'number' => 'Zahl',
        'textarea' => 'Mehrzeiliger Text',
        'boolean' => 'Ja/Nein',
        'select' => 'Auswahl',
        'file' => 'Datei',
    ];

    /**
     * Beziehungen
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CoreExtraFieldValue::class, 'definition_id');
    }

    /**
     * Scopes
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('context_type', $type);
    }

    /**
     * Lädt Definitionen für einen bestimmten Kontext
     * - Definitionen für dieses konkrete Objekt (context_id = $contextId)
     * - + Definitionen für den Typ allgemein (context_id = null)
     */
    public function scopeForContext(Builder $query, string $type, ?int $contextId = null): Builder
    {
        return $query->where('context_type', $type)
            ->where(function (Builder $q) use ($contextId) {
                $q->whereNull('context_id');
                if ($contextId !== null) {
                    $q->orWhere('context_id', $contextId);
                }
            });
    }

    /**
     * Prüft ob Definition für alle Objekte dieses Typs gilt
     */
    public function isGlobal(): bool
    {
        return $this->context_id === null;
    }

    /**
     * Gibt den Typ-Namen zurück
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
