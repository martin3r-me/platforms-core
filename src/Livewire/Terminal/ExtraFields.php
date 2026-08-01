<?php

namespace Platform\Core\Livewire\Terminal;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Core\Models\CoreLookup;
use Platform\Core\Models\CoreLookupValue;
use Platform\Core\Services\ExtraFieldCircularDependencyDetector;
use Platform\Core\Services\ExtraFieldConditionEvaluator;

/**
 * ExtraFields-Definitions-Editor — 1:1 aus der Terminal-Shell in eine
 * verschachtelte App-Komponente extrahiert. Verhaltenserhaltend: die Shell
 * behält den dünnen #[On('extrafields')]-Listener + Tab; diese Komponente
 * besitzt allen ef*-State, alle ef*-Methoden und das Editor-Markup. Der
 * Kontext (efContextType/efContextId) kommt als reaktive Props von der Shell.
 */
class ExtraFields extends Component
{
    // ── ExtraFields App ──────────────────────────────────────
    #[Reactive]
    public ?string $efContextType = null;
    #[Reactive]
    public ?int $efContextId = null;
    /** Vorberechneter Breadcrumb (Icon/Titel) von der Shell — getContextBreadcrumb bleibt shell-seitig. */
    #[Reactive]
    public array $contextBreadcrumb = [];
    public string $efTab = 'fields'; // 'fields' | 'lookups'
    public string $efEditFieldTab = 'basis'; // 'basis' | 'options' | 'conditions' | 'verification' | 'autofill'

    // Definitions
    public array $efDefinitions = [];
    public ?int $efEditingDefinitionId = null;
    public array $efNewField = [
        'name' => '',
        'label' => '',
        'description' => '',
        'type' => 'text',
        'is_required' => false,
        'is_mandatory' => false,
        'is_encrypted' => false,
        'options' => [],
        'is_multiple' => false,
        'verify_by_llm' => false,
        'verify_instructions' => '',
        'auto_fill_source' => '',
        'auto_fill_prompt' => '',
        'lookup_id' => null,
        'regex_pattern' => '',
        'regex_description' => '',
        'regex_error' => '',
        'placeholder' => '',
        'visibility' => [
            'enabled' => false,
            'logic' => 'AND',
            'groups' => [],
        ],
    ];
    public array $efEditField = [
        'label' => '',
        'description' => '',
        'type' => 'text',
        'is_required' => false,
        'is_mandatory' => false,
        'is_encrypted' => false,
        'options' => [],
        'is_multiple' => false,
        'verify_by_llm' => false,
        'verify_instructions' => '',
        'auto_fill_source' => '',
        'auto_fill_prompt' => '',
        'lookup_id' => null,
        'regex_pattern' => '',
        'regex_description' => '',
        'regex_error' => '',
        'placeholder' => '',
        'visibility' => [
            'enabled' => false,
            'logic' => 'AND',
            'groups' => [],
        ],
    ];
    public string $efNewOptionText = '';
    public string $efEditOptionText = '';

    // Lookups
    public array $efLookups = [];
    public ?int $efEditingLookupId = null;
    public array $efNewLookup = ['name' => '', 'label' => '', 'description' => ''];
    public array $efEditLookup = ['label' => '', 'description' => ''];

    // Lookup Values
    public array $efLookupValues = [];
    public ?int $efSelectedLookupId = null;
    public string $efNewLookupValueText = '';
    public string $efNewLookupValueLabel = '';

    public function mount(): void
    {
        $this->efResetForm();
        $this->efResetLookupForm();
        $this->efLoadDefinitions();
        $this->efLoadLookups();
    }

    /** Reaktive Kontext-Props: bei tatsächlicher Änderung neu laden (idempotent bei gleichem Kontext). */
    public function updatedEfContextType(): void { $this->reloadForContext(); }
    public function updatedEfContextId(): void { $this->reloadForContext(); }

    protected function reloadForContext(): void
    {
        $this->efTab = 'fields';
        $this->efEditingDefinitionId = null;
        $this->efEditFieldTab = 'basis';
        $this->efResetForm();
        $this->efResetLookupForm();
        $this->efLoadDefinitions();
        $this->efLoadLookups();
    }

