<?php

namespace Platform\Core\SemanticLayer;

/**
 * Statische Registry für Semantic-Layer-Kontext-Keys.
 *
 * Entkoppelt von ModuleRegistry: Kontext-Keys sind ein Superset
 * der Module-Keys (z.B. `mcp`, `api`, `webhook` sind keine Module,
 * aber gültige Kontext-Keys für das enabled_modules-Gate).
 *
 * Boot: CoreServiceProvider registriert builtins + importiert
 * alle Module-Keys automatisch.
 */
class ContextKeyRegistry
{
    /** @var array<string, string> key => description */
    private static array $keys = [];

    public static function register(string $key, string $description): void
    {
        static::$keys[$key] = $description;
    }

    public static function has(string $key): bool
    {
        return isset(static::$keys[$key]);
    }

    /**
     * @return array<string, string> key => description
     */
    public static function all(): array
    {
        return static::$keys;
    }

    /**
     * Reset (for testing).
     */
    public static function flush(): void
    {
        static::$keys = [];
    }
}
