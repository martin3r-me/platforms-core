<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Route;

class CoreContextTool
{
    public function getContext(): array
    {
        $user = auth()->user();
        $team = $user?->currentTeam;
        $routeName = Route::currentRouteName();
        $url = url()->current();
        $module = null;
        if (is_string($routeName) && str_contains($routeName, '.')) {
            $module = strstr($routeName, '.', true);
        }
        return [
            'ok' => true,
            'data' => [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name ?? null] : null,
                'team' => $team ? ['id' => $team->id, 'name' => $team->name ?? null] : null,
                'route' => $routeName,
                'module' => $module,
                'url' => $url,
            ],
            'message' => 'Kontext bereit',
        ];
    }
}

?>

