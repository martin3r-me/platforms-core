<?php

namespace Platform\Core\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Core\Jobs\VerifyExtraFieldValueJob;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Core\Services\ExtraFieldConditionEvaluator;

/**
 * Trait for Livewire components that need to manage extra field values.
 *
 * Usage:
 * 1. Use this trait in your Livewire component
 * 2. Call loadExtraFieldValues($model) in mount()
 * 3. Call saveExtraFieldValues() in save()
 * 4. Use isExtraFieldsDirty() to extend your isDirty computed property
 * 5. Add <x-extra-fields-form :definitions="$this->extraFieldDefinitions" /> in your view
 */
trait WithExtraFields
{
    /**
     * The extra field values indexed by definition ID
     */
    public array $extraFieldValues = [];

    /**
     * Original values for dirty checking
     */
    protected array $originalExtraFieldValues = [];

    /**
     * The extra field definitions
     */
    public array $extraFieldDefinitions = [];

    /**
     * Extra field metadata (verification status, etc.) indexed by definition ID
     */
    public array $extraFieldMeta = [];

    /**
     * The model that has extra fields
     */
    protected ?Model $extraFieldsModel = null;

    /**
     * The parent model for inherited definitions (e.g., Board for Ticket)
     */
    protected ?Model $extraFieldsParentModel = null;

    /**
     * Active file picker field ID
     */
    public ?int $activeExtraFieldFilePickerId = null;

    /**
     * Whether the active file picker allows multiple files
     */
    public bool $activeExtraFieldFilePickerMultiple = false;

    /**
     * Load extra field values from a model
     */
    public function loadExtraFieldValues(Model $model): void
    {
        $this->extraFieldsModel = $model;

        // Load definitions and mark as own (not inherited)
        $definitions = $model->getExtraFieldsWithLabels();
        $this->extraFieldDefinitions = array_map(function ($def) {
            $def['is_inherited'] = false;
            return $def;
        }, $definitions);

        // Load field values with verification metadata
        $fieldValues = $model->extraFieldValues()->with('definition')->get()->keyBy('definition_id');

        // Initialize values array with current values
        $this->extraFieldValues = [];
        $this->extraFieldMeta = [];

        foreach ($this->extraFieldDefinitions as $field) {
            $value = $field['value'];
            $fieldValue = $fieldValues->get($field['id']);

            // Für Mehrfachauswahl muss der Wert ein Array sein
            if ($field['type'] === 'select' && ($field['options']['multiple'] ?? false)) {
                if ($value === null) {
                    $value = [];
                } elseif (!is_array($value)) {
                    $value = [$value];
                }
            }

            // Für File-Felder: Array normalisieren
            if ($field['type'] === 'file' && ($field['options']['multiple'] ?? false)) {
                if ($value === null) {
                    $value = [];
                } elseif (!is_array($value)) {
                    $value = [$value];
                }
            }

            $this->extraFieldValues[$field['id']] = $value;

            // Load verification and auto-fill metadata
            if ($fieldValue) {
                $this->extraFieldMeta[$field['id']] = [
                    'verification_status' => $fieldValue->verification_status,
                    'verification_result' => $fieldValue->verification_result,
                    'verified_at' => $fieldValue->verified_at,
                    'auto_filled' => $fieldValue->auto_filled,
                    'auto_filled_at' => $fieldValue->auto_filled_at,
                ];
            }
        }

        // Store original for dirty checking
        $this->originalExtraFieldValues = $this->extraFieldValues;
    }

