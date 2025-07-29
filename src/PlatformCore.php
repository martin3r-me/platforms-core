<?php

namespace Platform\Core;

use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\Models\Team;
use Illuminate\Contracts\Auth\Authenticatable;
use Platform\Core\Enums\TeamRole;

class PlatformCore
{
    // --- Module Registry ---
    public static function registerModule(array $moduleConfig): void
    {
        $key = $moduleConfig['key'] ?? null;
        if (!$key) {
            throw new \InvalidArgumentException('Module key is required.');
        }

        // Basis-Domain aus APP_URL
        $baseUrl = config('app.url');
        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? 'localhost';

        $routing = $moduleConfig['routing'] ?? [];
        $mode = $routing['mode'] ?? 'path';
        $prefix = $routing['prefix'] ?? strtolower($key);

        // Basis-URL automatisch berechnen
        $url = $mode === 'subdomain'
            ? "{$scheme}://{$key}.{$host}"
            : "{$scheme}://{$host}/{$prefix}";
        $moduleConfig['url'] = $url;

        // Navigation-Daten sicher zusammenführen
        $navigation = $moduleConfig['navigation'] ?? [];
        if (!is_array($navigation)) {
            $navigation = [];
        }

        $routeName = $navigation['route'] ?? null;

        $moduleConfig['navigation'] = array_merge([
            'title' => $moduleConfig['title'] ?? ucfirst($key),
            'route' => $routeName, // explizit absichern
            'icon'  => $navigation['icon'] ?? null,
            'order' => $navigation['order'] ?? 999,
        ], $navigation);

        // Sidebar validieren
        $moduleConfig['sidebar'] = is_array($moduleConfig['sidebar'] ?? null)
            ? $moduleConfig['sidebar']
            : [];

        // Debug-Log (damit du prüfen kannst, ob route jetzt gesetzt ist)
        \Log::info('Module registered', [
            'key' => $key,
            'mode' => $mode,
            'url' => $url,
            'route' => $moduleConfig['navigation']['route'] ?? null,
        ]);

        // Modul registrieren
        ModuleRegistry::register($moduleConfig);
    }

    public static function getModule(string $key): ?array
    {
        return ModuleRegistry::get($key);
    }

    public static function getModules(): array
    {
        // Alle Module sortiert nach navigation.order zurückgeben
        return collect(ModuleRegistry::all())
            ->toArray();
    }

    public static function getVisibleModules(): array
    {
        $currentGuard = auth()->getDefaultDriver() ?? 'web';

        return collect(ModuleRegistry::all())
            ->filter(fn ($module) => ($module['guard'] ?? 'web') === $currentGuard)
            ->sortBy('navigation.order')
            ->toArray();
    }

    // --- Teams (unverändert) ---
    public static function createPersonalTeamFor(Authenticatable $user): Team
    {
        $team = Team::create([
            'user_id'       => $user->id,
            'name'          => "{$user->name}'s Team",
            'personal_team' => true,
        ]);

        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->teams()->attach($team, ['role' => TeamRole::OWNER->value]);

        return $team;
    }
}