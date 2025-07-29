<?php

namespace Platform\Core\Registry;

class ModuleRegistry
{
    /**
     * @var array<string, array> Alle registrierten Module (key => config)
     */
    protected static array $modules = [];

    /**
     * Registriert ein Modul mit Meta-Daten.
     */
    public static function register(array $moduleConfig): void
    {
        if (empty($moduleConfig['key'])) {
            throw new \InvalidArgumentException('Module key is required.');
        }

        static::$modules[$moduleConfig['key']] = $moduleConfig;
    }

    /**
     * Holt die Config eines Moduls.
     */
    public static function get(string $key): ?array
    {
        return static::$modules[$key] ?? null;
    }

    /**
     * Gibt alle registrierten Module zurück.
     */
    public static function all(): array
    {
        return static::$modules;
    }
}