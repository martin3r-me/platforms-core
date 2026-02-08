<?php

namespace Platform\Core\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Traits\HasExtraFields;

class GetExtraFieldsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.GET';
    }

    public function getDescription(): string
    {
        return 'GET /core/extra-fields - Liest Extra-Field-Definitionen und -Werte für ein beliebiges Model (z.B. hcm_applicant, hcm_employee). Gibt name, label, type, value, is_required, options zurück.';
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
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['model_type', 'model_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $modelType = (string)($arguments['model_type'] ?? '');
            $modelId = (int)($arguments['model_id'] ?? 0);

            if ($modelType === '' || $modelId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'model_type und model_id sind erforderlich.');
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

            $fields = $model->getExtraFieldsWithLabels();

            return ToolResult::success([
                'model_type' => $modelType,
                'model_id' => $modelId,
                'fields' => $fields,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Extra-Fields: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['core', 'extra_fields', 'get'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
