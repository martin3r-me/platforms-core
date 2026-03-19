<?php

namespace Platform\Core\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Traits\HasExtraFields;

class ReorderExtraFieldDefinitionsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.definitions.reorder';
    }

    public function getDescription(): string
    {
        return 'POST /core/extra-fields/definitions/reorder - Sortiert Extra-Field-Definitionen in die gewünschte Reihenfolge. Erwartet ein Array von Definition-IDs in der Zielreihenfolge.';
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
                    'description' => 'Optional: Instanz-ID für instanz-spezifische Felder. NULL = globale Felder des Typs.',
                ],
                'definition_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von Definition-IDs in der gewünschten Reihenfolge. Alle Definitionen des Kontexts müssen enthalten sein.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['model_type', 'definition_ids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $modelType = trim((string)($arguments['model_type'] ?? ''));
            $modelId = isset($arguments['model_id']) ? (int)$arguments['model_id'] : null;
            $definitionIds = $arguments['definition_ids'] ?? [];
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);

            if ($modelType === '') {
                return ToolResult::error('VALIDATION_ERROR', 'model_type ist erforderlich.');
            }

            if (empty($definitionIds) || !is_array($definitionIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'definition_ids muss ein nicht-leeres Array von IDs sein.');
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

            $contextType = $modelClass;
            $contextId = $modelId ?: null;

            // Alle Definitionen des Kontexts laden
            $existingDefs = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($contextType, $contextId)
                ->pluck('id')
                ->all();

            // Prüfen ob alle übergebenen IDs existieren
            $unknownIds = array_diff($definitionIds, $existingDefs);
            if (!empty($unknownIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'Unbekannte Definition-IDs: ' . implode(', ', $unknownIds));
            }

            // Prüfen ob alle existierenden IDs enthalten sind
            $missingIds = array_diff($existingDefs, $definitionIds);
            if (!empty($missingIds)) {
                return ToolResult::error('VALIDATION_ERROR', 'Fehlende Definition-IDs (alle müssen enthalten sein): ' . implode(', ', $missingIds));
            }

            // Duplikate prüfen
            if (count($definitionIds) !== count(array_unique($definitionIds))) {
                return ToolResult::error('VALIDATION_ERROR', 'definition_ids enthält Duplikate.');
            }

            // Order aktualisieren
            foreach ($definitionIds as $index => $defId) {
                CoreExtraFieldDefinition::where('id', (int)$defId)
                    ->update(['order' => $index + 1]);
            }

            // Ergebnis laden
            $result = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->forContext($contextType, $contextId)
                ->orderBy('order')
                ->get()
                ->map(fn($def) => [
                    'id' => $def->id,
                    'name' => $def->name,
                    'label' => $def->label,
                    'order' => $def->order,
                ])
                ->all();

            return ToolResult::success([
                'definitions' => $result,
                'message' => count($definitionIds) . ' Definitionen neu sortiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Sortieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'extra_fields', 'definitions', 'reorder'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
