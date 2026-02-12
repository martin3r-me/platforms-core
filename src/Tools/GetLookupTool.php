<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreLookup;

class GetLookupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.lookups.GET';
    }

    public function getDescription(): string
    {
        return 'GET /core/extra-fields/lookups/{id} - Ruft einen Lookup mit allen Werten ab. Nutze lookup_id oder name als Parameter.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lookup_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Lookups. Alternativ kann "name" verwendet werden.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name/Slug des Lookups (z.B. "nationalitaet"). Alternativ zu lookup_id.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
                'only_active_values' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden nur aktive Werte zurückgegeben. Default: false (alle).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $lookupId = isset($arguments['lookup_id']) ? (int)$arguments['lookup_id'] : null;
            $name = isset($arguments['name']) ? (string)$arguments['name'] : null;
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);
            $onlyActiveValues = (bool)($arguments['only_active_values'] ?? false);

            if ($lookupId === null && $name === null) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder lookup_id oder name muss angegeben werden.');
            }

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            $query = CoreLookup::forTeam($teamId);

            if ($lookupId !== null) {
                $query->where('id', $lookupId);
            } else {
                $query->where('name', $name);
            }

            $lookup = $query->first();

            if (!$lookup) {
                $identifier = $lookupId !== null ? "ID {$lookupId}" : "Name '{$name}'";
                return ToolResult::error('NOT_FOUND', "Lookup mit {$identifier} nicht gefunden.");
            }

            $values = $onlyActiveValues ? $lookup->activeValues : $lookup->values;

            return ToolResult::success([
                'id' => $lookup->id,
                'name' => $lookup->name,
                'label' => $lookup->label,
                'description' => $lookup->description,
                'is_system' => $lookup->is_system,
                'team_id' => $lookup->team_id,
                'created_at' => $lookup->created_at?->toIso8601String(),
                'updated_at' => $lookup->updated_at?->toIso8601String(),
                'values' => $values->map(fn($v) => [
                    'id' => $v->id,
                    'value' => $v->value,
                    'label' => $v->label,
                    'order' => $v->order,
                    'is_active' => $v->is_active,
                    'meta' => $v->meta,
                ])->toArray(),
                'values_count' => $lookup->values()->count(),
                'active_values_count' => $lookup->activeValues()->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Lookups: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['core', 'lookups', 'get'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
