<?php

namespace Platform\Core\Routing;

use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

class ModuleRouter
{
    public static function group(string $key, Closure $callback, bool $requireAuth = true)
    {
        // Modul-Config laden
        $module = \Platform\Core\PlatformCore::getModule($key);
        if (!$module) {
            throw new \RuntimeException("Module '{$key}' is not registered.");
        }

        $routing = $module['routing'] ?? [];
        $guard   = $module['guard'] ?? 'web';
        $mode    = $routing['mode'] ?? 'path';
        $prefix  = $routing['prefix'] ?? strtolower($key);

        // Basis-Domain aus APP_URL
        $appUrl = config('app.url');
        $parsed = parse_url($appUrl);
        $baseHost = $parsed['host'] ?? 'localhost';

        // Dynamische Middleware
        $middlewares = ['web'];
        if ($requireAuth) {
            $middlewares[] = "auth:{$guard}";
        }

        $routeGroup = Route::middleware($middlewares);

        Log::info('ModuleRouter: registering route group', [
            'module' => $key,
            'mode'   => $mode,
            'host'   => $baseHost,
            'prefix' => $prefix,
        ]);

        if ($mode === 'subdomain') {
            // Subdomain: prefix als Subdomain setzen
            $domain = "{$prefix}.{$baseHost}";
            return $routeGroup
                ->domain($domain)
                ->group($callback);
        }

        // Path: Domain bleibt die Basisdomain (kein Subdomain-Prefix)
        return $routeGroup
            ->domain($baseHost) // verhindert, dass Laravel die Subdomain vom aktuellen Request übernimmt
            ->prefix($prefix)
            ->group($callback);
    }
}