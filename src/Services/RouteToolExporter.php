<?php

namespace Platform\Core\Services;

use Illuminate\Routing\Route;
use Platform\Core\Registry\CommandRegistry;
use Platform\Core\Registry\ModuleRegistry;

class RouteToolExporter
{
    /**
     * Registriert GET-Navigationsrouten eines Moduls als Tools (read-only, autoAllowed).
     * Nimmt nur benannte Routen mit Präfix (z. B. planner.) auf.
     */
    public static function registerModuleRoutes(string $moduleKeyPrefix): void
    {
        $routes = app('router')->getRoutes();
        $items = [];
        /** @var Route $r */
        foreach ($routes as $r) {
            $name = $r->getName();
            if (!$name || !str_starts_with($name, $moduleKeyPrefix.'.')) {
                continue;
            }
            $methods = $r->methods();
            if (!in_array('GET', $methods, true)) {
                continue;
            }
            // Parameter aus URI extrahieren
            $uri = $r->uri();
            preg_match_all('/\{([^}]+)\}/', $uri, $m);
            $params = $m[1] ?? [];
            $paramSchemas = [];
            foreach ($params as $p) {
                $paramSchemas[] = ['name' => $p, 'type' => 'string', 'required' => true, 'description' => 'Route-Parameter'];
            }
            $items[] = [
                'key' => $name,
                'description' => 'Navigation: '.$name,
                'parameters' => $paramSchemas,
                'impact' => 'low',
                'confirmRequired' => false,
                'autoAllowed' => true,
                'phrases' => [],
                'slots' => array_map(fn($p) => ['name' => $p], $params),
                'guard' => 'web',
                'handler' => ['route', $name],
            ];
        }
        if (!empty($items)) {
            CommandRegistry::append($moduleKeyPrefix, $items);
        }
    }

    /**
     * Registriert GET-Routen für alle bekannten Module basierend auf deren Prefix (routing.prefix oder Modulschlüssel).
     */
    public static function registerAllModuleRoutes(): void
    {
        $modules = ModuleRegistry::all();
        foreach ($modules as $key => $config) {
            $prefix = $config['routing']['prefix'] ?? $key;
            // Benannte Routen verwenden in der Regel das Modulpräfix als Namespace (z. B. planner.*, crm.*)
            self::registerModuleRoutes($prefix);
        }
    }
}


