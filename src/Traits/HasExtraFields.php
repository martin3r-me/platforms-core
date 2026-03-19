<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\InheritsExtraFields;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Core\Models\CoreLookup;
use Platform\Core\Services\ExtraFieldConditionEvaluator;

trait HasExtraFields
{
    /**
     * Static stack to guard against circular inheritance references.
     */
    protected static array $extraFieldInheritanceStack = [];

    /**
     * Request-level cache for definitions (keyed by class + id).
     */
    protected static array $extraFieldDefinitionsCache = [];

    /**
     * Alle Extra Field Werte dieser Entity
     */
    public function extraFieldValues(): MorphMany
    {
        return $this->morphMany(CoreExtraFieldValue::class, 'fieldable');
    }

    /**
     * Lädt alle verfügbaren Extra Field Definitionen für diese Entity.
     *
     * 1. Definitionen für dieses konkrete Objekt (context_id = $this->id)
     * 2. + Definitionen für den Typ (context_id = null) → gilt für ALLE dieses Typs
     * 3. + Geerbte Definitionen von Parents (via InheritsExtraFields Contract)
     *
     * Bei Name-Konflikten: Child-Definition gewinnt über Parent-Definition.
     */
    public function getExtraFieldDefinitions(): Collection
    {
        $cacheKey = get_class($this) . ':' . ($this->id ?? 'new');

        if (isset(static::$extraFieldDefinitionsCache[$cacheKey])) {
            return static::$extraFieldDefinitionsCache[$cacheKey];
        }

        $teamId = $this->getTeamIdForExtraFields();
        if (!$teamId) {
            return collect();
        }

        // Own definitions (instance-specific + type-global)
        $ownDefinitions = CoreExtraFieldDefinition::query()
            ->forTeam($teamId)
            ->forContext(get_class($this), $this->id)
            ->orderBy('order')
            ->orderBy('label')
            ->get();

        // Inherited definitions from parents (if contract is implemented)
        if ($this instanceof InheritsExtraFields) {
            $inheritanceKey = get_class($this) . ':' . $this->id;

            // Circular reference guard
            if (in_array($inheritanceKey, static::$extraFieldInheritanceStack)) {
                return static::$extraFieldDefinitionsCache[$cacheKey] = $ownDefinitions;
            }

            static::$extraFieldInheritanceStack[] = $inheritanceKey;

            try {
                $parentDefinitions = collect();

                foreach ($this->extraFieldParents() as $parent) {
                    if (method_exists($parent, 'getExtraFieldDefinitions')) {
                        $parentDefinitions = $parentDefinitions->merge($parent->getExtraFieldDefinitions());
                    }
                }

                // Merge: Child wins on name conflicts
                $ownNames = $ownDefinitions->pluck('name')->toArray();
                $inheritedDefinitions = $parentDefinitions->filter(
                    fn ($def) => !in_array($def->name, $ownNames)
                );

                // Deduplicate inherited definitions by name (first parent wins)
                $inheritedDefinitions = $inheritedDefinitions->unique('name');

                $ownDefinitions = $inheritedDefinitions->merge($ownDefinitions)
                    ->sortBy(['order', 'label'])
                    ->values();
            } finally {
                array_pop(static::$extraFieldInheritanceStack);
            }
        }

        return static::$extraFieldDefinitionsCache[$cacheKey] = $ownDefinitions;
    }

    /**
     * Clears the request-level definitions cache for this instance.
     */
    public function clearExtraFieldDefinitionsCache(): void
    {
        $cacheKey = get_class($this) . ':' . ($this->id ?? 'new');
        unset(static::$extraFieldDefinitionsCache[$cacheKey]);
    }

    /**
     * Gibt den Wert eines Extra Fields zurück
     */
    public function getExtraField(string $name): mixed
    {
        $definition = $this->findExtraFieldDefinition($name);
        if (!$definition) {
            return null;
        }

        $value = $this->extraFieldValues()
            ->where('definition_id', $definition->id)
            ->first();

        return $value?->typed_value;
    }

    /**
     * Setzt den Wert eines Extra Fields
     */
    public function setExtraField(string $name, mixed $value): void
    {
        $definition = $this->findExtraFieldDefinition($name);
        if (!$definition) {
            return;
        }

        $fieldValue = $this->extraFieldValues()
            ->where('definition_id', $definition->id)
            ->first();

        if ($value === null || $value === '') {
            // Wert löschen wenn leer
            if ($fieldValue) {
                $fieldValue->delete();
            }
            return;
        }

        if (!$fieldValue) {
            $fieldValue = new CoreExtraFieldValue([
                'definition_id' => $definition->id,
                'fieldable_type' => $this->getMorphClass(),
                'fieldable_id' => $this->id,
            ]);
        }

        $fieldValue->setTypedValue($value);
        $fieldValue->save();
    }

    /**
     * Gibt alle Extra Fields als Array zurück
     * Format: ['field_name' => value, ...]
     */
    public function getExtraFieldsArray(): array
    {
        $definitions = $this->getExtraFieldDefinitions();
        $values = $this->extraFieldValues()->with('definition')->get()->keyBy('definition_id');

        $result = [];
        foreach ($definitions as $definition) {
            $value = $values->get($definition->id);
            $result[$definition->name] = $value?->typed_value;
        }

        return $result;
    }

