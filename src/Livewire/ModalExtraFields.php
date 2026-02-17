<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Core\Models\CoreLookup;
use Platform\Core\Models\CoreLookupValue;
use Platform\Core\Services\ExtraFieldCircularDependencyDetector;
use Platform\Core\Services\ExtraFieldConditionEvaluator;

class ModalExtraFields extends Component
{
    public bool $open = false;

    // Tab System (main modal tabs)
    public string $activeTab = 'fields'; // 'fields' | 'lookups'

    // Edit Field Tab System
    public string $editFieldTab = 'basis'; // 'basis' | 'options' | 'conditions' | 'verification' | 'autofill'

    // Kontext
    public ?string $contextType = null;
    public ?int $contextId = null;

    // Daten
    public array $definitions = [];

    // Lookups
    public array $lookups = [];
    public ?int $editingLookupId = null;
    public array $newLookup = [
        'name' => '',
        'label' => '',
        'description' => '',
    ];
    public array $editLookup = [
        'label' => '',
        'description' => '',
    ];

    // Lookup Values
    public array $lookupValues = [];
    public ?int $selectedLookupId = null;
    public string $newLookupValueText = '';
    public string $newLookupValueLabel = '';

    // Neues Feld Formular
    public array $newField = [
        'name' => '',
        'label' => '',
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
        'visibility' => [
            'enabled' => false,
            'logic' => 'AND',
            'groups' => [],
        ],
    ];

    // Bearbeitungs-Modus
    public ?int $editingDefinitionId = null;
    public array $editField = [
        'label' => '',
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
        'visibility' => [
            'enabled' => false,
            'logic' => 'AND',
            'groups' => [],
        ],
    ];

    // Temporäre Option für Eingabe
    public string $newOptionText = '';
    public string $editOptionText = '';

    public function mount(): void
    {
        // Initialisierung
    }

    #[On('extrafields')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) && $payload['context_id'] !== null
            ? (int) $payload['context_id']
            : null;

