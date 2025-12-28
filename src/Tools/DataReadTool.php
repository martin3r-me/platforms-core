<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Tool-Implementierung für data_read
 * 
 * Wrapper um CoreDataProxy, der das ToolContract implementiert
 */
class DataReadTool implements ToolContract
{
    public function __construct(
        private CoreDataProxy $proxy
    ) {}

    public function getName(): string
    {
        return 'data_read';
    }

    public function getDescription(): string
    {
        return 'Liest Daten aus verschiedenen Entitäten. Unterstützt die Operationen: describe (Metadaten), list (Liste), get (Einzel-Datensatz), search (Suche).';
    }

    public function getSchema(): array
    {
            return [
            'type' => 'object',
            'properties' => [
                'entity' => [
                    'type' => 'string',
                    'description' => 'Name der Entität (z.B. "planner.tasks", "okr.key_results")'
                ],
                'operation' => [
                    'type' => 'string',
                    'enum' => ['describe', 'list', 'get', 'search'],
                    'description' => 'Operation: describe (Metadaten), list (Liste), get (Einzel-Datensatz), search (Suche)'
                ],
                'id' => [
                    'type' => 'integer',
                    'description' => 'ID für get-Operation'
                ],
                'filters' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'op' => ['type' => 'string', 'enum' => ['eq', 'ne', 'like', 'in', 'gte', 'lte', 'between', 'is_null']],
                            'value' => ['oneOf' => [
                                ['type' => 'string'],
                                ['type' => 'number'],
                                ['type' => 'boolean'],
                                ['type' => 'array', 'items' => ['type' => 'string']]
                            ]]
                        ],
                        'required' => ['field', 'op']
                    ]
                ],
                'sort' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'dir' => ['type' => 'string', 'enum' => ['asc', 'desc']]
                        ],
                        'required' => ['field', 'dir']
                    ]
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'include' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ],
                'page' => [
                    'type' => 'integer'
                ],
                'per_page' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 200
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Suchbegriff für search-Operation'
                ]
            ],
            'required' => ['entity', 'operation']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $entity = $arguments['entity'] ?? '';
        $operation = $arguments['operation'] ?? '';
        
        // Alle anderen Argumente als Options übergeben
        $options = $arguments;
        unset($options['entity'], $options['operation']);

        $result = $this->proxy->executeRead($entity, $operation, $options, [
            'trace_id' => bin2hex(random_bytes(8))
        ]);

        // Konvertiere altes Format zu ToolResult
        if (($result['ok'] ?? false) === true) {
            return ToolResult::success($result['data'] ?? null, $result['_source'] ?? []);
    }

        $error = $result['error'] ?? [];
        return ToolResult::error(
            $error['message'] ?? 'Unbekannter Fehler',
            $error['code'] ?? 'UNKNOWN_ERROR',
            ['trace_id' => $error['trace_id'] ?? null]
        );
    }
}
