<?php

namespace Platform\Core;

use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\Models\Team;
use Platform\Core\Models\Module;
use Illuminate\Contracts\Auth\Authenticatable;
use Platform\Core\Enums\TeamRole;
use Illuminate\Support\Facades\Schema;

class PlatformCore
{
    // --- Module Registry ---
    public static function registerModule(array $moduleConfig): void
    {
        if (!Schema::hasTable('modules')) {
            // Tabelle noch nicht migriert – Registrierung überspringen
            return;
        }

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

        // --- 1. Modul einmalig in die DB schreiben ---
        // Scope-Type: 'single' = team-spezifisch, 'parent' = root-scoped
        // Kann direkt im $moduleConfig oder in der Config-Datei (z.B. config('crm.scope_type')) gesetzt werden
        $scopeType = $moduleConfig['scope_type'] 
            ?? config(strtolower($key) . '.scope_type') 
            ?? 'single';
        
        if (!in_array($scopeType, ['single', 'parent'])) {
            $scopeType = 'single'; // Fallback auf single bei ungültigem Wert
        }

        // Prüfe ob scope_type Spalte existiert (Migration könnte noch nicht ausgeführt sein)
        $hasScopeTypeColumn = Schema::hasColumn('modules', 'scope_type');

        $updateData = [
            'title'       => $moduleConfig['title'] ?? ucfirst($key),
            'description' => $moduleConfig['description'] ?? null,
            'url'         => $url,
            'config'      => $moduleConfig,
        ];

        // Nur scope_type hinzufügen, wenn Spalte existiert
        if ($hasScopeTypeColumn) {
            $updateData['scope_type'] = $scopeType;
        }

        Module::updateOrCreate(
            ['key' => $key],
            $updateData
        );

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

        // Modul registrieren (nur in Registry, nicht DB)
        ModuleRegistry::register($moduleConfig);
    }

    public static function getModule(string $key): ?array
    {
        return ModuleRegistry::get($key);
    }

    public static function getModules(): array
    {
        return collect(ModuleRegistry::all())
            ->toArray();
    }

    /**
     * Definiert die verfügbaren Modul-Gruppen mit Label und Sortierung.
     */
    public static function getModuleGroups(): array
    {
        return [
            'planning' => ['label' => 'Projekte & Planung', 'order' => 1],
            'content'  => ['label' => 'Kommunikation & Content', 'order' => 2],
            'sales'    => ['label' => 'Vertrieb & Kunden', 'order' => 3],
            'finance'  => ['label' => 'Finanzen & Verwaltung', 'order' => 4],
            'hr'       => ['label' => 'Personal & QM', 'order' => 5],
            'tools'    => ['label' => 'Support & Tools', 'order' => 6],
            'admin'    => ['label' => 'Administration', 'order' => 7],
            'other'    => ['label' => 'Sonstige', 'order' => 99],
        ];
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