        // Wenn Modal bereits offen ist, Daten neu laden
        if ($this->open && $this->contextType) {
            $this->loadDefinitions();
        }
    }

    #[On('extrafields:open')]
    public function openModal(): void
    {
        if (!Auth::check() || !Auth::user()->currentTeamRelation) {
            return;
        }

        // Reset
        $this->resetForm();
        $this->editingDefinitionId = null;
        $this->activeTab = 'fields';

        // Daten laden
        if ($this->contextType) {
            $this->loadDefinitions();
        }
        $this->loadLookups();

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->open = false;
        $this->reset('contextType', 'contextId', 'definitions', 'editingDefinitionId', 'lookups', 'editingLookupId', 'selectedLookupId', 'lookupValues', 'activeTab', 'editFieldTab');
        $this->resetForm();
        $this->resetLookupForm();
    }

    public function loadDefinitions(): void
    {
        if (!$this->contextType) {
            $this->definitions = [];
            return;
        }

        if (!class_exists($this->contextType)) {
            $this->definitions = [];
            return;
        }

        // Prüfe ob Datenbank-Tabellen existieren
        try {
            if (!Schema::hasTable('core_extra_field_definitions')) {
                $this->definitions = [];
                return;
            }
        } catch (\Exception $e) {
            $this->definitions = [];
            return;
        }

        try {
            $teamId = $this->getTeamId();
            if (!$teamId) {
                $this->definitions = [];
                return;
            }

            $this->definitions = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->contextType, $this->contextId)
                ->orderBy('order')
                ->orderBy('label')
                ->get()
                ->map(function ($def) {
                    return [
                        'id' => $def->id,
                        'name' => $def->name,
                        'label' => $def->label,
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
            $this->definitions = [];
        }
    }

    public function addNewOption(): void
    {
        $text = trim($this->newOptionText);
        if ($text !== '' && !in_array($text, $this->newField['options'])) {
            $this->newField['options'][] = $text;
        }
        $this->newOptionText = '';
    }

    public function removeNewOption(int $index): void
    {
        unset($this->newField['options'][$index]);
        $this->newField['options'] = array_values($this->newField['options']);
    }

    public function addEditOption(): void
    {
        $text = trim($this->editOptionText);
        if ($text !== '' && !in_array($text, $this->editField['options'])) {
            $this->editField['options'][] = $text;
        }
        $this->editOptionText = '';
    }

    public function removeEditOption(int $index): void
    {
        unset($this->editField['options'][$index]);
        $this->editField['options'] = array_values($this->editField['options']);
    }

    public function createDefinition(): void
    {
        $rules = [
            'newField.label' => ['required', 'string', 'max:255'],
            'newField.type' => ['required', 'string', 'in:text,number,textarea,boolean,select,lookup,file'],
            'newField.is_required' => ['boolean'],
            'newField.is_mandatory' => ['boolean'],
            'newField.is_encrypted' => ['boolean'],
        ];

        // Select braucht mindestens eine Option
        if ($this->newField['type'] === 'select') {
            $rules['newField.options'] = ['required', 'array', 'min:1'];
        }

        // Lookup braucht eine Lookup-ID
        if ($this->newField['type'] === 'lookup') {
            $rules['newField.lookup_id'] = ['required', 'integer', 'exists:core_lookups,id'];
        }

        $this->validate($rules);

        try {
            $teamId = $this->getTeamId();
            if (!$teamId) {
                $this->addError('newField.label', 'Kein Team-Kontext vorhanden.');
                return;
            }

            $user = Auth::user();

            // Name aus Label generieren
            $name = Str::slug($this->newField['label'], '_');

            // Prüfe ob Name bereits existiert (im selben Kontext)
            $exists = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->contextType, $this->contextId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $this->addError('newField.label', 'Ein Feld mit diesem Namen existiert bereits.');
                return;
            }

            // Höchste order ermitteln (im selben Kontext)
            $maxOrder = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->contextType, $this->contextId)
                ->max('order') ?? 0;

            // Options für Select-Felder
            $options = null;
            if ($this->newField['type'] === 'select') {
                $options = [
                    'choices' => $this->newField['options'],
                    'multiple' => $this->newField['is_multiple'] ?? false,
                ];
            }

            // Options für Lookup-Felder
            if ($this->newField['type'] === 'lookup') {
                $options = [
                    'lookup_id' => (int) $this->newField['lookup_id'],
                    'multiple' => $this->newField['is_multiple'] ?? false,
                ];
            }

            // Options für File-Felder
            if ($this->newField['type'] === 'file') {
                $options = [
                    'multiple' => $this->newField['is_multiple'] ?? false,
                ];
            }

            CoreExtraFieldDefinition::create([
                'team_id' => $teamId,
                'created_by_user_id' => $user->id,
                'context_type' => $this->contextType,
                'context_id' => $this->contextId, // Spezifisch für diesen Kontext (oder null für global)
                'name' => $name,
                'label' => trim($this->newField['label']),
                'type' => $this->newField['type'],
                'is_required' => $this->newField['is_required'] ?? false,
                'is_mandatory' => $this->newField['is_mandatory'] ?? false,
                'is_encrypted' => $this->newField['is_encrypted'] ?? false,
                'order' => $maxOrder + 1,
                'options' => $options,
                'verify_by_llm' => $this->newField['type'] === 'file' && ($this->newField['verify_by_llm'] ?? false),
                'verify_instructions' => $this->newField['type'] === 'file' ? ($this->newField['verify_instructions'] ?? null) : null,
                'auto_fill_source' => !empty($this->newField['auto_fill_source']) ? $this->newField['auto_fill_source'] : null,
                'auto_fill_prompt' => !empty($this->newField['auto_fill_prompt']) ? $this->newField['auto_fill_prompt'] : null,
            ]);

            // Reset Formular
            $this->resetForm();

            // Definitionen neu laden
            $this->loadDefinitions();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Feld erstellt.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Erstellen des Feldes.',
            ]);
        }
    }

    public function startEditDefinition(int $definitionId): void
    {
        $definition = collect($this->definitions)->firstWhere('id', $definitionId);
        if (!$definition) {
            return;
        }

        $this->editingDefinitionId = $definitionId;
        $this->editFieldTab = 'basis';

        // Load visibility config from dedicated column
        $visibility = $definition['visibility_config'] ?? ExtraFieldConditionEvaluator::createEmptyConfig();

        $this->editField = [
            'label' => $definition['label'],
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
            'visibility' => $visibility,
        ];
        $this->editOptionText = '';
    }

    public function cancelEditDefinition(): void
    {
        $this->editingDefinitionId = null;
        $this->editFieldTab = 'basis';
        $this->editField = [
            'label' => '',
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
            'visibility' => ExtraFieldConditionEvaluator::createEmptyConfig(),
        ];
        $this->editOptionText = '';
    }

    public function saveEditDefinition(): void
    {
        if (!$this->editingDefinitionId) {
            return;
        }

        $rules = [
            'editField.label' => ['required', 'string', 'max:255'],
            'editField.type' => ['required', 'string', 'in:text,number,textarea,boolean,select,lookup,file'],
            'editField.is_required' => ['boolean'],
            'editField.is_mandatory' => ['boolean'],
            'editField.is_encrypted' => ['boolean'],
        ];

        // Select braucht mindestens eine Option
        if ($this->editField['type'] === 'select') {
            $rules['editField.options'] = ['required', 'array', 'min:1'];
        }

        // Lookup braucht eine Lookup-ID
        if ($this->editField['type'] === 'lookup') {
            $rules['editField.lookup_id'] = ['required', 'integer', 'exists:core_lookups,id'];
        }

        $this->validate($rules);

        try {
            $definition = CoreExtraFieldDefinition::find($this->editingDefinitionId);
            if (!$definition) {
                return;
            }

            // Build options array based on field type
            $options = match ($this->editField['type']) {
                'select' => [
                    'choices' => $this->editField['options'],
                    'multiple' => $this->editField['is_multiple'] ?? false,
                ],
                'lookup' => [
                    'lookup_id' => (int) $this->editField['lookup_id'],
                    'multiple' => $this->editField['is_multiple'] ?? false,
                ],
                'file' => [
                    'multiple' => $this->editField['is_multiple'] ?? false,
                ],
                default => [],
            };

            // Handle visibility config
            $visibility = $this->editField['visibility'] ?? ExtraFieldConditionEvaluator::createEmptyConfig();
            $visibilityConfig = ($visibility['enabled'] ?? false) ? $visibility : null;

            // Check for circular dependencies if visibility is enabled
            if ($visibilityConfig) {
                $detector = new ExtraFieldCircularDependencyDetector();
                $cycle = $detector->detectCycle(
                    $definition->name,
                    $visibilityConfig,
                    $this->definitions
                );

                if ($cycle !== null) {
                    $fieldLabels = [];
                    foreach ($this->definitions as $def) {
                        $fieldLabels[$def['name']] = $def['label'];
                    }
                    $fieldLabels[$definition->name] = trim($this->editField['label']);
                    $cycleDescription = $detector->describeCycle($cycle, $fieldLabels);

                    $this->addError('editField.visibility', "Zirkuläre Abhängigkeit erkannt: {$cycleDescription}");
                    return;
                }
            }

            $definition->update([
                'label' => trim($this->editField['label']),
                'type' => $this->editField['type'],
                'is_required' => $this->editField['is_required'] ?? false,
                'is_mandatory' => $this->editField['is_mandatory'] ?? false,
                'is_encrypted' => $this->editField['is_encrypted'] ?? false,
                'options' => $options,
                'visibility_config' => $visibilityConfig,
                'verify_by_llm' => $this->editField['type'] === 'file' && ($this->editField['verify_by_llm'] ?? false),
                'verify_instructions' => $this->editField['type'] === 'file' ? ($this->editField['verify_instructions'] ?? null) : null,
                'auto_fill_source' => !empty($this->editField['auto_fill_source']) ? $this->editField['auto_fill_source'] : null,
                'auto_fill_prompt' => !empty($this->editField['auto_fill_prompt']) ? $this->editField['auto_fill_prompt'] : null,
            ]);

            $this->cancelEditDefinition();
            $this->loadDefinitions();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Feld aktualisiert.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Aktualisieren.',
            ]);
        }
    }

    public function deleteDefinition(int $definitionId): void
    {
        try {
            $definition = CoreExtraFieldDefinition::find($definitionId);
            if (!$definition) {
                return;
            }

            // Werte mit löschen (cascade)
            CoreExtraFieldValue::where('definition_id', $definitionId)->delete();

            $definition->delete();

            $this->loadDefinitions();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Feld gelöscht.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Löschen.',
            ]);
        }
    }

    public function getContextLabelProperty(): ?string
    {
        if (!$this->contextType || !$this->contextId) {
            return null;
        }

        if (!class_exists($this->contextType)) {
            return null;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                return null;
            }

            // Versuche verschiedene Methoden für Label
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

            return class_basename($this->contextType) . ' #' . $this->contextId;
        } catch (\Exception $e) {
            return class_basename($this->contextType) . ' #' . $this->contextId;
        }
    }

    public function getContextBreadcrumbProperty(): array
    {
        if (!$this->contextType || !$this->contextId) {
            return [];
        }

        return [
            [
                'type' => class_basename($this->contextType),
                'label' => $this->contextLabel,
            ],
        ];
    }

    public function getAvailableTypesProperty(): array
    {
        return CoreExtraFieldDefinition::TYPES;
    }

    public function getAutoFillSourcesProperty(): array
    {
        return CoreExtraFieldDefinition::AUTO_FILL_SOURCES;
    }

    public function getTypeDescriptionsProperty(): array
    {
        return CoreExtraFieldDefinition::TYPE_DESCRIPTIONS;
    }

    public function getAutoFillSourceDescriptionsProperty(): array
    {
        return CoreExtraFieldDefinition::AUTO_FILL_SOURCE_DESCRIPTIONS;
    }

    protected function resetForm(): void
    {
        $this->newField = [
            'name' => '',
            'label' => '',
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
            'visibility' => ExtraFieldConditionEvaluator::createEmptyConfig(),
        ];
        $this->newOptionText = '';
    }

    protected function resetLookupForm(): void
    {
        $this->newLookup = [
            'name' => '',
            'label' => '',
            'description' => '',
        ];
        $this->editLookup = [
            'label' => '',
            'description' => '',
        ];
        $this->editingLookupId = null;
        $this->selectedLookupId = null;
        $this->lookupValues = [];
        $this->newLookupValueText = '';
        $this->newLookupValueLabel = '';
    }

    protected function getTeamId(): ?int
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return null;
            }

            $baseTeam = $user->currentTeamRelation;
            if (!$baseTeam) {
                return null;
            }

            return $baseTeam->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    // ==========================================
    // Lookup Management
    // ==========================================

    public function loadLookups(): void
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            $this->lookups = [];
            return;
        }

        try {
            if (!Schema::hasTable('core_lookups')) {
                $this->lookups = [];
                return;
            }

            $this->lookups = CoreLookup::forTeam($teamId)
                ->orderBy('label')
                ->get()
                ->map(fn($lookup) => [
                    'id' => $lookup->id,
                    'name' => $lookup->name,
                    'label' => $lookup->label,
                    'description' => $lookup->description,
                    'is_system' => $lookup->is_system,
                    'values_count' => $lookup->values()->count(),
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->lookups = [];
        }
    }

    public function createLookup(): void
    {
        $this->validate([
            'newLookup.label' => ['required', 'string', 'max:255'],
        ]);

        $teamId = $this->getTeamId();
        if (!$teamId) {
            $this->addError('newLookup.label', 'Kein Team-Kontext vorhanden.');
            return;
        }

        // Name aus Label generieren
        $name = Str::slug($this->newLookup['label'], '_');

        // Prüfe ob Name bereits existiert
        $exists = CoreLookup::forTeam($teamId)->where('name', $name)->exists();
        if ($exists) {
            $this->addError('newLookup.label', 'Ein Lookup mit diesem Namen existiert bereits.');
            return;
        }

        try {
            CoreLookup::create([
                'team_id' => $teamId,
                'created_by_user_id' => Auth::id(),
                'name' => $name,
                'label' => trim($this->newLookup['label']),
                'description' => trim($this->newLookup['description']) ?: null,
                'is_system' => false,
            ]);

            $this->newLookup = ['name' => '', 'label' => '', 'description' => ''];
            $this->loadLookups();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Lookup erstellt.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Erstellen.',
            ]);
        }
    }

    public function startEditLookup(int $lookupId): void
    {
        $lookup = collect($this->lookups)->firstWhere('id', $lookupId);
        if (!$lookup) {
            return;
        }

        $this->editingLookupId = $lookupId;
        $this->editLookup = [
            'label' => $lookup['label'],
            'description' => $lookup['description'] ?? '',
        ];
    }

    public function cancelEditLookup(): void
    {
        $this->editingLookupId = null;
        $this->editLookup = ['label' => '', 'description' => ''];
    }

    public function saveEditLookup(): void
    {
        if (!$this->editingLookupId) {
            return;
        }

        $this->validate([
            'editLookup.label' => ['required', 'string', 'max:255'],
        ]);

        try {
            $lookup = CoreLookup::find($this->editingLookupId);
            if (!$lookup) {
                return;
            }

            $lookup->update([
                'label' => trim($this->editLookup['label']),
                'description' => trim($this->editLookup['description']) ?: null,
            ]);

            $this->cancelEditLookup();
            $this->loadLookups();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Lookup aktualisiert.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Aktualisieren.',
            ]);
        }
    }

    public function deleteLookup(int $lookupId): void
    {
        try {
            $lookup = CoreLookup::find($lookupId);
            if (!$lookup) {
                return;
            }

            if ($lookup->is_system) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'System-Lookups können nicht gelöscht werden.',
                ]);
                return;
            }

            // Prüfe ob Lookup in Verwendung
            $inUse = CoreExtraFieldDefinition::where('type', 'lookup')
                ->where('options->lookup_id', $lookupId)
                ->exists();

            if ($inUse) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Lookup wird noch verwendet und kann nicht gelöscht werden.',
                ]);
                return;
            }

            $lookup->delete();

            if ($this->selectedLookupId === $lookupId) {
                $this->selectedLookupId = null;
                $this->lookupValues = [];
            }

            $this->loadLookups();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Lookup gelöscht.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Löschen.',
            ]);
        }
    }

    // ==========================================
    // Lookup Values Management
    // ==========================================

    public function selectLookup(int $lookupId): void
    {
        $this->selectedLookupId = $lookupId;
        $this->loadLookupValues();
    }

    public function deselectLookup(): void
    {
        $this->selectedLookupId = null;
        $this->lookupValues = [];
        $this->newLookupValueText = '';
        $this->newLookupValueLabel = '';
    }

    public function loadLookupValues(): void
    {
        if (!$this->selectedLookupId) {
            $this->lookupValues = [];
            return;
        }

        try {
            $this->lookupValues = CoreLookupValue::where('lookup_id', $this->selectedLookupId)
                ->orderBy('order')
                ->orderBy('label')
                ->get()
                ->map(fn($v) => [
                    'id' => $v->id,
                    'value' => $v->value,
                    'label' => $v->label,
                    'order' => $v->order,
                    'is_active' => $v->is_active,
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->lookupValues = [];
        }
    }

    public function addLookupValue(): void
    {
        if (!$this->selectedLookupId) {
            return;
        }

        $label = trim($this->newLookupValueLabel);
        $value = trim($this->newLookupValueText) ?: $label;

        if ($label === '') {
            return;
        }

        // Prüfe ob Value bereits existiert
        $exists = CoreLookupValue::where('lookup_id', $this->selectedLookupId)
            ->where('value', $value)
            ->exists();

        if ($exists) {
            $this->addError('newLookupValueText', 'Dieser Wert existiert bereits.');
            return;
        }

        try {
            $maxOrder = CoreLookupValue::where('lookup_id', $this->selectedLookupId)->max('order') ?? 0;

            CoreLookupValue::create([
                'lookup_id' => $this->selectedLookupId,
                'value' => $value,
                'label' => $label,
                'order' => $maxOrder + 1,
                'is_active' => true,
            ]);

            $this->newLookupValueText = '';
            $this->newLookupValueLabel = '';
            $this->loadLookupValues();
            $this->loadLookups(); // Update count
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Hinzufügen.',
            ]);
        }
    }

    public function toggleLookupValue(int $valueId): void
    {
        try {
            $value = CoreLookupValue::find($valueId);
            if ($value) {
                $value->update(['is_active' => !$value->is_active]);
                $this->loadLookupValues();
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    public function deleteLookupValue(int $valueId): void
    {
        try {
            CoreLookupValue::where('id', $valueId)->delete();
            $this->loadLookupValues();
            $this->loadLookups(); // Update count
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Löschen.',
            ]);
        }
    }

    public function moveLookupValueUp(int $valueId): void
    {
        $this->moveLookupValue($valueId, -1);
    }

    public function moveLookupValueDown(int $valueId): void
    {
        $this->moveLookupValue($valueId, 1);
    }

    protected function moveLookupValue(int $valueId, int $direction): void
    {
        $values = collect($this->lookupValues);
        $currentIndex = $values->search(fn($v) => $v['id'] === $valueId);

        if ($currentIndex === false) {
            return;
        }

        $newIndex = $currentIndex + $direction;
        if ($newIndex < 0 || $newIndex >= $values->count()) {
            return;
        }

        // Swap order values
        $currentValue = CoreLookupValue::find($valueId);
        $swapValue = CoreLookupValue::find($values[$newIndex]['id']);

        if ($currentValue && $swapValue) {
            $tempOrder = $currentValue->order;
            $currentValue->update(['order' => $swapValue->order]);
            $swapValue->update(['order' => $tempOrder]);
            $this->loadLookupValues();
        }
    }

    public function getAvailableLookupsProperty(): array
    {
        return $this->lookups;
    }

    public function getSelectedLookupProperty(): ?array
    {
        if (!$this->selectedLookupId) {
            return null;
        }
        return collect($this->lookups)->firstWhere('id', $this->selectedLookupId);
    }

    // ==========================================
    // Condition Builder Methods
    // ==========================================

    /**
     * Get available fields for conditions (excluding the current field being edited)
     */
    public function getConditionFieldsProperty(): array
    {
        $fields = [];
        foreach ($this->definitions as $def) {
            // Exclude the field being edited
            if ($def['id'] === $this->editingDefinitionId) {
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

    /**
     * Get operators for a specific field type
     */
    public function getOperatorsForField(string $fieldName): array
    {
        $field = collect($this->definitions)->firstWhere('name', $fieldName);
        if (!$field) {
            return [];
        }

        return ExtraFieldConditionEvaluator::getOperatorsForType($field['type']);
    }

    /**
     * Toggle visibility enabled
     */
    public function toggleVisibilityEnabled(): void
    {
        $this->editField['visibility']['enabled'] = !($this->editField['visibility']['enabled'] ?? false);

        // Initialize with one group if enabling
        if ($this->editField['visibility']['enabled'] && empty($this->editField['visibility']['groups'])) {
            $this->addConditionGroup();
        }
    }

    /**
     * Set the main visibility logic
     */
    public function setVisibilityLogic(string $logic): void
    {
        $this->editField['visibility']['logic'] = $logic;
    }

    /**
     * Add a new condition group
     */
    public function addConditionGroup(): void
    {
        $this->editField['visibility']['groups'][] = ExtraFieldConditionEvaluator::createEmptyGroup();
    }

    /**
     * Remove a condition group
     */
    public function removeConditionGroup(int $groupIndex): void
    {
        unset($this->editField['visibility']['groups'][$groupIndex]);
        $this->editField['visibility']['groups'] = array_values($this->editField['visibility']['groups']);

        // Disable visibility if no groups left
        if (empty($this->editField['visibility']['groups'])) {
            $this->editField['visibility']['enabled'] = false;
        }
    }

    /**
     * Set group logic (AND/OR)
     */
    public function setGroupLogic(int $groupIndex, string $logic): void
    {
        if (isset($this->editField['visibility']['groups'][$groupIndex])) {
            $this->editField['visibility']['groups'][$groupIndex]['logic'] = $logic;
        }
    }

    /**
     * Add a condition to a group
     */
    public function addCondition(int $groupIndex): void
    {
        if (isset($this->editField['visibility']['groups'][$groupIndex])) {
            $this->editField['visibility']['groups'][$groupIndex]['conditions'][] = ExtraFieldConditionEvaluator::createEmptyCondition();
        }
    }

    /**
     * Remove a condition from a group
     */
    public function removeCondition(int $groupIndex, int $conditionIndex): void
    {
        if (isset($this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            unset($this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]);
            $this->editField['visibility']['groups'][$groupIndex]['conditions'] = array_values(
                $this->editField['visibility']['groups'][$groupIndex]['conditions']
            );
        }
    }

    /**
     * Update a condition's field
     */
    public function updateConditionField(int $groupIndex, int $conditionIndex, string $fieldName): void
    {
        if (isset($this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['field'] = $fieldName;

            // Reset operator to first available for this field type
            $operators = $this->getOperatorsForField($fieldName);
            if (!empty($operators)) {
                $firstOperator = array_key_first($operators);
                $this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['operator'] = $firstOperator;
            }

            // Reset value
            $this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = null;
        }
    }

    /**
     * Update a condition's operator
     */
    public function updateConditionOperator(int $groupIndex, int $conditionIndex, string $operator): void
    {
        if (isset($this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['operator'] = $operator;

            // Reset value if operator doesn't require one
            $operatorMeta = ExtraFieldConditionEvaluator::OPERATORS[$operator] ?? null;
            if ($operatorMeta && !($operatorMeta['requiresValue'] ?? true)) {
                $this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = null;
            }
        }
    }

    /**
     * Update a condition's value
     */
    public function updateConditionValue(int $groupIndex, int $conditionIndex, mixed $value): void
    {
        if (isset($this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex])) {
            $this->editField['visibility']['groups'][$groupIndex]['conditions'][$conditionIndex]['value'] = $value;
        }
    }

    /**
     * Get human-readable visibility description
     */
    public function getVisibilityDescriptionProperty(): string
    {
        if (!($this->editField['visibility']['enabled'] ?? false)) {
            return 'Immer sichtbar';
        }

        $fieldLabels = [];
        foreach ($this->definitions as $def) {
            $fieldLabels[$def['name']] = $def['label'];
        }

        $evaluator = new ExtraFieldConditionEvaluator();
        return $evaluator->toHumanReadable($this->editField['visibility'], $fieldLabels);
    }

    /**
     * Get all operators for the condition builder
     */
    public function getAllOperatorsProperty(): array
    {
        return ExtraFieldConditionEvaluator::getAllOperators();
    }

    public function render()
    {
        return view('platform::livewire.modal-extra-fields');
    }
}
