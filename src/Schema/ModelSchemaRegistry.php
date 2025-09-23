<?php

namespace Platform\Core\Schema;

class ModelSchemaRegistry
{
    protected static array $schemas = [];

    public static function register(string $modelKey, array $schema): void
    {
        // Minimaler Vertrag: fields, filterable, sortable, selectable, relations
        self::$schemas[$modelKey] = [
            'fields' => array_values($schema['fields'] ?? []),
            'filterable' => array_values($schema['filterable'] ?? []),
            'sortable' => array_values($schema['sortable'] ?? []),
            'selectable' => array_values($schema['selectable'] ?? []),
            'relations' => $schema['relations'] ?? [],
            'required' => array_values($schema['required'] ?? []),
            'writable' => array_values($schema['writable'] ?? []),
            'foreign_keys' => $schema['foreign_keys'] ?? [],
            'meta' => $schema['meta'] ?? [],
        ];
    }

    public static function get(string $modelKey): array
    {
        return self::$schemas[$modelKey] ?? [];
    }

    public static function keys(): array
    {
        return array_keys(self::$schemas);
    }

    public static function keysByPrefix(string $prefix): array
    {
        return array_values(array_filter(self::keys(), fn($k) => str_starts_with($k, $prefix)));
    }

    public static function validateFields(string $modelKey, array $requested, array $fallback): array
    {
        $schema = self::get($modelKey);
        $allowed = $schema['selectable'] ?: ($schema['fields'] ?? []);
        if (empty($allowed)) return $fallback;
        $valid = array_values(array_intersect($requested, $allowed));
        return empty($valid) ? $fallback : $valid;
    }

    public static function validateSort(string $modelKey, ?string $sort, string $default = 'id'): string
    {
        $schema = self::get($modelKey);
        $allowed = $schema['sortable'] ?? [];
        if (!$sort || empty($allowed)) return $default;
        return in_array($sort, $allowed, true) ? $sort : $default;
    }

    public static function validateFilters(string $modelKey, array $filters): array
    {
        $schema = self::get($modelKey);
        $allowed = $schema['filterable'] ?? [];
        if (empty($allowed)) return [];
        return array_filter(
            $filters,
            fn($v, $k) => in_array($k, $allowed, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function meta(string $modelKey, string $key, $default = null)
    {
        $schema = self::get($modelKey);
        return $schema['meta'][$key] ?? $default;
    }

    public static function required(string $modelKey): array
    {
        return self::get($modelKey)['required'] ?? [];
    }

    public static function writable(string $modelKey): array
    {
        return self::get($modelKey)['writable'] ?? [];
    }

    public static function foreignKeys(string $modelKey): array
    {
        return self::get($modelKey)['foreign_keys'] ?? [];
    }
}


