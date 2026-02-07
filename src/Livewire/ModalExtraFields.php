<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreExtraFieldValue;

class ModalExtraFields extends Component
{
    public bool $open = false;

    // Kontext
    public ?string $contextType = null;
    public ?int $contextId = null;

    // Tab-Steuerung
    public string $activeTab = 'values'; // 'values', 'definitions'

    // Daten
    public array $definitions = [];
    public array $values = [];

    // Neues Feld Formular
    public array $newField = [
        'name' => '',
        'label' => '',
        'type' => 'text',
        'is_required' => false,
        'is_encrypted' => false,
    ];

    // Bearbeitungs-Modus
    public ?int $editingDefinitionId = null;
    public array $editField = [
        'label' => '',
        'type' => 'text',
        'is_required' => false,
        'is_encrypted' => false,
    ];

    public function mount(): void
    {
        // Initialisierung
    }

    #[On('extrafields')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;

        // Wenn Modal bereits offen ist, Daten neu laden
        if ($this->open && $this->contextType && $this->contextId) {
            $this->loadDefinitions();
            $this->loadValues();
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
        $this->activeTab = 'values';
        $this->editingDefinitionId = null;

        // Daten laden
        if ($this->contextType && $this->contextId) {
            $this->loadDefinitions();
            $this->loadValues();
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->open = false;
        $this->reset('contextType', 'contextId', 'definitions', 'values', 'activeTab', 'editingDefinitionId');
        $this->resetForm();
    }

    public function loadDefinitions(): void
    {
        if (!$this->contextType || !$this->contextId) {
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
                        'is_encrypted' => $def->is_encrypted,
                        'is_global' => $def->isGlobal(),
                        'options' => $def->options,
                        'created_at' => $def->created_at?->format('d.m.Y'),
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            $this->definitions = [];
        }
    }

    public function loadValues(): void
    {
        if (!$this->contextType || !$this->contextId) {
            $this->values = [];
            return;
        }

        if (!class_exists($this->contextType)) {
            $this->values = [];
            return;
        }

        try {
            if (!Schema::hasTable('core_extra_field_values')) {
                $this->values = [];
                return;
            }

            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                $this->values = [];
                return;
            }

            // Prüfe ob Model HasExtraFields Trait verwendet
            if (!method_exists($context, 'extraFieldValues')) {
                $this->values = [];
                return;
            }

            // Werte laden
            $valuesCollection = $context->extraFieldValues()
                ->with('definition')
                ->get()
                ->keyBy('definition_id');

            $this->values = [];
            foreach ($this->definitions as $def) {
                $valueModel = $valuesCollection->get($def['id']);
                $this->values[$def['id']] = $valueModel?->typed_value ?? '';
            }
        } catch (\Exception $e) {
            $this->values = [];
        }
    }

    public function updateValue(int $definitionId): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        if (!class_exists($this->contextType)) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context || !method_exists($context, 'extraFieldValues')) {
                return;
            }

            $definition = CoreExtraFieldDefinition::find($definitionId);
            if (!$definition) {
                return;
            }

            $value = $this->values[$definitionId] ?? null;

            // Validierung für required Felder
            if ($definition->is_required && ($value === null || $value === '')) {
                $this->addError("values.{$definitionId}", 'Dieses Feld ist erforderlich.');
                return;
            }

            // Wert speichern oder aktualisieren
            $fieldValue = $context->extraFieldValues()
                ->where('definition_id', $definitionId)
                ->first();

            if ($value === null || $value === '') {
                // Wert löschen wenn leer
                if ($fieldValue) {
                    $fieldValue->delete();
                }
            } else {
                if (!$fieldValue) {
                    $fieldValue = new CoreExtraFieldValue([
                        'definition_id' => $definitionId,
                        'fieldable_type' => get_class($context),
                        'fieldable_id' => $context->id,
                    ]);
                }

                $fieldValue->setTypedValue($value);
                $fieldValue->save();
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Wert gespeichert.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Speichern.',
            ]);
        }
    }

    public function createDefinition(): void
    {
        $this->validate([
            'newField.label' => ['required', 'string', 'max:255'],
            'newField.type' => ['required', 'string', 'in:text,number,textarea'],
            'newField.is_required' => ['boolean'],
            'newField.is_encrypted' => ['boolean'],
        ]);

        try {
            $teamId = $this->getTeamId();
            if (!$teamId) {
                $this->addError('newField.label', 'Kein Team-Kontext vorhanden.');
                return;
            }

            $user = Auth::user();

            // Name aus Label generieren
            $name = Str::slug($this->newField['label'], '_');

            // Prüfe ob Name bereits existiert
            $exists = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->contextType, null) // Global für diesen Typ
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $this->addError('newField.label', 'Ein Feld mit diesem Namen existiert bereits.');
                return;
            }

            // Höchste order ermitteln
            $maxOrder = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($this->contextType, null)
                ->max('order') ?? 0;

            CoreExtraFieldDefinition::create([
                'team_id' => $teamId,
                'created_by_user_id' => $user->id,
                'context_type' => $this->contextType,
                'context_id' => null, // Global für alle dieses Typs
                'name' => $name,
                'label' => trim($this->newField['label']),
                'type' => $this->newField['type'],
                'is_required' => $this->newField['is_required'] ?? false,
                'is_encrypted' => $this->newField['is_encrypted'] ?? false,
                'order' => $maxOrder + 1,
            ]);

            // Reset Formular
            $this->resetForm();

            // Definitionen neu laden
            $this->loadDefinitions();
            $this->loadValues();

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
        $this->editField = [
            'label' => $definition['label'],
            'type' => $definition['type'],
            'is_required' => $definition['is_required'],
            'is_encrypted' => $definition['is_encrypted'],
        ];
    }

    public function cancelEditDefinition(): void
    {
        $this->editingDefinitionId = null;
        $this->editField = [
            'label' => '',
            'type' => 'text',
            'is_required' => false,
            'is_encrypted' => false,
        ];
    }

    public function saveEditDefinition(): void
    {
        if (!$this->editingDefinitionId) {
            return;
        }

        $this->validate([
            'editField.label' => ['required', 'string', 'max:255'],
            'editField.type' => ['required', 'string', 'in:text,number,textarea'],
            'editField.is_required' => ['boolean'],
            'editField.is_encrypted' => ['boolean'],
        ]);

        try {
            $definition = CoreExtraFieldDefinition::find($this->editingDefinitionId);
            if (!$definition) {
                return;
            }

            $definition->update([
                'label' => trim($this->editField['label']),
                'type' => $this->editField['type'],
                'is_required' => $this->editField['is_required'] ?? false,
                'is_encrypted' => $this->editField['is_encrypted'] ?? false,
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

            // Prüfe ob Werte existieren
            $hasValues = CoreExtraFieldValue::where('definition_id', $definitionId)->exists();
            if ($hasValues) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Feld hat Werte und kann nicht gelöscht werden.',
                ]);
                return;
            }

            $definition->delete();

            $this->loadDefinitions();
            $this->loadValues();

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

    protected function resetForm(): void
    {
        $this->newField = [
            'name' => '',
            'label' => '',
            'type' => 'text',
            'is_required' => false,
            'is_encrypted' => false,
        ];
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

            $rootTeam = method_exists($baseTeam, 'getRootTeam') ? $baseTeam->getRootTeam() : $baseTeam;
            return $rootTeam?->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function render()
    {
        return view('platform::livewire.modal-extra-fields');
    }
}
