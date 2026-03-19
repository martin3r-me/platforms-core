<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreExtraFieldDefinition;

class DeleteExtraFieldDefinitionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.definitions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /core/extra-fields/definitions/{id} - Löscht eine Extra-Field-Definition. Zugehörige Werte werden per FK-Cascade mitgelöscht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'definition_id' => [
                    'type' => 'integer',
                    'description' => 'ID der zu löschenden Definition.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['definition_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $definitionId = (int)($arguments['definition_id'] ?? 0);
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);

            if ($definitionId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'definition_id ist erforderlich.');
            }

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            $definition = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->where('id', $definitionId)
                ->first();

            if (!$definition) {
                return ToolResult::error('NOT_FOUND', "Definition mit ID {$definitionId} nicht gefunden.");
            }

            // Mandatory-Felder können nicht gelöscht werden
            if ($definition->is_mandatory) {
                return ToolResult::error('FORBIDDEN', "Pflicht-Definition '{$definition->label}' kann nicht gelöscht werden (is_mandatory=true).");
            }

            $valuesCount = $definition->values()->count();
            $defLabel = $definition->label;
            $defName = $definition->name;
            $defType = $definition->type;
            $defContextType = $definition->context_type;

            // Löschen (Values werden per FK-Cascade gelöscht)
            $definition->delete();

            return ToolResult::success([
                'deleted_id' => $definitionId,
                'deleted_name' => $defName,
                'deleted_label' => $defLabel,
                'deleted_type' => $defType,
                'deleted_context_type' => $defContextType,
                'deleted_values_count' => $valuesCount,
                'message' => "Definition '{$defLabel}' ({$defType}) mit {$valuesCount} Wert(en) gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'extra_fields', 'definitions', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'destructive',
            'idempotent' => true,
        ];
    }
}
