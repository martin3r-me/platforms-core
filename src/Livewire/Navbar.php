<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\PlatformCore;
use Platform\Core\Models\Module;

class Navbar extends Component
{
    public array $favorites = [];
    public ?string $currentModuleKey = null;
    public bool $isAdmin = false;
    public ?string $currentTeamName = null;
    public ?string $userName = null;
    public ?string $userAvatar = null;

    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $this->currentModuleKey = request()->segment(1) ?: null;
        $this->userName = $user->name;
        $this->userAvatar = $user->avatar ?? null;

        $team = $user->currentTeam;
        $this->currentTeamName = $team?->name;

        // Admin check: OWNER or ADMIN on current team
        if ($team) {
            $pivot = $user->teams()->where('team_id', $team->id)->first()?->pivot;
            $this->isAdmin = $pivot && in_array($pivot->role, ['owner', 'admin']);
        }

        $this->loadFavorites();
    }

    public function loadFavorites(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $savedFavorites = $user->navbar_favorites ?? [];
        $allModules = PlatformCore::getVisibleModules();

        // Build lookup of allowed non-admin modules
        $allowedModules = $this->getAllowedNonAdminModules($user, $allModules);

        if (!empty($savedFavorites)) {
            // Resolve saved favorites against allowed modules
            $this->favorites = collect($savedFavorites)
                ->filter(fn ($key) => isset($allowedModules[$key]))
                ->take(5)
                ->map(fn ($key) => $allowedModules[$key])
                ->values()
                ->toArray();
        }

        // Auto-fill: if empty, take first 3 allowed non-admin modules
        if (empty($this->favorites)) {
            $this->favorites = collect($allowedModules)
                ->take(3)
                ->values()
                ->toArray();
        }
    }

    protected function getAllowedNonAdminModules($user, array $allModules): array
    {
        $baseTeam = $user->currentTeamRelation;
        if (!$baseTeam) {
            return [];
        }

        $rootTeam = $baseTeam->getRootTeam();

        return collect($allModules)
            ->filter(function ($module) use ($user, $baseTeam, $rootTeam) {
                // Exclude admin group
                if (($module['group'] ?? 'other') === 'admin') {
                    return false;
                }

                $moduleModel = Module::where('key', $module['key'])->first();
                if (!$moduleModel) {
                    return false;
                }

                $checkTeam = $moduleModel->isRootScoped() ? $rootTeam : $baseTeam;
                $checkTeamId = $checkTeam->id;

                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $checkTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();

                $teamAllowed = $checkTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();

                return $userAllowed || $teamAllowed;
            })
            ->mapWithKeys(function ($module) {
                $key = $module['key'];
                $icon = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                $routeName = $module['navigation']['route'] ?? null;
                $url = ($routeName && \Illuminate\Support\Facades\Route::has($routeName))
                    ? route($routeName)
                    : ($module['url'] ?? '/' . $key);

                return [$key => [
                    'key' => $key,
                    'title' => $module['title'] ?? $module['label'] ?? ucfirst($key),
                    'icon' => $icon,
                    'url' => $url,
                ]];
            })
            ->toArray();
    }

    public function removeFavorite(string $key): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $favorites = $user->navbar_favorites ?? [];
        $favorites = array_values(array_filter($favorites, fn ($k) => $k !== $key));
        $user->navbar_favorites = $favorites;
        $user->save();

        $this->loadFavorites();
    }

    public function addFavorite(string $key): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $favorites = $user->navbar_favorites ?? [];

        if (count($favorites) >= 5 || in_array($key, $favorites)) {
            return;
        }

        $favorites[] = $key;
        $user->navbar_favorites = $favorites;
        $user->save();

        $this->loadFavorites();
    }

    public function render()
    {
        return view('platform::livewire.navbar');
    }
}
