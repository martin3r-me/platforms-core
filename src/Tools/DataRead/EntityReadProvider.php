<?php

namespace Platform\Core\Tools\DataRead;

use Illuminate\Database\Eloquent\Builder;

interface EntityReadProvider
{
    /** Unique entity key used by tools (e.g., 'task', 'okr.key_result') */
    public function key(): string;

    /** Fully-qualified Eloquent model class name */
    public function model(): string;

    /** Introspection metadata */
    public function readableFields(): array;
    public function allowedFilters(): array; // [field => [ops]]
    public function allowedSorts(): array;   // [field, ...]
    public function relationsWhitelist(): array;
    public function searchFields(): array;
    public function defaultProjection(): array;

    /**
     * Return team/user scoped base query.
     */
    public function teamScopedQuery(): Builder;

    /**
     * Apply domain defaults to query and options (e.g., open items only, default sort).
     */
    public function applyDomainDefaults(Builder $query, array &$options): void;

    /**
     * Map or normalize a single filter (e.g., map 'status' to 'is_done').
     * Return null to drop the filter.
     */
    public function mapFilter(array $filter): ?array;
}
