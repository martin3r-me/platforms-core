<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ToolRegistryService;

class ToolRegistryBulkUpsertTool implements ToolContract
{
    public function getName(): string
    {
        return 'tool_registry.BULK_POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt oder aktualisiert mehrere Tool-Registry-Einträge atomar (Transaktion). Nur für Admins. Jedes Item benötigt mindestens: name, tier, intent, cost_class.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'description' => 'Array von Tool-Definitionen. Jedes Item muss mindestens name, tier, intent und cost_class enthalten.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Tool-Name (muss im ToolRegistry existieren).'],
                            'kind' => ['type' => 'string'],
                            'module' => ['type' => 'string'],
                            'namespace' => ['type' => 'string'],
                            'tier' => ['type' => 'string'],
                            'intent' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'required_params' => ['type' => 'array'],
                            'optional_params' => ['type' => 'array'],
                            'cost_class' => ['type' => 'string'],
                            'cost_per_call_eur' => ['type' => 'number'],
                            'read_only' => ['type' => 'boolean'],
                            'deprecated' => ['type' => 'boolean'],
                            'successor_name' => ['type' => 'string'],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'requires' => ['type' => 'array'],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User muss authentifiziert sein.');
            }

            // Admin-Check
            $role = $context->user->pivot->role
                ?? ($context->team ? $context->user->teams()->where('teams.id', $context->team->id)->first()?->pivot?->role : null)
                ?? null;

            if (!in_array($role, ['admin', 'owner'], true)) {
                return ToolResult::error('FORBIDDEN', 'Nur Admins dürfen die Tool-Registry bearbeiten.');
            }

            $items = $arguments['items'] ?? [];
            if (empty($items)) {
                return ToolResult::error('VALIDATION_ERROR', 'items darf nicht leer sein.');
            }

            if (count($items) > 100) {
                return ToolResult::error('VALIDATION_ERROR', 'Maximal 100 Items pro Bulk-Operation.');
            }

            $service = app(ToolRegistryService::class);
            $results = $service->bulkUpsert($items);

            $succeeded = count(array_filter($results, fn($r) => $r['ok']));
            $failed = count($results) - $succeeded;

            $summary = array_map(function ($r) {
                $item = ['name' => $r['name'], 'ok' => $r['ok']];
                if (!$r['ok']) {
                    $item['error'] = $r['error'];
                }
                return $item;
            }, $results);

            return ToolResult::success([
                'results' => $summary,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'total' => count($results),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Upsert: ' . $e->getMessage());
        }
    }
}
