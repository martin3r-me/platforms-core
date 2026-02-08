<?php

namespace Platform\Core\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Traits\HasExtraFields;

class UpdateExtraFieldsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /core/extra-fields - Setzt Extra-Field-Werte für ein beliebiges Model. Nutze core.extra_fields.GET um verfügbare Felder und deren Namen zu sehen.';
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

            $modelClass = Relation::getMorphedModel($modelType);
            if (!$modelClass || !class_exists($modelClass)) {
                return ToolResult::error('VALIDATION_ERROR', "Unbekannter model_type: {$modelType}");
            }

            if (!in_array(HasExtraFields::class, class_uses_recursive($modelClass))) {
                return ToolResult::error('VALIDATION_ERROR', "Model {$modelType} unterstützt keine Extra-Fields.");
            }

            $model = $modelClass::find($modelId);
            if (!$model) {
                return ToolResult::error('NOT_FOUND', "Model {$modelType} #{$modelId} nicht gefunden.");
            }

            // Build a set of known field names for validation
            $definitions = $model->getExtraFieldsWithLabels();
            $knownFields = array_column($definitions, 'name');

            $updated = [];
            $errors = [];

            foreach ($fields as $fieldName => $value) {
                if (!in_array($fieldName, $knownFields, true)) {
                    $errors[] = "Feld '{$fieldName}' existiert nicht.";
                    continue;
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
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Extra-Fields: ' . $e->getMessage());
        }
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