    /**
     * Gibt alle Extra Fields mit Labels zurück
     * Format: [['name' => 'field_name', 'label' => 'Field Label', 'value' => value, 'type' => 'text'], ...]
     */
    public function getExtraFieldsWithLabels(): array
    {
        $definitions = $this->getExtraFieldDefinitions();
        $values = $this->extraFieldValues()->with('definition')->get()->keyBy('definition_id');

        $result = [];
        foreach ($definitions as $definition) {
            $value = $values->get($definition->id);

            // A definition is inherited if its context_type differs from this model's class
            $isInherited = $definition->context_type !== get_class($this);

            $fieldData = [
                'id' => $definition->id,
                'name' => $definition->name,
                'label' => $definition->label,
                'description' => $definition->description,
                'type' => $definition->type,
                'value' => $value?->typed_value,
                'is_required' => $definition->is_required,
                'is_mandatory' => $definition->is_mandatory,
                'is_encrypted' => $definition->is_encrypted,
                'is_inherited' => $isInherited,
                'options' => $definition->options,
                'visibility_config' => $definition->visibility_config,
                'verify_by_llm' => $definition->verify_by_llm,
                'verify_instructions' => $definition->verify_instructions,
                'auto_fill_source' => $definition->auto_fill_source,
                'auto_fill_prompt' => $definition->auto_fill_prompt,
            ];

            // Für Lookup-Felder: Verfügbare Optionen hinzufügen
            if ($definition->type === 'lookup' && isset($definition->options['lookup_id'])) {
                $lookup = CoreLookup::with('activeValues')->find($definition->options['lookup_id']);
                if ($lookup) {
                    $fieldData['lookup'] = [
                        'id' => $lookup->id,
                        'name' => $lookup->name,
                        'label' => $lookup->label,
                        'choices' => $lookup->activeValues->map(fn($v) => [
                            'value' => $v->value,
                            'label' => $v->label,
                        ])->toArray(),
                    ];
                }
            }

            // Für Select-Felder: Choices als Array formatieren
            if ($definition->type === 'select' && isset($definition->options['choices'])) {
                $fieldData['choices'] = array_map(fn($c) => [
                    'value' => $c,
                    'label' => $c,
                ], $definition->options['choices']);
            }

            $result[] = $fieldData;
        }

        return $result;
    }

    /**
     * Gibt alle sichtbaren Extra Fields mit Labels zurück
     * Berücksichtigt Visibility-Bedingungen basierend auf aktuellen Werten
     *
     * @param array|null $currentValues Optional: Aktuelle Feldwerte zum Evaluieren (z.B. aus Formular)
     * @return array
     */
    public function getVisibleExtraFieldsWithLabels(?array $currentValues = null): array
    {
        $allFields = $this->getExtraFieldsWithLabels();

        if (empty($allFields)) {
            return [];
        }

        // Build field values map by name
        $fieldValuesByName = [];
        foreach ($allFields as $field) {
            $value = $currentValues[$field['id']] ?? $field['value'];
            $fieldValuesByName[$field['name']] = $value;
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        $visibleFields = [];

        foreach ($allFields as $field) {
            $visibility = $field['visibility_config'] ?? null;

            // If no visibility config or not enabled, field is visible
            if (!$visibility || !($visibility['enabled'] ?? false)) {
                $visibleFields[] = $field;
                continue;
            }

            // Evaluate visibility
            if ($evaluator->evaluate($visibility, $fieldValuesByName)) {
                $visibleFields[] = $field;
            }
        }

        return $visibleFields;
    }

    /**
     * Prüft ob ein Extra Field sichtbar ist basierend auf Bedingungen
     *
     * @param string $name Field name
     * @param array|null $currentValues Optional: Aktuelle Feldwerte zum Evaluieren
     * @return bool
     */
    public function isExtraFieldVisible(string $name, ?array $currentValues = null): bool
    {
        $definition = $this->findExtraFieldDefinition($name);
        if (!$definition) {
            return false;
        }

        $visibility = $definition->visibility_config;

        // If no visibility config or not enabled, field is visible
        if (!$visibility || !($visibility['enabled'] ?? false)) {
            return true;
        }

        // Build field values map
        $allFields = $this->getExtraFieldsWithLabels();
        $fieldValuesByName = [];
        foreach ($allFields as $field) {
            $value = $currentValues[$field['id']] ?? $field['value'];
            $fieldValuesByName[$field['name']] = $value;
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        return $evaluator->evaluate($visibility, $fieldValuesByName);
    }

    /**
     * Sucht eine Extra Field Definition anhand des Namens.
     * Nutzt getExtraFieldDefinitions() um auch geerbte Definitionen zu finden.
     */
    protected function findExtraFieldDefinition(string $name): ?CoreExtraFieldDefinition
    {
        return $this->getExtraFieldDefinitions()->firstWhere('name', $name);
    }

    /**
     * Ermittelt die Team-ID für Extra Fields
     */
    protected function getTeamIdForExtraFields(): ?int
    {
        // Wenn Entity selbst team_id hat, verwende diese
        if (isset($this->team_id) && $this->team_id) {
            return $this->team_id;
        }

        // Fallback: Aktuelles Team des Users
        try {
            $user = Auth::user();
            if (!$user) {
                return null;
            }

            $baseTeam = $user->currentTeamRelation;
            return $baseTeam?->id;
        } catch (\Exception $e) {
            return null;
        }
    }
}
