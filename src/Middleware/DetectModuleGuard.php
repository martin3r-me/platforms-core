<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\PlatformCore;

class DetectModuleGuard
{
    public function handle($request, Closure $next)
    {
        $currentModule = null;
        $matchedBy     = null;

        $host = $request->getHost();
        $path = trim($request->getPathInfo(), '/');

        // Alle registrierten Module durchgehen
        foreach (PlatformCore::getModules() as $module) {
            $url = $module['url'] ?? '';
            if (!$url) {
                continue;
            }

            $parsed = parse_url($url);
            $moduleHost = $parsed['host'] ?? '';
            $modulePath = trim($parsed['path'] ?? '/', '/');
            $mode       = $module['routing']['mode'] ?? 'path';

            if ($mode === 'subdomain') {
                // Subdomain-Modul → Host muss exakt übereinstimmen
                if ($host === $moduleHost) {
                    $currentModule = $module;
                    $matchedBy     = 'subdomain';
                    break;
                }
            } else {
                // Path-Modul → Host muss Hauptdomain matchen + Pfad beginnen
                if ($host === $moduleHost &&
                    $modulePath &&
                    str_starts_with($path, $modulePath)
                ) {
                    $currentModule = $module;
                    $matchedBy     = 'path';
                    break;
                }
            }
        }

        // Fallback: Core (Root-Domain) als "virtuelles Modul"
        if (!$currentModule && $host === parse_url(config('app.url'), PHP_URL_HOST)) {
            $currentModule = [
                'key'   => 'core',
                'title' => 'Platform',
                'guard' => 'web',
                'url'   => config('app.url'),
            ];
            $matchedBy = 'core';
        }

        // Guard und Module setzen
        if ($currentModule) {
            $guard = $currentModule['guard'] ?? 'web';
            Auth::shouldUse($guard);

            $request->attributes->set('current_module', $currentModule['key']);

            Log::info('DetectModuleGuard: Modul erkannt', [
                'module' => $currentModule['key'],
                'guard'  => $guard,
                'match'  => $matchedBy,
                'host'   => $host,
                'path'   => $request->getPathInfo(),
                'url'    => $currentModule['url'] ?? null,
            ]);
        } else {
            // Kein Modul → Standard-Guard, kein Key
            Auth::shouldUse('web');
            $request->attributes->set('current_module', null);

            Log::info('DetectModuleGuard: Kein Modul erkannt', [
                'host' => $host,
                'path' => $request->getPathInfo(),
            ]);
        }

        return $next($request);
    }
}