<?php

namespace Platform\Core\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Traits\HasExtraFields;

class CreateExtraFieldDefinitionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.definitions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /core/extra-fields/definitions - Erstellt eine neue Extra-Field-Definition für ein Model. Unterstützte Typen: text, number, textarea, boolean, select, lookup, file, phone, regex, address.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model_type' => [
                    'type' => 'string',
                    'description' => 'Morph-Map-Key des Models (z.B. "rec_position", "hcm_employee").',
                ],
                'model_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Instanz-ID für instanz-spezifische Felder. NULL/weggelassen = gilt für alle Instanzen dieses Typs.',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Anzeige-Label des Feldes (z.B. "Geburtsdatum").',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibungstext / Hilfetext für das Feld. Wird als Tooltip oder Hinweis angezeigt.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['text', 'number', 'textarea', 'boolean', 'select', 'lookup', 'file', 'phone', 'regex', 'address'],
                    'description' => 'Feldtyp.',
                ],
                'is_required' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Pflichtfeld? Default: false.',
                ],
                'is_mandatory' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Muss-Feld (nicht löschbar)? Default: false.',
                ],
                'placeholder' => [
                    'type' => 'string',
                    'description' => 'Optional: Platzhaltertext, der im leeren Eingabefeld angezeigt wird. Nur für text, number, textarea, regex.',
                ],
                'options' => [
                    'type' => 'object',
                    'description' => 'Optional: Typ-spezifische Optionen. Select: {choices: ["A","B"], multiple: bool}. Lookup: {lookup_id: int, multiple: bool}. File: {multiple: bool}. Regex: {pattern: "regex", pattern_description: "Beschreibung", pattern_error: "Fehlermeldung"}.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['model_type', 'label', 'type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $modelType = trim((string)($arguments['model_type'] ?? ''));
            $modelId = isset($arguments['model_id']) ? (int)$arguments['model_id'] : null;
            $label = trim((string)($arguments['label'] ?? ''));
            $type = trim((string)($arguments['type'] ?? ''));
            $isRequired = (bool)($arguments['is_required'] ?? false);
            $isMandatory = (bool)($arguments['is_mandatory'] ?? false);
            $options = $arguments['options'] ?? null;
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);

            // Validierung
            if ($modelType === '') {
                return ToolResult::error('VALIDATION_ERROR', 'model_type ist erforderlich.');
            }

            if ($label === '') {
                return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich.');
            }

            $validTypes = array_keys(CoreExtraFieldDefinition::TYPES);
            if (!in_array($type, $validTypes)) {
                return ToolResult::error('VALIDATION_ERROR', "Ungültiger Typ '{$type}'. Erlaubt: " . implode(', ', $validTypes));
            }

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            // Model-Typ validieren
            $modelClass = Relation::getMorphedModel($modelType);
            if (!$modelClass || !class_exists($modelClass)) {
                return ToolResult::error('VALIDATION_ERROR', "Unbekannter model_type: {$modelType}");
            }

            if (!in_array(HasExtraFields::class, class_uses_recursive($modelClass))) {
                return ToolResult::error('VALIDATION_ERROR', "Model {$modelType} unterstützt keine Extra-Fields.");
            }

            // Typ-spezifische Validierung
            if ($type === 'select') {
                $choices = $options['choices'] ?? [];
                if (empty($choices) || !is_array($choices)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Select-Felder benötigen options.choices (Array mit mindestens einem Wert).');
                }
            }

            if ($type === 'lookup') {
                $lookupId = $options['lookup_id'] ?? null;
                if (!$lookupId) {
                    return ToolResult::error('VALIDATION_ERROR', 'Lookup-Felder benötigen options.lookup_id.');
                }
            }

            if ($type === 'regex') {
                $pattern = $options['pattern'] ?? '';
                if (empty($pattern)) {
                    return ToolResult::error('VALIDATION_ERROR', 'Regex-Felder benötigen options.pattern.');
                }
                if (@preg_match('/' . $pattern . '/', '') === false) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültiges reguläres Ausdrucksmuster in options.pattern.');
                }
            }

            // Name aus Label generieren
            $name = Str::slug($label, '_');

            // context_type muss der volle Klassenname sein (wie Livewire/Trait es erwarten)
            $contextType = $modelClass;
            $contextId = $modelId ?: null;

            // Uniqueness-Check
            $exists = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($contextType, $contextId)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Ein Feld mit dem Namen '{$name}' existiert bereits in diesem Kontext.");
            }

            // Höchste order ermitteln
            $maxOrder = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($contextType, $contextId)
                ->max('order') ?? 0;

            // Options aufbauen
            $fieldOptions = null;
            if ($type === 'select') {
                $fieldOptions = [
                    'choices' => $options['choices'],
                    'multiple' => (bool)($options['multiple'] ?? false),
                ];
            } elseif ($type === 'lookup') {
                $fieldOptions = [
                    'lookup_id' => (int)$options['lookup_id'],
                    'multiple' => (bool)($options['multiple'] ?? false),
                ];
            } elseif ($type === 'file') {
                $fieldOptions = [
                    'multiple' => (bool)($options['multiple'] ?? false),
                ];
            } elseif ($type === 'regex') {
                $fieldOptions = [
                    'pattern' => (string)$options['pattern'],
                    'pattern_description' => isset($options['pattern_description']) ? trim((string)$options['pattern_description']) ?: null : null,
                    'pattern_error' => isset($options['pattern_error']) ? trim((string)$options['pattern_error']) ?: null : null,
                ];
            }

            // Placeholder in Options mergen
            $placeholder = isset($arguments['placeholder']) ? trim((string)$arguments['placeholder']) : '';
            if ($placeholder !== '' && in_array($type, ['text', 'number', 'textarea', 'regex'])) {
                $fieldOptions = $fieldOptions ?? [];
                $fieldOptions['placeholder'] = $placeholder;
            }

            $description = isset($arguments['description']) ? trim((string)$arguments['description']) : null;

            $definition = CoreExtraFieldDefinition::create([
                'team_id' => $teamId,
                'created_by_user_id' => $context->user?->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'name' => $name,
                'label' => $label,
                'description' => $description ?: null,
                'type' => $type,
                'is_required' => $isRequired,
                'is_mandatory' => $isMandatory,
                'is_encrypted' => false,
                'order' => $maxOrder + 1,
                'options' => $fieldOptions,
            ]);

            return ToolResult::success([
                'id' => $definition->id,
                'name' => $definition->name,
                'label' => $definition->label,
                'type' => $definition->type,
                'model_type' => $modelType,
                'context_type' => $definition->context_type,
                'context_id' => $definition->context_id,
                'is_required' => $definition->is_required,
                'is_mandatory' => $definition->is_mandatory,
                'order' => $definition->order,
                'options' => $definition->options,
                'team_id' => $definition->team_id,
                'message' => "Extra-Field-Definition '{$definition->label}' ({$definition->type}) erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'extra_fields', 'definitions', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