    public function efLoadDefinitions(): void
    {
        if (! $this->efContextType) {
            $this->efDefinitions = [];
            return;
        }

        if (! class_exists($this->efContextType)) {
            $this->efDefinitions = [];
            return;
        }

        try {
            if (! Schema::hasTable('core_extra_field_definitions')) {
                $this->efDefinitions = [];
                return;
            }
        } catch (\Exception $e) {
            $this->efDefinitions = [];
            return;
        }

        try {
            $teamId = $this->efGetTeamId();
            if (! $teamId) {
                $this->efDefinitions = [];
                return;
            }

            $this->efDefinitions = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->efContextType, $this->efContextId)
                ->orderBy('order')
                ->orderBy('label')
                ->get()
                ->map(function ($def) {
                    return [
                        'id' => $def->id,
                        'name' => $def->name,
                        'label' => $def->label,
                        'description' => $def->description,
                        'type' => $def->type,
                        'type_label' => $def->type_label,
                        'is_required' => $def->is_required,
                        'is_mandatory' => $def->is_mandatory,
                        'is_encrypted' => $def->is_encrypted,
                        'is_global' => $def->isGlobal(),
                        'options' => $def->options,
                        'visibility_config' => $def->visibility_config,
                        'has_visibility_conditions' => $def->hasVisibilityConditions(),
                        'verify_by_llm' => $def->verify_by_llm,
                        'verify_instructions' => $def->verify_instructions,
                        'auto_fill_source' => $def->auto_fill_source,
                        'auto_fill_prompt' => $def->auto_fill_prompt,
                        'created_at' => $def->created_at?->format('d.m.Y'),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            $this->efDefinitions = [];
        }
    }

    public function efCreateDefinition(): void
    {
        $rules = [
            'efNewField.label' => ['required', 'string', 'max:255'],
            'efNewField.type' => ['required', 'string', 'in:' . implode(',', array_keys(CoreExtraFieldDefinition::TYPES))],
            'efNewField.is_required' => ['boolean'],
            'efNewField.is_mandatory' => ['boolean'],
            'efNewField.is_encrypted' => ['boolean'],
        ];

        if ($this->efNewField['type'] === 'select') {
            $rules['efNewField.options'] = ['required', 'array', 'min:1'];
        }
        if ($this->efNewField['type'] === 'lookup') {
            $rules['efNewField.lookup_id'] = ['required', 'integer', 'exists:core_lookups,id'];
        }
        if ($this->efNewField['type'] === 'regex') {
            $rules['efNewField.regex_pattern'] = ['required', 'string', 'max:500'];
        }

        $this->validate($rules);

        try {
            $teamId = $this->efGetTeamId();
            if (! $teamId) {
                $this->addError('efNewField.label', 'Kein Team-Kontext vorhanden.');
                return;
            }

            $user = Auth::user();
            $name = Str::slug($this->efNewField['label'], '_');

            $exists = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->efContextType, $this->efContextId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $this->addError('efNewField.label', 'Ein Feld mit diesem Namen existiert bereits.');
                return;
            }

            $maxOrder = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->efContextType, $this->efContextId)
                ->max('order') ?? 0;

            $options = null;
            if ($this->efNewField['type'] === 'select') {
                $options = [
                    'choices' => $this->efNewField['options'],
                    'multiple' => $this->efNewField['is_multiple'] ?? false,
                ];
            }
            if ($this->efNewField['type'] === 'lookup') {
                $options = [
                    'lookup_id' => (int) $this->efNewField['lookup_id'],
                    'multiple' => $this->efNewField['is_multiple'] ?? false,
                ];
            }
            if ($this->efNewField['type'] === 'file') {
                $options = [
                    'multiple' => $this->efNewField['is_multiple'] ?? false,
                ];
            }
            if ($this->efNewField['type'] === 'regex') {
                $pattern = trim($this->efNewField['regex_pattern'] ?? '');
                if (@preg_match('/' . $pattern . '/', '') === false) {
                    $this->addError('efNewField.regex_pattern', 'Ungültiges reguläres Ausdrucksmuster.');
                    return;
                }
                $options = [
                    'pattern' => $pattern,
                    'pattern_description' => trim($this->efNewField['regex_description'] ?? '') ?: null,
                    'pattern_error' => trim($this->efNewField['regex_error'] ?? '') ?: null,
                ];
            }

            $placeholder = trim($this->efNewField['placeholder'] ?? '');
            if ($placeholder !== '' && in_array($this->efNewField['type'], ['text', 'number', 'textarea', 'regex'])) {
                $options = $options ?? [];
                $options['placeholder'] = $placeholder;
            }

            CoreExtraFieldDefinition::create([
                'team_id' => $teamId,
                'created_by_user_id' => $user->id,
                'context_type' => $this->efContextType,
                'context_id' => $this->efContextId,
                'name' => $name,
                'label' => trim($this->efNewField['label']),
                'description' => ! empty($this->efNewField['description']) ? trim($this->efNewField['description']) : null,
                'type' => $this->efNewField['type'],
                'is_required' => $this->efNewField['is_required'] ?? false,
                'is_mandatory' => $this->efNewField['is_mandatory'] ?? false,
                'is_encrypted' => $this->efNewField['is_encrypted'] ?? false,
                'order' => $maxOrder + 1,
                'options' => $options,
                'verify_by_llm' => $this->efNewField['type'] === 'file' && ($this->efNewField['verify_by_llm'] ?? false),
                'verify_instructions' => $this->efNewField['type'] === 'file' ? ($this->efNewField['verify_instructions'] ?? null) : null,
                'auto_fill_source' => ! empty($this->efNewField['auto_fill_source']) ? $this->efNewField['auto_fill_source'] : null,
                'auto_fill_prompt' => ! empty($this->efNewField['auto_fill_prompt']) ? $this->efNewField['auto_fill_prompt'] : null,
            ]);

            $this->efResetForm();
            $this->efLoadDefinitions();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Feld erstellt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Erstellen des Feldes.']);
        }
    }

