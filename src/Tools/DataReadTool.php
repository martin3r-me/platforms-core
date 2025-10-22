<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;
use Platform\Core\Models\Team;

class DataReadTool
{
    private const MAX_PER_PAGE = 200;
    private const DEFAULT_PER_PAGE = 50;

    public function describe(string $entity): array
    {
        $provider = $this->getProvider($entity);
        if (!$provider) {
            return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found");
        }

        return [
            'ok' => true,
            'data' => [
                'entity' => $entity,
                'readable_fields' => $provider['readable_fields'],
                'allowed_filters' => $provider['allowed_filters'],
                'allowed_sorts' => $provider['allowed_sorts'],
                'relations_whitelist' => $provider['relations_whitelist'],
                'search_fields' => $provider['search_fields'],
                'default_projection' => $provider['default_projection'],
            ],
            'message' => "Schema for {$entity} loaded"
        ];
    }

    public function list(string $entity, array $options = []): array
    {
        $provider = $this->getProvider($entity);
        if (!$provider) {
            return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found");
        }

        try {
            $query = $this->buildQuery($entity, $options);
            $total = $query->count();
            
            $page = max(1, (int)($options['page'] ?? 1));
            $perPage = min(self::MAX_PER_PAGE, max(1, (int)($options['per_page'] ?? self::DEFAULT_PER_PAGE)));
            
            $records = $query
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->map(fn($record) => $this->redactRecord($record, $provider))
                ->toArray();

            return [
                'ok' => true,
                'data' => [
                    'records' => $records,
                    '_source' => [
                        'module' => $provider['module'],
                        'entity' => $entity,
                        'model' => $provider['model'],
                        'updated_at' => now()->toISOString(),
                    ],
                    'meta' => [
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $perPage,
                        'sort' => $options['sort'] ?? [],
                        'fields' => $options['fields'] ?? $provider['default_projection'],
                        'include' => $options['include'] ?? [],
                        'duration_ms' => 0, // TODO: measure
                    ]
                ],
                'message' => "Listed {$total} {$entity} records"
            ];

        } catch (\Exception $e) {
            return $this->error('INTERNAL_ERROR', $e->getMessage());
        }
    }

    public function get(string $entity, int $id): array
    {
        $provider = $this->getProvider($entity);
        if (!$provider) {
            return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found");
        }

        try {
            $query = $this->buildQuery($entity, []);
            $record = $query->where('id', $id)->first();

            if (!$record) {
                return $this->error('ROW_NOT_FOUND', "Record with ID {$id} not found");
            }

            return [
                'ok' => true,
                'data' => [
                    'record' => $this->redactRecord($record, $provider),
                    '_source' => [
                        'module' => $provider['module'],
                        'entity' => $entity,
                        'model' => $provider['model'],
                        'updated_at' => now()->toISOString(),
                    ]
                ],
                'message' => "Retrieved {$entity} #{$id}"
            ];

        } catch (\Exception $e) {
            return $this->error('INTERNAL_ERROR', $e->getMessage());
        }
    }

    public function search(string $entity, string $query, array $options = []): array
    {
        $provider = $this->getProvider($entity);
        if (!$provider) {
            return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found");
        }

        if (empty($provider['search_fields'])) {
            return $this->error('SEARCH_NOT_SUPPORTED', "Search not supported for {$entity}");
        }

        try {
            $searchOptions = array_merge($options, ['query' => $query]);
            $result = $this->list($entity, $searchOptions);
            
            if ($result['ok']) {
                $result['message'] = "Searched {$entity} for '{$query}'";
            }
            
            return $result;

        } catch (\Exception $e) {
            return $this->error('INTERNAL_ERROR', $e->getMessage());
        }
    }

    private function getProvider(string $entity): ?array
    {
        $providers = $this->getProviders();
        return $providers[$entity] ?? null;
    }

