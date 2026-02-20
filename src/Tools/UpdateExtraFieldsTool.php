<?php

namespace Platform\Core\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreLookup;
use Platform\Core\Traits\HasExtraFields;

class UpdateExtraFieldsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /core/extra-fields - Setzt Extra-Field-Werte für ein beliebiges Model. Unterstützt text, number, boolean, select, lookup und file Felder. Für lookup-Felder nutze core.extra_fields.GET um gültige Werte zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model_type' => [
                    'type' => 'string',
                    'description' => 'Morph-Map-Key des Models (z.B. "hcm_applicant", "hcm_employee").',
                ],
                'model_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Models.',
                ],
                'fields' => [
                    'type' => 'object',
                    'description' => 'Key-Value-Paare der zu setzenden Extra-Fields. Key = field name, Value = neuer Wert. Für boolean-Felder: true/false oder "Ja"/"Nein". Für select-Felder mit Mehrfachauswahl: Array von Strings. Nutze null oder "" um einen Wert zu löschen.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['model_type', 'model_id', 'fields'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $modelType = (string)($arguments['model_type'] ?? '');
            $modelId = (int)($arguments['model_id'] ?? 0);
            $fields = $arguments['fields'] ?? [];

            if ($modelType === '' || $modelId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'model_type und model_id sind erforderlich.');
            }

            if (!is_array($fields) || empty($fields)) {
                return ToolResult::error('VALIDATION_ERROR', 'fields muss ein nicht-leeres Objekt sein.');
            }

            // Normalize array format to key-value format
            // Supports: [{"name": "field", "value": 123}] or [{"id": 3, "value": 123}] or [{"field_id": 3, "value": 123}]
            if (array_is_list($fields)) {
                $normalizedFields = [];
                foreach ($fields as $item) {
                    if (!is_array($item)) continue;

                    $fieldName = $item['name'] ?? $item['key'] ?? null;
                    $value = $item['value'] ?? $item['number'] ?? $item['number_value'] ?? null;

                    // If no name but has id/field_id, we need to resolve it from definitions
                    if (!$fieldName && isset($item['id'])) {
                        $fieldName = '__id_' . $item['id'];
                    }
                    if (!$fieldName && isset($item['field_id'])) {
                        $fieldName = '__id_' . $item['field_id'];
                    }

                    if ($fieldName) {
                        $normalizedFields[$fieldName] = $value;
                    }
                }
                $fields = $normalizedFields;
            }

            $modelClass = Relation::getMorphedModel($modelType);
            if (!$modelClass || !class_exists($modelClass)) {
                return ToolResult::error('VALIDATION_ERROR', "Unbekannter model_type: {$modelType}");
            }

            if (!in_array(HasExtraFields::class, class_uses_recursive($modelClass))) {
                return ToolResult::error('VALIDATION_ERROR', "Model {$modelType} unterstützt keine Extra-Fields.");
            }

            // Context-Scoping: Wenn context_model gesetzt, nur dieses Model erlauben
            $accessError = $this->checkAccess($modelClass, $modelId, $context);
            if ($accessError) {
                return $accessError;
            }

            $model = $modelClass::find($modelId);
            if (!$model) {
                return ToolResult::error('NOT_FOUND', "Model {$modelType} #{$modelId} nicht gefunden.");
            }

            // Team-Scoping: Wenn kein context_model, prüfe team_id
            if (!($context->metadata['context_model'] ?? null) && isset($model->team_id) && $context->team) {
                if ((int)$model->team_id !== (int)$context->team->id) {
                    return ToolResult::error('ACCESS_DENIED', "Model gehört nicht zum aktuellen Team.");
                }
            }

            // Build a set of known field names for validation
            $definitions = $model->getExtraFieldsWithLabels();
            $definitionsByName = [];
            $definitionsById = [];
            foreach ($definitions as $def) {
                $definitionsByName[$def['name']] = $def;
                if (isset($def['id'])) {
                    $definitionsById[$def['id']] = $def;
                }
            }

            // Resolve __id_X placeholders to actual field names
            $resolvedFields = [];
            foreach ($fields as $fieldName => $value) {
                if (str_starts_with($fieldName, '__id_')) {
                    $fieldId = (int) substr($fieldName, 5);
                    if (isset($definitionsById[$fieldId])) {
                        $resolvedFields[$definitionsById[$fieldId]['name']] = $value;
                    } else {
                        $resolvedFields[$fieldName] = $value; // Keep for error message
                    }
                } else {
                    $resolvedFields[$fieldName] = $value;
                }
            }
            $fields = $resolvedFields;

            $updated = [];
            $errors = [];

            foreach ($fields as $fieldName => $value) {
                if (!isset($definitionsByName[$fieldName])) {
                    $errors[] = "Feld '{$fieldName}' existiert nicht.";
                    continue;
                }

                $definition = $definitionsByName[$fieldName];

                // Validierung für Lookup-Felder
                if ($definition['type'] === 'lookup' && $value !== null && $value !== '') {
                    $lookupId = $definition['options']['lookup_id'] ?? null;
                    if ($lookupId) {
                        $lookup = CoreLookup::with('activeValues')->find($lookupId);
                        if ($lookup) {
                            $validValues = $lookup->activeValues->pluck('value')->toArray();
                            $valuesToCheck = is_array($value) ? $value : [$value];
                            $invalidValues = array_diff($valuesToCheck, $validValues);

                            if (!empty($invalidValues)) {
                                $errors[] = "Feld '{$fieldName}': Ungültige Werte: " . implode(', ', $invalidValues) . ". Gültige Werte: " . implode(', ', $validValues);
                                continue;
                            }
                        }
                    }
                }

                // Validierung für Select-Felder
                if ($definition['type'] === 'select' && $value !== null && $value !== '') {
                    $validChoices = $definition['options']['choices'] ?? [];
                    if (!empty($validChoices)) {
                        $valuesToCheck = is_array($value) ? $value : [$value];
                        $invalidValues = array_diff($valuesToCheck, $validChoices);

                        if (!empty($invalidValues)) {
                            $errors[] = "Feld '{$fieldName}': Ungültige Werte: " . implode(', ', $invalidValues) . ". Gültige Werte: " . implode(', ', $validChoices);
                            continue;
                        }
                    }
                }

                $model->setExtraField($fieldName, $value);
                $savedValue = $model->getExtraField($fieldName);
                $expectNull = ($value === null || $value === '');
                if ($expectNull ? $savedValue === null : $savedValue !== null) {
                    $updated[] = $fieldName;
                } else {
                    $errors[] = "Feld '{$fieldName}' konnte nicht gespeichert werden (Definition nicht gefunden oder Kontext-Fehler).";
                }
            }

            $result = [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'updated_fields' => $updated,
                'message' => count($updated) . ' Feld(er) aktualisiert.',
            ];

            if (!empty($errors)) {
                $result['warnings'] = $errors;

                // Provide guidance: show available field names and correct format
                $availableFields = array_map(fn($d) => [
                    'name' => $d['name'],
                    'type' => $d['type'],
                    'label' => $d['label'] ?? $d['name'],
                ], $definitions);

                $result['hint'] = 'Nutze core.extra_fields.GET um verfügbare Felder zu sehen. Format: {"fields": {"feldname": wert}}';
                $result['available_fields'] = $availableFields;
                $result['example'] = [
                    'model_type' => $modelType,
                    'model_id' => $modelId,
                    'fields' => count($availableFields) > 0
                        ? [$availableFields[0]['name'] => $availableFields[0]['type'] === 'number' ? 123 : 'wert']
                        : ['feldname' => 'wert'],
                ];
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Extra-Fields: ' . $e->getMessage());
        }
    }

    private function checkAccess(string $modelClass, int $modelId, ToolContext $context): ?ToolResult
    {
        $contextModel = $context->metadata['context_model'] ?? null;
        $contextModelId = $context->metadata['context_model_id'] ?? null;

        if (!$contextModel || !$contextModelId) {
            return null; // Kein Context-Scoping → Team-Check greift später
        }

        // Resolve morph alias to full class name for comparison
        $contextClass = Relation::getMorphedModel($contextModel) ?? $contextModel;

        if ($modelClass !== $contextClass || (int)$modelId !== (int)$contextModelId) {
            return ToolResult::error('ACCESS_DENIED', "Zugriff nur auf das aktuelle Kontext-Model erlaubt ({$contextModel} #{$contextModelId}).");
        }

        return null;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'extra_fields', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