    public function efStartEditDefinition(int $definitionId): void
    {
        $definition = collect($this->efDefinitions)->firstWhere('id', $definitionId);
        if (! $definition) {
            return;
        }

        $this->efEditingDefinitionId = $definitionId;
        $this->efEditFieldTab = 'basis';

        $visibility = $definition['visibility_config'] ?? ExtraFieldConditionEvaluator::createEmptyConfig();

        $this->efEditField = [
            'label' => $definition['label'],
            'description' => $definition['description'] ?? '',
            'type' => $definition['type'],
            'is_required' => $definition['is_required'],
            'is_mandatory' => $definition['is_mandatory'],
            'is_encrypted' => $definition['is_encrypted'],
            'options' => $definition['options']['choices'] ?? [],
            'is_multiple' => $definition['options']['multiple'] ?? false,
            'verify_by_llm' => $definition['verify_by_llm'] ?? false,
            'verify_instructions' => $definition['verify_instructions'] ?? '',
            'auto_fill_source' => $definition['auto_fill_source'] ?? '',
            'auto_fill_prompt' => $definition['auto_fill_prompt'] ?? '',
            'lookup_id' => $definition['options']['lookup_id'] ?? null,
            'regex_pattern' => $definition['options']['pattern'] ?? '',
            'regex_description' => $definition['options']['pattern_description'] ?? '',
            'regex_error' => $definition['options']['pattern_error'] ?? '',
            'placeholder' => $definition['options']['placeholder'] ?? '',
            'visibility' => $visibility,
        ];
        $this->efEditOptionText = '';
    }

    public function efCancelEditDefinition(): void
    {
        $this->efEditingDefinitionId = null;
        $this->efEditFieldTab = 'basis';
        $this->efEditField = [
            'label' => '',
            'description' => '',
            'type' => 'text',
            'is_required' => false,
            'is_mandatory' => false,
            'is_encrypted' => false,
            'options' => [],
            'is_multiple' => false,
            'verify_by_llm' => false,
            'verify_instructions' => '',
            'auto_fill_source' => '',
            'auto_fill_prompt' => '',
            'lookup_id' => null,
            'regex_pattern' => '',
            'regex_description' => '',
            'regex_error' => '',
            'placeholder' => '',
            'visibility' => ExtraFieldConditionEvaluator::createEmptyConfig(),
        ];
        $this->efEditOptionText = '';
    }

