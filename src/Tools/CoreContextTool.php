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
                'user' => $user ? [
                    'id' => $user->id, 
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null
                ] : null,
                'team' => $team ? [
                    'id' => $team->id, 
                    'name' => $team->name ?? null,
                    'slug' => $team->slug ?? null
                ] : null,
                'route' => $routeName,
                'module' => $module,
                'url' => $url,
                'current_time' => now()->format('Y-m-d H:i:s'),
                'timezone' => config('app.timezone')
            ],
            'message' => 'Aktueller User und Team Kontext geladen'
        ];
    }
}

?>

