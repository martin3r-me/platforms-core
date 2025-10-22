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
                ] : null,
                'team' => $team ? [
                    'id' => $team->id,
                    'name' => $team->name ?? null,
                ] : null,
                'route' => $routeName,
                'module' => $module,
                'url' => $url,
                'current_time' => now()->format('Y-m-d H:i:s'),
                'timezone' => config('app.timezone'),
                'system_prompt' => 'Du bist ein Assistent, der den angegebenen Nutzer beim Bedienen der Plattform unterstützt. Beachte stets den aktuellen Scope (Route/Modul). Nutze Kontextwissen nur, wenn es eindeutig passt; andernfalls ignoriere es. Antworte kurz, präzise und auf Deutsch.'
            ],
            'message' => 'Aktueller User und Team Kontext geladen'
        ];
    }
}

?>

