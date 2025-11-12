<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\PlatformCore;
use Platform\Core\Models\TeamUserLastModule;
use Platform\Core\Models\Module;

class DetectModuleGuard
{
    public function handle($request, Closure $next)
    {
        $currentModule = null;
        $matchedBy     = null;

        $host = $request->getHost();
        $path = trim($request->getPathInfo(), '/');

        // Redirect-Logik wird in der Dashboard-Komponente gemacht (sauberer)

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

            $moduleKey = $currentModule['key'];
            
            // Wenn "core" erkannt wurde und wir auf dashboard sind, dann "dashboard" speichern
            if ($moduleKey === 'core' && ($path === 'dashboard' || empty($path))) {
                $moduleKey = 'dashboard';
            }
            
            $request->attributes->set('current_module', $moduleKey);
            
            // Auch in Session speichern für Livewire-Requests
            session(['current_module_key' => $moduleKey]);

            // Modul für aktuelles Team speichern (wenn User eingeloggt und Team vorhanden)
            $user = Auth::user();
            if ($user && $user->current_team_id) {
                TeamUserLastModule::updateLastModule($user->id, $user->current_team_id, $moduleKey);
            }

            Log::info('DetectModuleGuard: Modul erkannt', [
                'module' => $moduleKey,
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
            
            // Bei Livewire-Requests: Versuche Modul aus Session zu holen
            if (str_starts_with($path, 'livewire/')) {
                $moduleKey = session('current_module_key');
                if ($moduleKey) {
                    $request->attributes->set('current_module', $moduleKey);
                }
            }

            Log::info('DetectModuleGuard: Kein Modul erkannt', [
                'host' => $host,
                'path' => $request->getPathInfo(),
            ]);
        }

        return $next($request);
    }
}