    /**
     * Load extra field values with inherited definitions from parent model.
     * Also loads own definitions specific to the model itself.
     *
     * @param Model $model The model to load values for (e.g., Ticket)
     * @param Model $parentModel The parent model with definitions (e.g., Board)
     */
    public function loadExtraFieldValuesFromParent(Model $model, Model $parentModel): void
    {
        $this->extraFieldsModel = $model;
        $this->extraFieldsParentModel = $parentModel;

        // Load definitions from PARENT model
        $parentDefinitions = $parentModel->getExtraFieldsWithLabels();

        // Load definitions specific to THIS model (own definitions)
        $ownDefinitions = $model->getExtraFieldsWithLabels();

        // Merge: Parent definitions first, then own definitions
        // Mark each definition with its source for UI distinction
        $parentDefinitions = array_map(function ($def) {
            $def['is_inherited'] = true;
            return $def;
        }, $parentDefinitions);

        $ownDefinitions = array_map(function ($def) {
            $def['is_inherited'] = false;
            return $def;
        }, $ownDefinitions);

        $this->extraFieldDefinitions = array_merge($parentDefinitions, $ownDefinitions);

        // Load VALUES from current model
        $values = $model->extraFieldValues()->with('definition')->get()->keyBy('definition_id');

        // Initialize values array
        $this->extraFieldValues = [];
        $this->extraFieldMeta = [];

        foreach ($this->extraFieldDefinitions as $field) {
            $fieldValue = $values->get($field['id']);
            $value = $fieldValue?->typed_value;

            // Für Mehrfachauswahl muss der Wert ein Array sein
            if ($field['type'] === 'select' && ($field['options']['multiple'] ?? false)) {
                if ($value === null) {
                    $value = [];
                } elseif (!is_array($value)) {
                    $value = [$value];
                }
            }

            // Für File-Felder: Array normalisieren
            if ($field['type'] === 'file' && ($field['options']['multiple'] ?? false)) {
                if ($value === null) {
                    $value = [];
                } elseif (!is_array($value)) {
                    $value = [$value];
                }
            }

            $this->extraFieldValues[$field['id']] = $value;

            // Load verification and auto-fill metadata
            if ($fieldValue) {
                $this->extraFieldMeta[$field['id']] = [
                    'verification_status' => $fieldValue->verification_status,
                    'verification_result' => $fieldValue->verification_result,
                    'verified_at' => $fieldValue->verified_at,
                    'auto_filled' => $fieldValue->auto_filled,
                    'auto_filled_at' => $fieldValue->auto_filled_at,
                ];
            }
        }

        // Store original for dirty checking
        $this->originalExtraFieldValues = $this->extraFieldValues;
    }

    /**
     * Refresh extra field definitions (e.g., after definitions were changed)
     */
    public function refreshExtraFieldDefinitions(): void
    {
        if (!$this->extraFieldsModel) {
            return;
        }

        // If we have a parent model, reload both sources
        if ($this->extraFieldsParentModel) {
            $parentDefinitions = $this->extraFieldsParentModel->getExtraFieldsWithLabels();
            $ownDefinitions = $this->extraFieldsModel->getExtraFieldsWithLabels();

            $parentDefinitions = array_map(function ($def) {
                $def['is_inherited'] = true;
                return $def;
            }, $parentDefinitions);

            $ownDefinitions = array_map(function ($def) {
                $def['is_inherited'] = false;
                return $def;
            }, $ownDefinitions);

            $this->extraFieldDefinitions = array_merge($parentDefinitions, $ownDefinitions);
        } else {
            // No parent - just load own definitions
            $this->extraFieldDefinitions = $this->extraFieldsModel->getExtraFieldsWithLabels();
        }

        // Add any new definitions to values array
        foreach ($this->extraFieldDefinitions as $field) {
            if (!array_key_exists($field['id'], $this->extraFieldValues)) {
                $this->extraFieldValues[$field['id']] = $field['value'] ?? null;
                $this->originalExtraFieldValues[$field['id']] = $field['value'] ?? null;
            }
        }
    }

    /**
     * Save extra field values to the model
     *
     * @param Model|null $model Optional model to save to (useful when extraFieldsModel is not hydrated)
     */
    public function saveExtraFieldValues(?Model $model = null): void
    {
        $model = $model ?? $this->extraFieldsModel;

        if (!$model) {
            return;
        }

        foreach ($this->extraFieldDefinitions as $field) {
            $definitionId = $field['id'];
            $newValue = $this->extraFieldValues[$definitionId] ?? null;

            // Find or create the value record
            $valueRecord = CoreExtraFieldValue::query()
                ->where('definition_id', $definitionId)
                ->where('fieldable_type', $model->getMorphClass())
                ->where('fieldable_id', $model->id)
                ->first();

            if ($newValue === null || $newValue === '') {
                // Delete if empty
                if ($valueRecord) {
                    $valueRecord->delete();
                }
            } else {
                $isNewRecord = !$valueRecord;
                $oldValue = $valueRecord?->typed_value;

                if ($isNewRecord) {
                    $valueRecord = new CoreExtraFieldValue([
                        'definition_id' => $definitionId,
                        'fieldable_type' => $model->getMorphClass(),
                        'fieldable_id' => $model->id,
                    ]);
                }

                $valueRecord->setTypedValue($newValue);
                $valueRecord->save();

                // Dispatch LLM verification job for file fields with verify_by_llm enabled
                // Only if value has changed or is new
                if ($field['type'] === 'file' && ($field['verify_by_llm'] ?? false)) {
                    $valueChanged = $isNewRecord || $oldValue !== $newValue;
                    if ($valueChanged) {
                        $valueRecord->update(['verification_status' => 'pending']);
                        VerifyExtraFieldValueJob::dispatch($valueRecord->id);

                        // Update local meta
                        $this->extraFieldMeta[$definitionId] = [
                            'verification_status' => 'pending',
                            'verification_result' => null,
                            'verified_at' => null,
                        ];
                    }
                }
            }
        }

        // Update original values after save
        $this->originalExtraFieldValues = $this->extraFieldValues;
    }

