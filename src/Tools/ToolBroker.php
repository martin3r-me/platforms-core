<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Auth;

class ToolBroker
{
    public function getAvailableCapabilities(): array
    {
        $user = Auth::user();
        $team = $user?->currentTeam;
        
        if (!$user || !$team) {
            return [
                'available_modules' => [],
                'available_entities' => [],
                'available_operations' => []
            ];
        }

        return [
            'available_modules' => $this->getAvailableModules($user, $team),
            'available_entities' => $this->getAvailableEntities($user, $team),
            'available_operations' => ['describe', 'list', 'get', 'search'],
            'user_context' => [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'team_name' => $team->name,
            ]
        ];
    }

    public function getToolDefinition(string $entity, string $operation): ?array
    {
        $capabilities = $this->getAvailableCapabilities();
        
        if (!in_array($entity, $capabilities['available_entities'])) {
            return null;
        }

        if (!in_array($operation, $capabilities['available_operations'])) {
            return null;
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => 'data_read',
                'description' => "Read {$entity} data with {$operation} operation",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => [
                            'type' => 'string',
                            'enum' => $capabilities['available_entities'],
                            'description' => 'Entity to query'
                        ],
                        'operation' => [
                            'type' => 'string',
                            'enum' => $capabilities['available_operations'],
                            'description' => 'Operation to perform'
                        ],
                        'id' => [
                            'type' => 'integer',
                            'description' => 'Record ID (required for get operation)'
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
                            ],
                            'description' => 'Filter conditions'
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
                            ],
                            'description' => 'Sort conditions'
                        ],
                        'fields' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Fields to include in response'
                        ],
                        'include' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Relations to include'
                        ],
                        'page' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'description' => 'Page number (default: 1)'
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 200,
                            'description' => 'Records per page (default: 50, max: 200)'
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query (for search operation)'
                        ]
                    ],
                    'required' => ['entity', 'operation']
                ]
            ]
        ];
    }

    private function getAvailableModules($user, $team): array
    {
        // TODO: Check user/team module permissions
        return ['planner'];
    }

    private function getAvailableEntities($user, $team): array
    {
        // TODO: Check user/team entity permissions
        return ['task'];
    }
}
