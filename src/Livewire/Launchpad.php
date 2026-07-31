<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Platform\Core\PlatformCore;

/**
 * Launchpad — speist den nx-launchpad-Overlay mit den Modulen des Users.
 *
 * Nutzt exakt denselben Datenpfad wie Navbar/ModuleFlyout: sichtbare Module
 * (nach Guard) gefiltert über Module::hasAccess() (respektiert Graph-Authz
 * unter Enforcement, sonst modulables-Fallback). Flach, alphabetisch.
 */
class Launchpad extends Component
{
    public array $modules = [];
    public array $anchors = [];
    public array $favorites = [];

    public function mount(): void
    {
        $this->loadModules();
    }

    public function loadModules(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->modules = [];
            return;
        }

        $baseTeam = $user->currentTeamRelation; // Basis-Team (nicht dynamisch)
        if (! $baseTeam) {
            $this->modules = [];
            return;
        }

        $all = collect(PlatformCore::getVisibleModules())
            ->filter(function ($module) use ($user, $baseTeam) {
                $moduleModel = \Platform\Core\Models\Module::where('key', $module['key'])->first();

                return $moduleModel && $moduleModel->hasAccess($user, $baseTeam);
            })
            ->map(function ($module) {
                $title     = $module['title'] ?? $module['label'] ?? ucfirst($module['key'] ?? '');
                $icon      = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                $routeName = $module['navigation']['route'] ?? null;
                $url       = $routeName && Route::has($routeName)
                    ? route($routeName)
                    : ($module['url'] ?? '#');

                return [
                    'key'   => $module['key'],
                    'title' => $title,
                    'icon'  => $icon,
                    'url'   => $url,
                    'group' => $module['group'] ?? 'other',
                ];
            })
            ->sortBy(fn ($m) => $m['title'])
            ->values();

        // Anker (strukturelle Einstiegspunkte) nach vorn, in Config-Reihenfolge,
        // aus dem Kategorie-Raster herausgelöst.
        $anchorKeys = (array) config('platform.launchpad.anchors', []);
        $byKey      = $all->keyBy('key');

        $this->anchors = collect($anchorKeys)
            ->map(fn ($k) => $byKey->get($k))
            ->filter()
            ->values()
            ->toArray();

        $this->modules = $all
            ->reject(fn ($m) => in_array($m['key'], $anchorKeys, true))
            ->values()
            ->toArray();

        // Favoriten = meistgenutzte Module (ModuleUsageCount), ohne Anker,
        // max. eine Zeile. Team-Kontext wie in der Navbar (currentTeam).
        $currentTeam = $user->currentTeam;
        $favKeys = $currentTeam
            ? \Platform\Core\Models\ModuleUsageCount::topModules($user->id, $currentTeam->id, 8)
            : [];

        $this->favorites = collect($favKeys)
            ->reject(fn ($k) => in_array($k, $anchorKeys, true))
            ->map(fn ($k) => $byKey->get($k))
            ->filter()
            ->take(7)
            ->values()
            ->toArray();
    }

    public function render()
    {
        return view('platform::livewire.launchpad');
    }
}