    public function efSaveEditDefinition(): void
    {
        if (! $this->efEditingDefinitionId) {
            return;
        }

        $rules = [
            'efEditField.label' => ['required', 'string', 'max:255'],
            'efEditField.type' => ['required', 'string', 'in:' . implode(',', array_keys(CoreExtraFieldDefinition::TYPES))],
            'efEditField.is_required' => ['boolean'],
            'efEditField.is_mandatory' => ['boolean'],
            'efEditField.is_encrypted' => ['boolean'],
        ];

        if ($this->efEditField['type'] === 'select') {
            $rules['efEditField.options'] = ['required', 'array', 'min:1'];
        }
        if ($this->efEditField['type'] === 'lookup') {
            $rules['efEditField.lookup_id'] = ['required', 'integer', 'exists:core_lookups,id'];
        }
        if ($this->efEditField['type'] === 'regex') {
            $rules['efEditField.regex_pattern'] = ['required', 'string', 'max:500'];
        }

        $this->validate($rules);

        try {
            $definition = CoreExtraFieldDefinition::find($this->efEditingDefinitionId);
            if (! $definition) {
                return;
            }

            if ($this->efEditField['type'] === 'regex') {
                $pattern = trim($this->efEditField['regex_pattern'] ?? '');
                if (@preg_match('/' . $pattern . '/', '') === false) {
                    $this->addError('efEditField.regex_pattern', 'Ungültiges reguläres Ausdrucksmuster.');
                    return;
                }
            }

            $options = match ($this->efEditField['type']) {
                'select' => [
                    'choices' => $this->efEditField['options'],
                    'multiple' => $this->efEditField['is_multiple'] ?? false,
                ],
                'lookup' => [
                    'lookup_id' => (int) $this->efEditField['lookup_id'],
                    'multiple' => $this->efEditField['is_multiple'] ?? false,
                ],
                'file' => [
                    'multiple' => $this->efEditField['is_multiple'] ?? false,
                ],
                'regex' => [
                    'pattern' => trim($this->efEditField['regex_pattern'] ?? ''),
                    'pattern_description' => trim($this->efEditField['regex_description'] ?? '') ?: null,
                    'pattern_error' => trim($this->efEditField['regex_error'] ?? '') ?: null,
                ],
                default => [],
            };

            $placeholder = trim($this->efEditField['placeholder'] ?? '');
            if ($placeholder !== '' && in_array($this->efEditField['type'], ['text', 'number', 'textarea', 'regex'])) {
                $options['placeholder'] = $placeholder;
            }

            $visibility = $this->efEditField['visibility'] ?? ExtraFieldConditionEvaluator::createEmptyConfig();
            $visibilityConfig = ($visibility['enabled'] ?? false) ? $visibility : null;

            if ($visibilityConfig) {
                $detector = new ExtraFieldCircularDependencyDetector();
                $cycle = $detector->detectCycle(
                    $definition->name,
                    $visibilityConfig,
                    $this->efDefinitions
                );

                if ($cycle !== null) {
                    $fieldLabels = [];
                    foreach ($this->efDefinitions as $def) {
                        $fieldLabels[$def['name']] = $def['label'];
                    }
                    $fieldLabels[$definition->name] = trim($this->efEditField['label']);
                    $cycleDescription = $detector->describeCycle($cycle, $fieldLabels);

                    $this->addError('efEditField.visibility', "Zirkuläre Abhängigkeit erkannt: {$cycleDescription}");
                    return;
                }
            }

            $definition->update([
                'label' => trim($this->efEditField['label']),
                'description' => ! empty($this->efEditField['description']) ? trim($this->efEditField['description']) : null,
                'type' => $this->efEditField['type'],
                'is_required' => $this->efEditField['is_required'] ?? false,
                'is_mandatory' => $this->efEditField['is_mandatory'] ?? false,
                'is_encrypted' => $this->efEditField['is_encrypted'] ?? false,
                'options' => $options,
                'visibility_config' => $visibilityConfig,
                'verify_by_llm' => $this->efEditField['type'] === 'file' && ($this->efEditField['verify_by_llm'] ?? false),
                'verify_instructions' => $this->efEditField['type'] === 'file' ? ($this->efEditField['verify_instructions'] ?? null) : null,
                'auto_fill_source' => ! empty($this->efEditField['auto_fill_source']) ? $this->efEditField['auto_fill_source'] : null,
                'auto_fill_prompt' => ! empty($this->efEditField['auto_fill_prompt']) ? $this->efEditField['auto_fill_prompt'] : null,
            ]);

            $this->efCancelEditDefinition();
            $this->efLoadDefinitions();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Feld aktualisiert.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Aktualisieren.']);
        }
    }

