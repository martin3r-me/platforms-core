<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ToolRegistryService;

class ToolRegistryGetTool implements ToolContract
{
    public function getName(): string
    {
        return 'tool_registry.GET';
    }

    public function getDescription(): string
    {
        return 'Gibt detaillierte Informationen zu einem einzelnen Tool oder listet Tools eines Namespaces/Tiers auf. Für Einzelabfragen: name angeben. Für Listen: namespace und/oder tier angeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Exakter Tool-Name (z.B. "obsidian.files.PUT"). Gibt volle Details zurück.',
                ],
                'namespace' => [
                    'type' => 'string',
                    'description' => 'Namespace zum Auflisten (z.B. "obsidian", "planner").',
                ],
                'tier' => [
                    'type' => 'string',
                    'description' => 'Tier zum Filtern: always_on, common, specialized, hidden.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Ergebnisse für Listen (Standard: 20).',
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Offset für Paginierung (Standard: 0).',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User muss authentifiziert sein.');
            }

            $name = $arguments['name'] ?? null;

            // Einzel-Abfrage
            if ($name) {
                $service = app(ToolRegistryService::class);
                $result = $service->get($name);

                if (!$result) {
                    return ToolResult::error('NOT_FOUND', "Tool '{$name}' nicht in der Registry gefunden oder nicht verfügbar.");
                }

                return ToolResult::success($result);
            }

            // Listen-Abfrage
            $filters = [];
            if (!empty($arguments['namespace'])) {
                $filters['namespace'] = $arguments['namespace'];
            }
            if (!empty($arguments['tier'])) {
                $filters['tier'] = $arguments['tier'];
            }

            $limit = min(50, max(1, (int) ($arguments['limit'] ?? 20)));
            $offset = max(0, (int) ($arguments['offset'] ?? 0));

            $service = app(ToolRegistryService::class);
            $result = $service->list($filters, $limit, $offset);

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen: ' . $e->getMessage());
        }
    }
}
