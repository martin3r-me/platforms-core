<?php

namespace Platform\Core\Tools\Concerns;

/**
 * Trait für standardisierte GET-Operationen
 * 
 * Bietet einheitliche Filter, Suche, Sortierung und Pagination
 * für alle GET-Tools, damit die LLM alle Tools gleich bedienen kann.
 */
trait HasStandardGetOperations
{
    /**
     * Standard-Schema für alle GET-Operationen
     * 
     * Enthält: filters, search, sort, pagination
     */
    protected function getStandardGetSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                // === FILTER ===
                'filters' => [
                    'type' => 'array',
                    'description' => 'Optional: Array von Filtern. Jeder Filter ist ein Objekt mit "field", "op" (Operator) und "value". Beispiel: [{"field": "project_type", "op": "eq", "value": "internal"}]',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => [
                                'type' => 'string',
                                'description' => 'Feldname zum Filtern (z.B. "project_type", "is_done", "created_at")'
                            ],
                            'op' => [
                                'type' => 'string',
                                'enum' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'like', 'in', 'not_in', 'is_null', 'is_not_null'],
                                'description' => 'Operator: eq (gleich), ne (ungleich), gt (größer), gte (größer gleich), lt (kleiner), lte (kleiner gleich), like (enthält), in (in Liste), not_in (nicht in Liste), is_null (ist null), is_not_null (ist nicht null)'
                            ],
                            'value' => [
                                'description' => 'Wert für den Filter. Bei "in" oder "not_in" ein Array, sonst String/Number/Boolean. Bei "is_null" oder "is_not_null" kann value weggelassen werden.'
                            ]
                        ],
                        'required' => ['field', 'op']
                    ]
                ],
                
                // === SUCHE ===
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suchbegriff für Volltext-Suche. Durchsucht standardmäßig relevante Felder (z.B. Name, Beschreibung). Beispiel: "Test Projekt"'
                ],
                'search_fields' => [
                    'type' => 'array',
                    'description' => 'Optional: Array von Feldnamen, die durchsucht werden sollen. Wenn nicht angegeben, werden Standard-Felder durchsucht. Beispiel: ["name", "description"]',
                    'items' => ['type' => 'string']
                ],
                
                // === SORTIERUNG ===
                'sort' => [
                    'type' => 'array',
                    'description' => 'Optional: Array von Sortierungen. Jede Sortierung ist ein Objekt mit "field" und "dir" (asc/desc). Beispiel: [{"field": "name", "dir": "asc"}, {"field": "created_at", "dir": "desc"}]',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => [
                                'type' => 'string',
                                'description' => 'Feldname zum Sortieren (z.B. "name", "created_at", "due_date")'
                            ],
                            'dir' => [
                                'type' => 'string',
                                'enum' => ['asc', 'desc'],
                                'description' => 'Sortierrichtung: asc (aufsteigend) oder desc (absteigend)'
                            ]
                        ],
                        'required' => ['field', 'dir']
                    ]
                ],
                
                // === PAGINATION ===
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Optional: Maximale Anzahl der Ergebnisse. Standard: 50, Maximum: 1000.'
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Optional: Anzahl der zu überspringenden Ergebnisse (für Pagination). Standard: 0.'
                ],
            ],
            'required' => []
        ];
    }
    
    /**
     * Wendet Standard-Filter auf Query an
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $arguments Tool-Argumente
     * @param array $allowedFields Erlaubte Feldnamen für Filter (Security)
     */
    protected function applyStandardFilters($query, array $arguments, array $allowedFields = []): void
    {
        if (empty($arguments['filters']) || !is_array($arguments['filters'])) {
            return;
        }
        
        foreach ($arguments['filters'] as $filter) {
            if (empty($filter['field']) || empty($filter['op'])) {
                continue;
            }
            
            $field = $filter['field'];
            $op = $filter['op'];
            $value = $filter['value'] ?? null;
            
            // Security: Nur erlaubte Felder (wenn angegeben)
            if (!empty($allowedFields) && !in_array($field, $allowedFields)) {
                continue;
            }
            
            match ($op) {
                'eq' => $query->where($field, $value),
                'ne' => $query->where($field, '!=', $value),
                'gt' => $query->where($field, '>', $value),
                'gte' => $query->where($field, '>=', $value),
                'lt' => $query->where($field, '<', $value),
                'lte' => $query->where($field, '<=', $value),
                'like' => $query->where($field, 'like', '%' . $value . '%'),
                'in' => $query->whereIn($field, is_array($value) ? $value : [$value]),
                'not_in' => $query->whereNotIn($field, is_array($value) ? $value : [$value]),
                'is_null' => $query->whereNull($field),
                'is_not_null' => $query->whereNotNull($field),
                default => null
            };
        }
    }
    
    /**
     * Wendet Standard-Suche auf Query an
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $arguments Tool-Argumente
     * @param array $defaultSearchFields Standard-Felder, die durchsucht werden sollen
     */
    protected function applyStandardSearch($query, array $arguments, array $defaultSearchFields = []): void
    {
        if (empty($arguments['search'])) {
            return;
        }
        
        $search = $arguments['search'];
        $searchFields = $arguments['search_fields'] ?? $defaultSearchFields;
        
        if (empty($searchFields)) {
            return;
        }
        
        $query->where(function($q) use ($search, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'like', '%' . $search . '%');
            }
        });
    }
    
    /**
     * Wendet Standard-Sortierung auf Query an
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $arguments Tool-Argumente
     * @param array $allowedSortFields Erlaubte Feldnamen für Sortierung (Security)
     * @param string $defaultSort Standard-Sortierfeld
     * @param string $defaultDir Standard-Sortierrichtung (asc/desc)
     */
    protected function applyStandardSort($query, array $arguments, array $allowedSortFields = [], string $defaultSort = 'created_at', string $defaultDir = 'desc'): void
    {
        if (!empty($arguments['sort']) && is_array($arguments['sort'])) {
            foreach ($arguments['sort'] as $sort) {
                if (empty($sort['field']) || empty($sort['dir'])) {
                    continue;
                }
                
                $field = $sort['field'];
                $dir = in_array($sort['dir'], ['asc', 'desc']) ? $sort['dir'] : 'asc';
                
                // Security: Nur erlaubte Felder (wenn angegeben)
                if (!empty($allowedSortFields) && !in_array($field, $allowedSortFields)) {
                    continue;
                }
                
                $query->orderBy($field, $dir);
            }
        } else {
            // Default-Sortierung
            if (empty($allowedSortFields) || in_array($defaultSort, $allowedSortFields)) {
                $query->orderBy($defaultSort, $defaultDir);
            }
        }
    }
    
    /**
     * Wendet Standard-Pagination auf Query an
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $arguments Tool-Argumente
     */
    protected function applyStandardPagination($query, array $arguments): void
    {
        $limit = min($arguments['limit'] ?? 50, 1000); // Max 1000
        $offset = max($arguments['offset'] ?? 0, 0);
        
        $query->limit($limit)->offset($offset);
    }
    
    /**
     * Merge zwei JSON-Schemas (für Standard + Custom Properties)
     */
    protected function mergeSchemas(array $standardSchema, array $customSchema): array
    {
        $merged = $standardSchema;
        
        // Merge properties
        if (isset($customSchema['properties'])) {
            $merged['properties'] = array_merge(
                $standardSchema['properties'] ?? [],
                $customSchema['properties']
            );
        }
        
        // Merge required
        if (isset($customSchema['required'])) {
            $merged['required'] = array_unique(array_merge(
                $standardSchema['required'] ?? [],
                $customSchema['required']
            ));
        }
        
        return $merged;
    }
}

