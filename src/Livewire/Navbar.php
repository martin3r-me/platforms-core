<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Core\PlatformCore;
use Platform\Core\Models\Module;
use Platform\Core\Models\ModuleUsageCount;

class Navbar extends Component
{
    public array $favorites = [];
    public ?string $currentModuleKey = null;
    public bool $isAdmin = false;
    public bool $isOwner = false;
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
            $this->isOwner = $pivot && $pivot->role === 'owner';
        }

        $this->loadFavorites();
    }

    public function loadFavorites(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $team = $user->currentTeam;
        if (!$team) {
            return;
        }

        $allModules = PlatformCore::getVisibleModules();
        $allowedModules = $this->getAllowedModules($user, $allModules);

        // Get top modules by usage for this user+team
        $topKeys = ModuleUsageCount::topModules($user->id, $team->id, 5);

        if (!empty($topKeys)) {
            $this->favorites = collect($topKeys)
                ->filter(fn ($key) => isset($allowedModules[$key]))
                ->take(5)
                ->map(fn ($key) => $allowedModules[$key])
                ->values()
                ->toArray();
        }

        // Fallback: if no usage data yet, take first 3 allowed modules
        if (empty($this->favorites)) {
            $this->favorites = collect($allowedModules)
                ->take(3)
                ->values()
                ->toArray();
        }
    }

    protected function getAllowedModules($user, array $allModules): array
    {
        $baseTeam = $user->currentTeamRelation;
        if (!$baseTeam) {
            return [];
        }

        // Gebündelt (wie Middleware/hasAccess) statt 3 Queries je Modul
        $accessible = array_flip(Module::accessibleKeys($user, $baseTeam));

        return collect($allModules)
            ->filter(fn ($module) => isset($accessible[$module['key']]))
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

    public function render()
    {
        return view('platform::livewire.navbar');
    }
}
