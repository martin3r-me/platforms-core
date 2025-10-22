<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Tools\DataRead\ProviderRegistry;
use Illuminate\Database\Eloquent\Builder;

class DataReadTool
{
    private const MAX_PER_PAGE = 200;
    private const DEFAULT_PER_PAGE = 50;

    public function __construct(private ProviderRegistry $registry) {}

    public function describe(string $entity): array
    {
        $provider = $this->registry->get($entity);
        if (!$provider) { return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found"); }

        return [
            'ok' => true,
            'data' => [
                'entity' => $entity,
                'readable_fields' => $provider->readableFields(),
                'allowed_filters' => $provider->allowedFilters(),
                'allowed_sorts' => $provider->allowedSorts(),
                'relations_whitelist' => $provider->relationsWhitelist(),
                'search_fields' => $provider->searchFields(),
                'default_projection' => $provider->defaultProjection(),
            ],
            'message' => "Schema for {$entity} loaded"
        ];
    }

    public function list(string $entity, array $options = []): array
    {
        $provider = $this->registry->get($entity);
        if (!$provider) { return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found"); }

        try {
            $query = $this->buildQuery($provider, $options);
            $total = $query->count();

            $page = max(1, (int)($options['page'] ?? 1));
            $perPage = min(self::MAX_PER_PAGE, max(1, (int)($options['per_page'] ?? self::DEFAULT_PER_PAGE)));

            $records = $query
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->map(fn($record) => $record->toArray())
                ->toArray();

            $sortMeta = $options['sort'] ?? [];

            return [
                'ok' => true,
                'data' => [
                    'records' => $records,
                    '_source' => [
                        'entity' => $entity,
                        'model' => $provider->model(),
                        'updated_at' => now()->toISOString(),
                    ],
                    'meta' => [
                        'total' => $total,
                        'page' => $page,
                        'per_page' => $perPage,
                        'sort' => $sortMeta,
                        'fields' => $options['fields'] ?? $provider->defaultProjection(),
                        'include' => $options['include'] ?? [],
                        'duration_ms' => 0,
                    ]
                ],
                'message' => "Listed {$total} {$entity} records"
            ];
        } catch (\Throwable $e) {
            return $this->error('INTERNAL_ERROR', $e->getMessage());
        }
    }

    public function get(string $entity, int $id): array
    {
        $provider = $this->registry->get($entity);
        if (!$provider) { return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found"); }

        try {
            $query = $this->buildQuery($provider, []);
            $record = $query->where('id', $id)->first();
            if (!$record) { return $this->error('ROW_NOT_FOUND', "Record with ID {$id} not found"); }

            return [
                'ok' => true,
                'data' => [
                    'record' => $record->toArray(),
                    '_source' => [
                        'entity' => $entity,
                        'model' => $provider->model(),
                        'updated_at' => now()->toISOString(),
                    ]
                ],
                'message' => "Retrieved {$entity} #{$id}"
            ];
        } catch (\Throwable $e) {
            return $this->error('INTERNAL_ERROR', $e->getMessage());
        }
    }

    public function search(string $entity, string $queryText, array $options = []): array
    {
        $provider = $this->registry->get($entity);
        if (!$provider) { return $this->error('ENTITY_NOT_FOUND', "Entity '{$entity}' not found"); }
        if (empty($provider->searchFields())) { return $this->error('SEARCH_NOT_SUPPORTED', "Search not supported for {$entity}"); }

        try {
            $options = array_merge($options, ['query' => $queryText]);
            return $this->list($entity, $options);
        } catch (\Throwable $e) {
            return $this->error('INTERNAL_ERROR', $e->getMessage());
        }
    }

    private function buildQuery($provider, array $options): Builder
    {
        $query = $provider->teamScopedQuery();

        // Domain defaults (e.g., open only, default sort)
        $provider->applyDomainDefaults($query, $options);

        // Filters with mapping
        foreach ($options['filters'] ?? [] as $filter) {
            $mapped = $provider->mapFilter($filter);
            if ($mapped === null) { continue; }
            $this->applyFilter($query, $mapped, $provider);
        }

        // Search
        if (!empty($options['query'])) {
            $fields = $provider->searchFields();
            $query->where(function($q) use ($fields, $options) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', '%' . $options['query'] . '%');
                }
            });
        }

        // Sort
        if (!empty($options['sort'])) {
            foreach ($options['sort'] as $sort) {
                $field = $sort['field'] ?? null;
                $dir = $sort['dir'] ?? 'asc';
                if ($field && in_array($field, $provider->allowedSorts(), true)) {
                    $query->orderBy($field, $dir);
                }
            }
        }

        return $query;
    }

    private function applyFilter(Builder $query, array $filter, $provider): void
    {
        $field = $filter['field'] ?? null;
        $op = $filter['op'] ?? null;
        $value = $filter['value'] ?? null;

        $allowed = $provider->allowedFilters();
        if (!$field || !$op || !isset($allowed[$field])) { return; }
        if (!in_array($op, $allowed[$field], true)) { return; }

        match ($op) {
            'eq' => $query->where($field, $value),
            'ne' => $query->where($field, '!=', $value),
            'like' => $query->where($field, 'like', '%' . $value . '%'),
            'in' => $query->whereIn($field, is_array($value) ? $value : [$value]),
            'gte' => $query->where($field, '>=', $value),
            'lte' => $query->where($field, '<=', $value),
            'between' => (is_array($value) && count($value) === 2) ? $query->whereBetween($field, $value) : null,
            'is_null' => $query->whereNull($field),
            default => null
        };
    }

    private function error(string $code, string $message): array
    {
        return [
            'ok' => false,
            'error' => [ 'code' => $code, 'message' => $message ]
        ];
    }
}
