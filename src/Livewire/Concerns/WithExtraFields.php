<?php

namespace Platform\Core\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;

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
     * The model that has extra fields
     */
    protected ?Model $extraFieldsModel = null;

    /**
     * Load extra field values from a model
     */
    public function loadExtraFieldValues(Model $model): void
    {
        $this->extraFieldsModel = $model;

        // Load definitions
        $this->extraFieldDefinitions = $model->getExtraFieldsWithLabels();

        // Initialize values array with current values
        $this->extraFieldValues = [];
        foreach ($this->extraFieldDefinitions as $field) {
            $value = $field['value'];

            // Für Mehrfachauswahl muss der Wert ein Array sein
            if ($field['type'] === 'select' && ($field['options']['multiple'] ?? false)) {
                if ($value === null) {
                    $value = [];
                } elseif (!is_array($value)) {
                    $value = [$value];
                }
            }

            $this->extraFieldValues[$field['id']] = $value;
        }

        // Store original for dirty checking
        $this->originalExtraFieldValues = $this->extraFieldValues;
    }

    /**
     * Refresh extra field definitions (e.g., after definitions were changed)
     */
    public function refreshExtraFieldDefinitions(): void
    {
        if ($this->extraFieldsModel) {
            $this->extraFieldDefinitions = $this->extraFieldsModel->getExtraFieldsWithLabels();

            // Add any new definitions to values array
            foreach ($this->extraFieldDefinitions as $field) {
                if (!array_key_exists($field['id'], $this->extraFieldValues)) {
                    $this->extraFieldValues[$field['id']] = $field['value'];
                    $this->originalExtraFieldValues[$field['id']] = $field['value'];
                }
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
                if (!$valueRecord) {
                    $valueRecord = new CoreExtraFieldValue([
                        'definition_id' => $definitionId,
                        'fieldable_type' => $model->getMorphClass(),
                        'fieldable_id' => $model->id,
                    ]);
                }

                $valueRecord->setTypedValue($newValue);
                $valueRecord->save();
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

            if ($field['is_required']) {
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
}