    public function efDeleteDefinition(int $definitionId): void
    {
        try {
            $definition = CoreExtraFieldDefinition::find($definitionId);
            if (! $definition) {
                return;
            }

            CoreExtraFieldValue::where('definition_id', $definitionId)->delete();
            $definition->delete();

            if ($this->efEditingDefinitionId === $definitionId) {
                $this->efCancelEditDefinition();
            }

            $this->efLoadDefinitions();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Feld gelöscht.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen.']);
        }
    }

    // ── EF: Options Management ──────────────────────────────

    public function efAddNewOption(): void
    {
        $text = trim($this->efNewOptionText);
        if ($text !== '' && ! in_array($text, $this->efNewField['options'])) {
            $this->efNewField['options'][] = $text;
        }
        $this->efNewOptionText = '';
    }

    public function efRemoveNewOption(int $index): void
    {
        unset($this->efNewField['options'][$index]);
        $this->efNewField['options'] = array_values($this->efNewField['options']);
    }

    public function efAddEditOption(): void
    {
        $text = trim($this->efEditOptionText);
        if ($text !== '' && ! in_array($text, $this->efEditField['options'])) {
            $this->efEditField['options'][] = $text;
        }
        $this->efEditOptionText = '';
    }

    public function efRemoveEditOption(int $index): void
    {
        unset($this->efEditField['options'][$index]);
        $this->efEditField['options'] = array_values($this->efEditField['options']);
    }

    // ── EF: Lookups CRUD ────────────────────────────────────

    public function efLoadLookups(): void
    {
        $teamId = $this->efGetTeamId();
        if (! $teamId) {
            $this->efLookups = [];
            return;
        }

        try {
            if (! Schema::hasTable('core_lookups')) {
                $this->efLookups = [];
                return;
            }

            $this->efLookups = CoreLookup::forTeam($teamId)
                ->orderBy('label')
                ->get()
                ->map(fn ($lookup) => [
                    'id' => $lookup->id,
                    'name' => $lookup->name,
                    'label' => $lookup->label,
                    'description' => $lookup->description,
                    'is_system' => $lookup->is_system,
                    'values_count' => $lookup->values()->count(),
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->efLookups = [];
        }
    }

    public function efCreateLookup(): void
    {
        $this->validate([
            'efNewLookup.label' => ['required', 'string', 'max:255'],
        ]);

        $teamId = $this->efGetTeamId();
        if (! $teamId) {
            $this->addError('efNewLookup.label', 'Kein Team-Kontext vorhanden.');
            return;
        }

        $name = Str::slug($this->efNewLookup['label'], '_');

        $exists = CoreLookup::forTeam($teamId)->where('name', $name)->exists();
        if ($exists) {
            $this->addError('efNewLookup.label', 'Ein Lookup mit diesem Namen existiert bereits.');
            return;
        }

        try {
            CoreLookup::create([
                'team_id' => $teamId,
                'created_by_user_id' => Auth::id(),
                'name' => $name,
                'label' => trim($this->efNewLookup['label']),
                'description' => trim($this->efNewLookup['description']) ?: null,
                'is_system' => false,
            ]);

            $this->efNewLookup = ['name' => '', 'label' => '', 'description' => ''];
            $this->efLoadLookups();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lookup erstellt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Erstellen.']);
        }
    }

    public function efStartEditLookup(int $lookupId): void
    {
        $lookup = collect($this->efLookups)->firstWhere('id', $lookupId);
        if (! $lookup) {
            return;
        }

        $this->efEditingLookupId = $lookupId;
        $this->efEditLookup = [
            'label' => $lookup['label'],
            'description' => $lookup['description'] ?? '',
        ];
    }

    public function efCancelEditLookup(): void
    {
        $this->efEditingLookupId = null;
        $this->efEditLookup = ['label' => '', 'description' => ''];
    }

    public function efSaveEditLookup(): void
    {
        if (! $this->efEditingLookupId) {
            return;
        }

        $this->validate([
            'efEditLookup.label' => ['required', 'string', 'max:255'],
        ]);

        try {
            $lookup = CoreLookup::find($this->efEditingLookupId);
            if (! $lookup) {
                return;
            }

            $lookup->update([
                'label' => trim($this->efEditLookup['label']),
                'description' => trim($this->efEditLookup['description']) ?: null,
            ]);

            $this->efCancelEditLookup();
            $this->efLoadLookups();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lookup aktualisiert.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Aktualisieren.']);
        }
    }

    public function efDeleteLookup(int $lookupId): void
    {
        try {
            $lookup = CoreLookup::find($lookupId);
            if (! $lookup) {
                return;
            }

            if ($lookup->is_system) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'System-Lookups können nicht gelöscht werden.']);
                return;
            }

            $inUse = CoreExtraFieldDefinition::where('type', 'lookup')
                ->where('options->lookup_id', $lookupId)
                ->exists();

            if ($inUse) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Lookup wird noch verwendet und kann nicht gelöscht werden.']);
                return;
            }

