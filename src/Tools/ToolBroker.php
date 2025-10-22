<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Tools\DataRead\ProviderRegistry;

class ToolBroker
{
    public function __construct(private ProviderRegistry $registry) {}

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

        $entities = array_keys($this->registry->all());

        return [
            'available_modules' => $this->getAvailableModules($user, $team),
            'available_entities' => $entities,
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
        
        if (!in_array($entity, $capabilities['available_entities'])) { return null; }
        if (!in_array($operation, $capabilities['available_operations'])) { return null; }

        return [
            'type' => 'function',
            'function' => [
                'name' => 'data_read',
                'description' => "Read {$entity} data with {$operation} operation",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => [ 'type' => 'string', 'enum' => $capabilities['available_entities'] ],
                        'operation' => [ 'type' => 'string', 'enum' => $capabilities['available_operations'] ],
                        'id' => [ 'type' => 'integer' ],
                        'filters' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'field' => ['type'=>'string'], 'op' => ['type'=>'string','enum'=>['eq','ne','like','in','gte','lte','between','is_null'] ], 'value' => ['oneOf' => [ ['type'=>'string'],['type'=>'number'],['type'=>'boolean'],['type'=>'array','items'=>['type'=>'string']] ]] ], 'required' => ['field','op'] ] ],
                        'sort' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'properties' => [ 'field' => ['type'=>'string'], 'dir' => ['type'=>'string','enum'=>['asc','desc']] ], 'required' => ['field','dir'] ] ],
                        'fields' => [ 'type' => 'array', 'items' => ['type'=>'string'] ],
                        'include' => [ 'type' => 'array', 'items' => ['type'=>'string'] ],
                        'page' => [ 'type' => 'integer' ],
                        'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ],
                        'query' => [ 'type' => 'string' ]
                    ],
                    'required' => ['entity', 'operation']
                ]
            ]
        ];
    }

    public function getWriteToolDefinition(): array
    {
        $entities = array_keys($this->registry->all());
        return [
            'type' => 'function',
            'function' => [
                'name' => 'data_write',
                'description' => 'Create, update, or delete records generically via manifest',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => [ 'type' => 'string', 'enum' => $entities ],
                        'operation' => [ 'type' => 'string', 'enum' => ['create','update','delete'] ],
                        'id' => [ 'type' => ['integer','null'] ],
                        'data' => [ 'type' => 'object', 'description' => 'Payload fields; see entity write_schemas for required fields (e.g., title)']
                    ],
                    'required' => ['entity','operation','data']
                ]
            ]
        ];
    }

    private function getAvailableModules($user, $team): array
    {
        return array_values(array_unique(array_map(fn($k) => strstr($k, '.', true) ?: $k, array_keys($this->registry->all()))));
    }
}
