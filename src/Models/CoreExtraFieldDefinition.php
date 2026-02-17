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
        'visibility_config',
        'verify_by_llm',
        'verify_instructions',
        'auto_fill_source',
        'auto_fill_prompt',
    ];

    /**
     * Available auto-fill sources
     */
    public const AUTO_FILL_SOURCES = [
        'llm' => 'LLM (KI-Analyse)',
        'websearch' => 'Web-Suche',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_mandatory' => 'boolean',
        'is_encrypted' => 'boolean',
        'order' => 'integer',
        'options' => 'array',
        'visibility_config' => 'array',
        'context_id' => 'integer',
        'verify_by_llm' => 'boolean',
    ];

    /**
     * Verfügbare Feldtypen
     */
    public const TYPES = [
        'text' => 'Text',
        'number' => 'Zahl',
        'textarea' => 'Mehrzeiliger Text',
        'boolean' => 'Ja/Nein',
        'select' => 'Auswahl (Freihand)',
        'lookup' => 'Auswahl (Lookup)',
        'file' => 'Datei',
    ];

    /**
     * Beschreibungen und Icons für Feldtypen
     */
    public const TYPE_DESCRIPTIONS = [
        'text' => [
            'icon' => 'heroicon-o-bars-3-bottom-left',
            'description' => 'Einzeiliges Textfeld für kurze Eingaben wie Namen, Titel oder Referenznummern.',
        ],
        'number' => [
            'icon' => 'heroicon-o-calculator',
            'description' => 'Numerisches Feld für Zahlen, Beträge oder Mengenangaben.',
        ],
        'textarea' => [
            'icon' => 'heroicon-o-document-text',
            'description' => 'Mehrzeiliges Textfeld für längere Beschreibungen, Notizen oder Kommentare.',
        ],
        'boolean' => [
            'icon' => 'heroicon-o-check-circle',
            'description' => 'Einfacher Ja/Nein-Schalter für Status-Flags oder Bestätigungen.',
        ],
        'select' => [
            'icon' => 'heroicon-o-list-bullet',
            'description' => 'Dropdown mit frei definierbaren Auswahloptionen. Ideal für Kategorien oder Status-Werte.',
        ],
        'lookup' => [
            'icon' => 'heroicon-o-book-open',
            'description' => 'Dropdown basierend auf einer zentral verwalteten Lookup-Tabelle. Änderungen am Lookup gelten für alle Felder.',
        ],
        'file' => [
            'icon' => 'heroicon-o-paper-clip',
            'description' => 'Datei-Upload für Dokumente, Bilder oder andere Anhänge. Optional mit KI-Verifikation.',
        ],
    ];

    /**
     * Beschreibungen für AutoFill-Quellen
     */
    public const AUTO_FILL_SOURCE_DESCRIPTIONS = [
        'llm' => 'Die KI analysiert vorhandene Daten des Eintrags und füllt das Feld automatisch aus.',
        'websearch' => 'Über eine Web-Suche werden relevante Informationen gefunden und in das Feld eingetragen.',
    ];

    /**
     * Lookup-Beziehung (für lookup-Felder)
     */
    public function lookup(): BelongsTo
    {
        $lookupId = $this->options['lookup_id'] ?? null;
        return $this->belongsTo(CoreLookup::class, 'lookup_id')
            ->withDefault(fn() => CoreLookup::find($lookupId));
    }

    /**
     * Holt die Lookup-Optionen für dieses Feld
     */
    public function getLookupOptionsAttribute(): array
    {
        if ($this->type !== 'lookup') {
            return [];
        }

        $lookupId = $this->options['lookup_id'] ?? null;
        if (!$lookupId) {
            return [];
        }

        $lookup = CoreLookup::find($lookupId);
        return $lookup ? $lookup->getOptionsArray() : [];
    }

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

    /**
     * Prüft ob dieses Feld Sichtbarkeitsbedingungen hat
     */
    public function hasVisibilityConditions(): bool
    {
        $config = $this->visibility_config;

        return !empty($config) && ($config['enabled'] ?? false);
    }

    /**
     * Gibt die Feld-Namen zurück, von denen dieses Feld abhängt
     */
    public function getDependsOnFieldNames(): array
    {
        $config = $this->visibility_config;

        if (empty($config) || !($config['enabled'] ?? false)) {
            return [];
        }

        $fieldNames = [];
        foreach ($config['groups'] ?? [] as $group) {
            foreach ($group['conditions'] ?? [] as $condition) {
                if (!empty($condition['field'])) {
                    $fieldNames[] = $condition['field'];
                }
            }
        }

        return array_unique($fieldNames);
    }
}