            $lookup->delete();

            if ($this->efSelectedLookupId === $lookupId) {
                $this->efSelectedLookupId = null;
                $this->efLookupValues = [];
            }

            $this->efLoadLookups();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Lookup gelöscht.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen.']);
        }
    }

    // ── EF: Lookup Values ───────────────────────────────────

    public function efSelectLookup(int $lookupId): void
    {
        $this->efSelectedLookupId = $lookupId;
        $this->efLoadLookupValues();
    }

    public function efDeselectLookup(): void
    {
        $this->efSelectedLookupId = null;
        $this->efLookupValues = [];
        $this->efNewLookupValueText = '';
        $this->efNewLookupValueLabel = '';
    }

    public function efLoadLookupValues(): void
    {
        if (! $this->efSelectedLookupId) {
            $this->efLookupValues = [];
            return;
        }

        try {
            $this->efLookupValues = CoreLookupValue::where('lookup_id', $this->efSelectedLookupId)
                ->orderBy('order')
                ->orderBy('label')
                ->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'value' => $v->value,
                    'label' => $v->label,
                    'order' => $v->order,
                    'is_active' => $v->is_active,
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->efLookupValues = [];
        }
    }

    public function efAddLookupValue(): void
    {
        if (! $this->efSelectedLookupId) {
            return;
        }

        $label = trim($this->efNewLookupValueLabel);
        $value = trim($this->efNewLookupValueText) ?: $label;

        if ($label === '') {
            return;
        }

        $exists = CoreLookupValue::where('lookup_id', $this->efSelectedLookupId)
            ->where('value', $value)
            ->exists();

        if ($exists) {
            $this->addError('efNewLookupValueText', 'Dieser Wert existiert bereits.');
            return;
        }

        try {
            $maxOrder = CoreLookupValue::where('lookup_id', $this->efSelectedLookupId)->max('order') ?? 0;

            CoreLookupValue::create([
                'lookup_id' => $this->efSelectedLookupId,
                'value' => $value,
                'label' => $label,
                'order' => $maxOrder + 1,
                'is_active' => true,
            ]);

            $this->efNewLookupValueText = '';
            $this->efNewLookupValueLabel = '';
            $this->efLoadLookupValues();
            $this->efLoadLookups();
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Hinzufügen.']);
        }
    }

    public function efToggleLookupValue(int $valueId): void
    {
        try {
            $value = CoreLookupValue::find($valueId);
            if ($value) {
                $value->update(['is_active' => ! $value->is_active]);
                $this->efLoadLookupValues();
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    public function efDeleteLookupValue(int $valueId): void
    {
        try {
            CoreLookupValue::where('id', $valueId)->delete();
            $this->efLoadLookupValues();
            $this->efLoadLookups();
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen.']);
        }
    }

    public function efMoveLookupValueUp(int $valueId): void
    {
        $this->efMoveLookupValue($valueId, -1);
    }

    public function efMoveLookupValueDown(int $valueId): void
    {
        $this->efMoveLookupValue($valueId, 1);
    }

    protected function efMoveLookupValue(int $valueId, int $direction): void
    {
        $values = collect($this->efLookupValues);
        $currentIndex = $values->search(fn ($v) => $v['id'] === $valueId);

        if ($currentIndex === false) {
            return;
        }

        $newIndex = $currentIndex + $direction;
        if ($newIndex < 0 || $newIndex >= $values->count()) {
            return;
        }

        $currentValue = CoreLookupValue::find($valueId);
        $swapValue = CoreLookupValue::find($values[$newIndex]['id']);

        if ($currentValue && $swapValue) {
            $tempOrder = $currentValue->order;
            $currentValue->update(['order' => $swapValue->order]);
            $swapValue->update(['order' => $tempOrder]);
            $this->efLoadLookupValues();
        }
    }

    // ── EF: Condition Builder ───────────────────────────────

    public function efGetOperatorsForField(string $fieldName): array
    {
        $field = collect($this->efDefinitions)->firstWhere('name', $fieldName);
        if (! $field) {
            return [];
        }

        return ExtraFieldConditionEvaluator::getOperatorsForType($field['type']);
    }

    public function efToggleVisibilityEnabled(): void
    {
        $this->efEditField['visibility']['enabled'] = ! ($this->efEditField['visibility']['enabled'] ?? false);

        if ($this->efEditField['visibility']['enabled'] && empty($this->efEditField['visibility']['groups'])) {
            $this->efAddConditionGroup();
        }
    }

    public function efSetVisibilityLogic(string $logic): void
    {
        $this->efEditField['visibility']['logic'] = $logic;
    }

    public function efAddConditionGroup(): void
    {
        $this->efEditField['visibility']['groups'][] = ExtraFieldConditionEvaluator::createEmptyGroup();
    }

    public function efRemoveConditionGroup(int $groupIndex): void
    {
        unset($this->efEditField['visibility']['groups'][$groupIndex]);
        $this->efEditField['visibility']['groups'] = array_values($this->efEditField['visibility']['groups']);

        if (empty($this->efEditField['visibility']['groups'])) {
            $this->efEditField['visibility']['enabled'] = false;
        }
    }

    public function efSetGroupLogic(int $groupIndex, string $logic): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['logic'] = $logic;
        }
    }

    public function efAddCondition(int $groupIndex): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][] = ExtraFieldConditionEvaluator::createEmptyCondition();
        }
    }

    public function efRemoveCondition(int $groupIndex, int $conditionIndex): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]);
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'] = array_values(
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions']
            );
        }
    }

    public function efUpdateConditionField(int $groupIndex, int $conditionIndex, string $fieldName): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['field'] = $fieldName;

            $operators = $this->efGetOperatorsForField($fieldName);
            if (! empty($operators)) {
                $firstOperator = array_key_first($operators);
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['operator'] = $firstOperator;
            }

            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = null;
        }
    }

    public function efUpdateConditionOperator(int $groupIndex, int $conditionIndex, string $operator): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['operator'] = $operator;

            $operatorMeta = ExtraFieldConditionEvaluator::OPERATORS[$operator] ?? null;
            if ($operatorMeta && ! ($operatorMeta['requiresValue'] ?? true)) {
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = null;
            }

            if (in_array($operator, ['is_in', 'is_not_in'])) {
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source'] = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source'] ?? 'manual';
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] ?? null;
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] ?? [];
            } else {
                unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source']);
                unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id']);
            }
        }
    }

    public function efUpdateConditionValue(int $groupIndex, int $conditionIndex, mixed $value): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = $value;
        }
    }

    public function efUpdateConditionListSource(int $groupIndex, int $conditionIndex, string $source): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_source'] = $source;
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = [];
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] = null;
        }
    }

    public function efUpdateConditionListLookup(int $groupIndex, int $conditionIndex, mixed $lookupId): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['list_lookup_id'] = $lookupId ? (int) $lookupId : null;
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = [];
        }
    }

    public function efAddConditionListValue(int $groupIndex, int $conditionIndex, string $value): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $value = trim($value);
            if ($value === '') {
                return;
            }
            $currentValues = $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] ?? [];
            if (! is_array($currentValues)) {
                $currentValues = [];
            }
            if (! in_array($value, $currentValues)) {
                $currentValues[] = $value;
            }
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = $currentValues;
        }
    }

    public function efRemoveConditionListValue(int $groupIndex, int $conditionIndex, int $valueIndex): void
    {
        if (isset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'][$valueIndex])) {
            unset($this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'][$valueIndex]);
            $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = array_values(
                $this->efEditField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value']
            );
        }
    }

    // ── EF: Computed Properties & Helpers ────────────────────

    public function efContextLabel(): ?string
    {
        if (! $this->efContextType || ! $this->efContextId) {
            return null;
        }

        if (! class_exists($this->efContextType)) {
            return null;
        }

        try {
            $context = $this->efContextType::find($this->efContextId);
            if (! $context) {
                return null;
            }

            if (method_exists($context, 'getDisplayName')) {
                return $context->getDisplayName();
            }
            if (method_exists($context, 'getContact')) {
                $contact = $context->getContact();
                if ($contact && isset($contact->full_name)) {
                    return $contact->full_name;
                }
            }
            if (isset($context->title)) {
                return $context->title;
            }
            if (isset($context->name)) {
                return $context->name;
            }

            return class_basename($this->efContextType) . ' #' . $this->efContextId;
        } catch (\Exception $e) {
            return class_basename($this->efContextType) . ' #' . $this->efContextId;
        }
    }

    public function efAvailableTypes(): array
    {
        return CoreExtraFieldDefinition::TYPES;
    }

    public function efAutoFillSources(): array
    {
        return CoreExtraFieldDefinition::AUTO_FILL_SOURCES;
    }

    public function efTypeDescriptions(): array
    {
        return CoreExtraFieldDefinition::TYPE_DESCRIPTIONS;
    }

    public function efAutoFillSourceDescriptions(): array
    {
        return CoreExtraFieldDefinition::AUTO_FILL_SOURCE_DESCRIPTIONS;
    }

    public function efConditionFields(): array
    {
        $fields = [];
        foreach ($this->efDefinitions as $def) {
            if ($def['id'] === $this->efEditingDefinitionId) {
                continue;
            }
            $fields[] = [
                'name' => $def['name'],
                'label' => $def['label'],
                'type' => $def['type'],
                'options' => $def['options'] ?? [],
            ];
        }
        return $fields;
    }

    public function efAllOperators(): array
    {
        return ExtraFieldConditionEvaluator::getAllOperators();
    }

    public function efAvailableLookupsForCondition(): array
    {
        $teamId = $this->efGetTeamId();
        if (! $teamId) {
            return [];
        }

        return CoreLookup::where('team_id', $teamId)
            ->orderBy('label')
            ->get()
            ->map(fn ($lookup) => [
                'id' => $lookup->id,
                'label' => $lookup->label,
                'name' => $lookup->name,
            ])
            ->all();
    }

    public function efVisibilityDescription(): string
    {
        if (! ($this->efEditField['visibility']['enabled'] ?? false)) {
            return 'Immer sichtbar';
        }

        $fieldLabels = [];
        foreach ($this->efDefinitions as $def) {
            $fieldLabels[$def['name']] = $def['label'];
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        return $evaluator->toHumanReadable($this->efEditField['visibility'], $fieldLabels);
    }

    public function efSelectedLookup(): ?array
    {
        if (! $this->efSelectedLookupId) {
            return null;
        }
        return collect($this->efLookups)->firstWhere('id', $this->efSelectedLookupId);
    }

    protected function efResetForm(): void
    {
        $this->efNewField = [
            'name' => '',
            'label' => '',
            'description' => '',
            'type' => 'text',
            'is_required' => false,
            'is_mandatory' => false,
            'is_encrypted' => false,
            'options' => [],
            'is_multiple' => false,
            'verify_by_llm' => false,
            'verify_instructions' => '',
            'auto_fill_source' => '',
            'auto_fill_prompt' => '',
            'lookup_id' => null,
            'regex_pattern' => '',
            'regex_description' => '',
            'regex_error' => '',
            'placeholder' => '',
            'visibility' => ExtraFieldConditionEvaluator::createEmptyConfig(),
        ];
        $this->efNewOptionText = '';
    }

    protected function efResetLookupForm(): void
    {
        $this->efNewLookup = ['name' => '', 'label' => '', 'description' => ''];
        $this->efEditLookup = ['label' => '', 'description' => ''];
        $this->efEditingLookupId = null;
        $this->efSelectedLookupId = null;
        $this->efLookupValues = [];
        $this->efNewLookupValueText = '';
        $this->efNewLookupValueLabel = '';
    }

    protected function efGetTeamId(): ?int
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return null;
            }

            $baseTeam = $user->currentTeamRelation;
            if (! $baseTeam) {
                return null;
            }

            return $baseTeam->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function render()
    {
        return view('platform::livewire.terminal.extra-fields');
    }
}