    private function getProviders(): array
    {
        return [
            'task' => [
                'module' => 'planner',
                'model' => 'Platform\Planner\Models\PlannerTask',
                'readable_fields' => [
                    'id', 'uuid', 'title', 'description', 'due_date', 'status', 'is_done', 
                    'is_frog', 'story_points', 'priority', 'order', 'created_at', 'updated_at',
                    'user_id', 'user_in_charge_id', 'team_id', 'project_id', 'task_group_id'
                ],
                'allowed_filters' => [
                    'id' => ['eq', 'ne', 'in'],
                    'title' => ['eq', 'ne', 'like'],
                    'status' => ['eq', 'ne', 'in'],
                    'is_done' => ['eq'],
                    'is_frog' => ['eq'],
                    'due_date' => ['eq', 'ne', 'gte', 'lte', 'between', 'is_null'],
                    'user_id' => ['eq', 'ne', 'in'],
                    'user_in_charge_id' => ['eq', 'ne', 'in', 'is_null'],
                    'team_id' => ['eq', 'ne', 'in'],
                    'project_id' => ['eq', 'ne', 'in', 'is_null'],
                    'task_group_id' => ['eq', 'ne', 'in', 'is_null'],
                    'created_at' => ['gte', 'lte', 'between'],
                    'updated_at' => ['gte', 'lte', 'between'],
                ],
                'allowed_sorts' => [
                    'id', 'title', 'due_date', 'created_at', 'updated_at', 'order'
                ],
                'relations_whitelist' => [
                    'user', 'team', 'project', 'taskGroup', 'userInCharge'
                ],
                'search_fields' => ['title', 'description'],
                'default_projection' => ['id', 'title', 'description', 'due_date', 'status', 'is_done', 'is_frog'],
                'pii_redaction' => []
            ]
        ];
    }

    private function buildQuery(string $entity, array $options): \Illuminate\Database\Eloquent\Builder
    {
        $provider = $this->getProvider($entity);
        $modelClass = $provider['model'];
        $query = $modelClass::query();

        // Team-Scope erzwingen
        $user = Auth::user();
        $team = $user?->currentTeam;
        if ($team) {
            $query->where('team_id', $team->id);
        }

        // Filters anwenden
        if (!empty($options['filters'])) {
            foreach ($options['filters'] as $filter) {
                $this->applyFilter($query, $filter, $provider);
            }
        }

        // Search
        if (!empty($options['query']) && !empty($provider['search_fields'])) {
            $query->where(function($q) use ($options, $provider) {
                foreach ($provider['search_fields'] as $field) {
                    $q->orWhere($field, 'like', '%' . $options['query'] . '%');
                }
            });
        }

        // Sort
        if (!empty($options['sort'])) {
            foreach ($options['sort'] as $sort) {
                $field = $sort['field'] ?? null;
                $dir = $sort['dir'] ?? 'asc';
                if ($field && in_array($field, $provider['allowed_sorts'])) {
                    $query->orderBy($field, $dir);
                }
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    private function applyFilter(\Illuminate\Database\Eloquent\Builder $query, array $filter, array $provider): void
    {
        $field = $filter['field'] ?? null;
        $op = $filter['op'] ?? null;
        $value = $filter['value'] ?? null;

        if (!$field || !$op || !isset($provider['allowed_filters'][$field])) {
            return;
        }

        if (!in_array($op, $provider['allowed_filters'][$field])) {
            return;
        }

        switch ($op) {
            case 'eq':
                $query->where($field, $value);
                break;
            case 'ne':
                $query->where($field, '!=', $value);
                break;
            case 'like':
                $query->where($field, 'like', '%' . $value . '%');
                break;
            case 'in':
                $query->whereIn($field, is_array($value) ? $value : [$value]);
                break;
            case 'gte':
                $query->where($field, '>=', $value);
                break;
            case 'lte':
                $query->where($field, '<=', $value);
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($field, $value);
                }
                break;
            case 'is_null':
                $query->whereNull($field);
                break;
        }
    }

    private function redactRecord($record, array $provider): array
    {
        $data = $record->toArray();
        
        // PII Redaction
        foreach ($provider['pii_redaction'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->maskPii($data[$field]);
            }
        }

        return $data;
    }

    private function maskPii($value): string
    {
        if (is_string($value) && str_contains($value, '@')) {
            return preg_replace('/(.{2}).*(@.*)/', '$1***$2', $value);
        }
        return '***';
    }

    private function error(string $code, string $message): array
    {
        return [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }
}
