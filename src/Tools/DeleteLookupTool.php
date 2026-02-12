<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Models\CoreLookup;

class DeleteLookupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.lookups.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /core/extra-fields/lookups/{id} - Löscht einen Lookup. System-Lookups und Lookups in Verwendung können nicht gelöscht werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lookup_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu löschenden Lookups.',
                ],
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Erzwingt Löschung auch wenn in Verwendung (außer System-Lookups). Default: false.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['lookup_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $lookupId = (int)($arguments['lookup_id'] ?? 0);
            $force = (bool)($arguments['force'] ?? false);
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);

            if ($lookupId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'lookup_id ist erforderlich.');
            }

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            $lookup = CoreLookup::forTeam($teamId)->where('id', $lookupId)->first();

            if (!$lookup) {
                return ToolResult::error('NOT_FOUND', "Lookup mit ID {$lookupId} nicht gefunden.");
            }

            // System-Lookups können nie gelöscht werden
            if ($lookup->is_system) {
                return ToolResult::error('FORBIDDEN', "System-Lookup '{$lookup->label}' kann nicht gelöscht werden.");
            }

            // Prüfen ob Lookup in Verwendung
            $inUse = CoreExtraFieldDefinition::where('type', 'lookup')
                ->whereJsonContains('options->lookup_id', $lookupId)
                ->exists();

            if ($inUse && !$force) {
                return ToolResult::error(
                    'IN_USE',
                    "Lookup '{$lookup->label}' wird noch in Extra-Field-Definitionen verwendet. Nutze force=true um trotzdem zu löschen."
                );
            }

            $valuesCount = $lookup->values()->count();
            $lookupLabel = $lookup->label;
            $lookupName = $lookup->name;

            // Löschen (cascade durch FK)
            $lookup->delete();

            return ToolResult::success([
                'deleted_id' => $lookupId,
                'deleted_name' => $lookupName,
                'deleted_label' => $lookupLabel,
                'deleted_values_count' => $valuesCount,
                'was_in_use' => $inUse,
                'message' => "Lookup '{$lookupLabel}' mit {$valuesCount} Wert(en) gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Lookups: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'lookups', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'destructive',
            'idempotent' => true,
        ];
    }
}
