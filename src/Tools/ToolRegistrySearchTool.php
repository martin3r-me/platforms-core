<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ToolRegistryService;

class ToolRegistrySearchTool implements ToolContract
{
    public function getName(): string
    {
        return 'tool_registry.SEARCH';
    }

    public function getDescription(): string
    {
        return 'Durchsucht die Tool-Registry nach verfügbaren Tools. Unterstützt natürlichsprachliche Queries (z.B. "Notizen schreiben", "Task anlegen"), Glob-Pattern (z.B. "obsidian.*", "*.bulk.POST") und Filter (namespace, tier, kind, cost_class). Gibt eine kompakte, Token-sparende Liste zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Natürlichsprachliche Suchanfrage (z.B. "Kontakt anlegen", "Dateien hochladen").',
                ],
                'name_glob' => [
                    'type' => 'string',
                    'description' => 'Glob-Pattern für Tool-Namen (z.B. "obsidian.*", "*.bulk.POST", "planner.tasks.*").',
                ],
                'filters' => [
                    'type' => 'object',
                    'description' => 'Optionale Filter.',
                    'properties' => [
                        'namespace' => [
                            'type' => 'string',
                            'description' => 'Semantischer Namespace (z.B. "obsidian", "planner").',
                        ],
                        'tier' => [
                            'type' => 'string',
                            'description' => 'Tool-Tier: always_on, common, specialized, hidden.',
                        ],
                        'cost_class' => [
                            'type' => 'string',
                            'description' => 'Kostenklasse: local_db, local_compute, external_api_free, external_api_paid.',
                        ],
                        'kind' => [
                            'type' => 'string',
                            'description' => 'HTTP-Methode/Typ: GET, POST, PUT, DELETE, PATCH, CRUD, SEARCH, FETCH, BULK_POST.',
                        ],
                        'deprecated' => [
                            'type' => 'boolean',
                            'description' => 'Auch deprecatete Tools anzeigen? Standard: false.',
                        ],
                    ],
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Anzahl Ergebnisse (1-20, Standard: 5).',
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

            $query = trim((string) ($arguments['query'] ?? ''));
            $nameGlob = $arguments['name_glob'] ?? null;
            $filters = $arguments['filters'] ?? [];
            $limit = min(20, max(1, (int) ($arguments['limit'] ?? 5)));

            if ($query === '' && !$nameGlob && empty($filters)) {
                return ToolResult::error('VALIDATION_ERROR', 'Mindestens query, name_glob oder filters muss angegeben werden.');
            }

            if ($nameGlob) {
                $filters['name_glob'] = $nameGlob;
            }

            $service = app(ToolRegistryService::class);
            $results = $service->search($query, $filters, $limit);

            return ToolResult::success([
                'tools' => $results,
                'count' => count($results),
                'query' => $query !== '' ? $query : null,
                'filters' => !empty($filters) ? $filters : null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Tool-Suche: ' . $e->getMessage());
        }
    }
}