    /**
     * Check if any extra field values have changed
     */
    public function isExtraFieldsDirty(): bool
    {
        foreach ($this->extraFieldValues as $id => $value) {
            $original = $this->originalExtraFieldValues[$id] ?? null;

            // Normalize for comparison (treat empty string as null, empty arrays as null)
            $normalizedValue = ($value === '' || $value === null || $value === []) ? null : $value;
            $normalizedOriginal = ($original === '' || $original === null || $original === []) ? null : $original;

            // Array comparison
            if (is_array($normalizedValue) || is_array($normalizedOriginal)) {
                if ($normalizedValue != $normalizedOriginal) {
                    return true;
                }
            } elseif ($normalizedValue !== $normalizedOriginal) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get validation rules for extra fields
     */
    public function getExtraFieldValidationRules(): array
    {
        $rules = [];

        foreach ($this->extraFieldDefinitions as $field) {
            $fieldRules = [];

            if ($field['is_mandatory'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['type']) {
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'text':
                case 'textarea':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:65535';
                    break;
                case 'boolean':
                    $fieldRules[] = 'in:0,1';
                    break;
                case 'select':
                    $isMultiple = $field['options']['multiple'] ?? false;
                    if ($isMultiple) {
                        $fieldRules[] = 'array';
                    } else {
                        $choices = $field['options']['choices'] ?? [];
                        if (!empty($choices)) {
                            $fieldRules[] = 'in:' . implode(',', $choices);
                        }
                    }
                    break;
                case 'file':
                    $isMultiple = $field['options']['multiple'] ?? false;
                    if ($isMultiple) {
                        $fieldRules[] = 'array';
                    } else {
                        $fieldRules[] = 'integer';
                    }
                    break;
            }

            $rules["extraFieldValues.{$field['id']}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Get validation messages for extra fields
     */
    public function getExtraFieldValidationMessages(): array
    {
        $messages = [];

        foreach ($this->extraFieldDefinitions as $field) {
            $messages["extraFieldValues.{$field['id']}.required"] = "Das Feld \"{$field['label']}\" ist ein Pflichtfeld.";
            $messages["extraFieldValues.{$field['id']}.numeric"] = "Das Feld \"{$field['label']}\" muss eine Zahl sein.";
            $messages["extraFieldValues.{$field['id']}.string"] = "Das Feld \"{$field['label']}\" muss ein Text sein.";
        }

        return $messages;
    }

    /**
     * Öffnet den File-Picker für ein Extra-Feld
     */
    public function openExtraFieldFilePicker(int $fieldId, bool $multiple = false): void
    {
        if (!$this->extraFieldsModel) {
            return;
        }

        $this->activeExtraFieldFilePickerId = $fieldId;
        $this->activeExtraFieldFilePickerMultiple = $multiple;

        // Context aus Model bestimmen
        $contextType = get_class($this->extraFieldsModel);
        $contextId = $this->extraFieldsModel->id;

        // Feldtitel ermitteln
        $fieldTitle = collect($this->extraFieldDefinitions)
            ->firstWhere('id', $fieldId)['label'] ?? null;

        $this->dispatch('files', [
            'context_type' => $contextType,
            'context_id' => $contextId,
        ]);

        $this->dispatch('files:picker', [
            'multiple' => $multiple,
            'callback' => 'extrafield',
            'title' => $fieldTitle,
        ]);
    }

    /**
     * Entfernt eine Datei aus einem Extra-Feld
     */
    public function removeExtraFieldFile(int $fieldId, int $fileId): void
    {
        $currentValue = $this->extraFieldValues[$fieldId] ?? null;

        if (is_array($currentValue)) {
            $this->extraFieldValues[$fieldId] = array_values(
                array_filter($currentValue, fn($id) => $id != $fileId)
            );
        } else {
            $this->extraFieldValues[$fieldId] = null;
        }
    }

    /**
     * Listener für File-Picker Callback
     */
    #[On('files:selected')]
    public function handleExtraFieldFileSelected(array $payload): void
    {
        if (($payload['callback'] ?? null) !== 'extrafield') {
            return;
        }

        if (!$this->activeExtraFieldFilePickerId) {
            return;
        }

        $fieldId = $this->activeExtraFieldFilePickerId;
        $selectedFileIds = collect($payload['files'] ?? [])->pluck('id')->toArray();

        if ($this->activeExtraFieldFilePickerMultiple) {
            // Multiple: Zu bestehenden hinzufügen
            $current = $this->extraFieldValues[$fieldId] ?? [];
            $current = is_array($current) ? $current : ($current ? [$current] : []);
            $this->extraFieldValues[$fieldId] = array_values(array_unique(array_merge($current, $selectedFileIds)));
        } else {
            // Single: Ersetzen
            $this->extraFieldValues[$fieldId] = $selectedFileIds[0] ?? null;
        }

        $this->activeExtraFieldFilePickerId = null;
        $this->activeExtraFieldFilePickerMultiple = false;
    }

    /**
     * Retry LLM verification for an extra field
     */
    public function retryExtraFieldVerification(int $fieldId): void
    {
        $definition = collect($this->extraFieldDefinitions)->firstWhere('id', $fieldId);

        if (!$definition || !($definition['verify_by_llm'] ?? false)) {
            return;
        }

        if (!$this->extraFieldsModel) {
            return;
        }

        $fieldValue = CoreExtraFieldValue::where('definition_id', $fieldId)
            ->where('fieldable_type', $this->extraFieldsModel->getMorphClass())
            ->where('fieldable_id', $this->extraFieldsModel->id)
            ->first();

        if ($fieldValue) {
            $fieldValue->update(['verification_status' => 'pending']);
            VerifyExtraFieldValueJob::dispatch($fieldValue->id);

            // Update local meta
            $this->extraFieldMeta[$fieldId] = [
                'verification_status' => 'pending',
                'verification_result' => null,
                'verified_at' => null,
            ];
        }
    }

    // ==========================================
    // Visibility Evaluation Methods
    // ==========================================

    /**
     * Get only the visible extra field definitions based on current values.
     * This is a computed property that re-evaluates when field values change.
     */
    #[Computed]
    public function visibleExtraFieldDefinitions(): array
    {
        if (empty($this->extraFieldDefinitions)) {
            return [];
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        $fieldValuesByName = $this->getFieldValuesByName();
        $visibleFields = [];

        foreach ($this->extraFieldDefinitions as $field) {
            if ($this->isFieldVisible($field, $fieldValuesByName, $evaluator)) {
                $visibleFields[] = $field;
            }
        }

        return $visibleFields;
    }

    /**
     * Check if a specific field is visible based on current values.
     *
     * @param int $fieldId The field definition ID
     * @return bool
     */
    public function isExtraFieldVisible(int $fieldId): bool
    {
        $field = collect($this->extraFieldDefinitions)->firstWhere('id', $fieldId);
        if (!$field) {
            return false;
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        $fieldValuesByName = $this->getFieldValuesByName();

        return $this->isFieldVisible($field, $fieldValuesByName, $evaluator);
    }

    /**
     * Check if a field is visible based on its visibility config and current values.
     */
    protected function isFieldVisible(array $field, array $fieldValuesByName, ExtraFieldConditionEvaluator $evaluator): bool
    {
        $visibility = $field['options']['visibility'] ?? null;

        // If no visibility config or not enabled, field is visible
        if (!$visibility || !($visibility['enabled'] ?? false)) {
            return true;
        }

        return $evaluator->evaluate($visibility, $fieldValuesByName);
    }

    /**
     * Build a map of field values indexed by field name.
     */
    protected function getFieldValuesByName(): array
    {
        $fieldValuesByName = [];

        foreach ($this->extraFieldDefinitions as $field) {
            $value = $this->extraFieldValues[$field['id']] ?? null;
            $fieldValuesByName[$field['name']] = $value;
        }

        return $fieldValuesByName;
    }

    /**
     * Get field visibility states as an array.
     * Useful for passing to Alpine.js for client-side tracking.
     *
     * @return array<int, bool> Map of field ID to visibility state
     */
    public function getFieldVisibilityStates(): array
    {
        $states = [];
        $evaluator = new ExtraFieldConditionEvaluator();
        $fieldValuesByName = $this->getFieldValuesByName();

        foreach ($this->extraFieldDefinitions as $field) {
            $states[$field['id']] = $this->isFieldVisible($field, $fieldValuesByName, $evaluator);
        }

        return $states;
    }

    /**
     * Get a human-readable description of a field's visibility conditions.
     *
     * @param int $fieldId The field definition ID
     * @return string
     */
    public function getFieldVisibilityDescription(int $fieldId): string
    {
        $field = collect($this->extraFieldDefinitions)->firstWhere('id', $fieldId);
        if (!$field) {
            return 'Feld nicht gefunden';
        }

        $visibility = $field['options']['visibility'] ?? null;

        if (!$visibility || !($visibility['enabled'] ?? false)) {
            return 'Immer sichtbar';
        }

        // Build field labels map
        $fieldLabels = [];
        foreach ($this->extraFieldDefinitions as $f) {
            $fieldLabels[$f['name']] = $f['label'];
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        return $evaluator->toHumanReadable($visibility, $fieldLabels);
    }
}
