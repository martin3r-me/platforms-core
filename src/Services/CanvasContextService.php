<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CanvasContextable;

/**
 * Resolves contextable entities for Canvas modules.
 *
 * Config-based registration allows modules to register their context types
 * without hard-coupling between modules. Gracefully returns null if a module
 * is not installed.
 */
class CanvasContextService
{
    /** @var array<string, CanvasContextable|null> Request-level cache */
    private array $cache = [];

    /**
     * Resolve a context entity by type and ID.
     *
     * Looks up the model class from config('canvas-context.types') and returns
     * the model instance implementing CanvasContextable, or null.
     */
    public function resolveContext(string $type, string|int $id): ?CanvasContextable
    {
        $cacheKey = "{$type}:{$id}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $types = config('canvas-context.types', []);

        if (!isset($types[$type])) {
            $this->cache[$cacheKey] = null;
            return null;
        }

        $modelClass = $types[$type];

        if (!class_exists($modelClass)) {
            $this->cache[$cacheKey] = null;
            return null;
        }

        $model = $modelClass::find($id);

        if (!$model instanceof CanvasContextable) {
            $this->cache[$cacheKey] = null;
            return null;
        }

        $this->cache[$cacheKey] = $model;

        return $model;
    }

    /**
     * Returns all registered context types.
     *
     * @return array<string, string> type => model class
     */
    public function getRegisteredTypes(): array
    {
        return config('canvas-context.types', []);
    }

    /**
     * Clear the request cache (useful for testing).
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
