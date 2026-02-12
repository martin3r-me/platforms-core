<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreLookup;

class ListLookupsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.lookups.LIST';
    }

    public function getDescription(): string
    {
        return 'GET /core/extra-fields/lookups - Listet alle Lookup-Auswahllisten für Extra-Felder auf. Lookups sind zentrale Wertelisten (z.B. "Nationalität", "Krankenkasse") die in Extra-Feldern vom Typ "lookup" verwendet werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
                'include_values' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden auch alle Werte der Lookups zurückgegeben. Default: false (nur Anzahl).',
                ],
                'only_active_values' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden nur aktive Werte zurückgegeben. Default: true.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);
            $includeValues = (bool)($arguments['include_values'] ?? false);
            $onlyActiveValues = (bool)($arguments['only_active_values'] ?? true);

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            $query = CoreLookup::forTeam($teamId)->orderBy('label');

            if ($includeValues) {
                $query->with($onlyActiveValues ? 'activeValues' : 'values');
            }

            $lookups = $query->get()->map(function ($lookup) use ($includeValues, $onlyActiveValues) {
                $data = [
                    'id' => $lookup->id,
                    'name' => $lookup->name,
                    'label' => $lookup->label,
                    'description' => $lookup->description,
                    'is_system' => $lookup->is_system,
                    'values_count' => $lookup->values()->count(),
                    'active_values_count' => $lookup->activeValues()->count(),
                ];

                if ($includeValues) {
                    $values = $onlyActiveValues ? $lookup->activeValues : $lookup->values;
                    $data['values'] = $values->map(fn($v) => [
                        'id' => $v->id,
                        'value' => $v->value,
                        'label' => $v->label,
                        'order' => $v->order,
                        'is_active' => $v->is_active,
                        'meta' => $v->meta,
                    ])->toArray();
                }

                return $data;
            });

            return ToolResult::success([
                'team_id' => $teamId,
                'lookups' => $lookups->toArray(),
                'total' => $lookups->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Lookups: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['core', 'lookups', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
