<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Route;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

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
        
        $displayName = null;
        if ($user) {
            $displayName = trim(($user->name ?? '') . ' ' . ($user->lastname ?? ''));
            if ($displayName === '') {
                $displayName = $user->name ?? null;
            }
        }

        // Debug: Timezone-Werte loggen (PRC-Bug hunting)
        $configTimezone = config('app.timezone');
        $envTimezone = env('APP_TIMEZONE');
        $phpTimezone = @date_default_timezone_get();
        \Log::debug('[CoreContextTool] Timezone values', [
            'config_app_timezone' => $configTimezone,
            'env_APP_TIMEZONE' => $envTimezone,
            'php_default_timezone' => $phpTimezone,
            'user_id' => $user?->id,
            'user_timezone' => $user?->timezone ?? '(not set)',
            'team_id' => $team?->id,
            'team_timezone' => $team?->timezone ?? '(not set)',
        ]);

        // SemanticLayer auflösen (Identität — aus DB/Cache, additiv zum Default-Prompt)
        $resolved = null;
        try {
            $resolver = app(SemanticLayerResolver::class);
            $resolved = $resolver->resolveFor($team, $module);
        } catch (\Throwable $e) {
            // Defensive: Layer-Resolution darf den Kontext nie brechen
            \Log::warning('[CoreContextTool] SemanticLayer-Resolution fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }

        // Verhaltens-Instruktion (Tool-Nutzung) bleibt hier — nur die Identität kommt aus dem Layer
        $baseInstruction = 'Du bist ein Assistent, der den angegebenen Nutzer beim Bedienen der Plattform unterstützt. Beachte stets den aktuellen Scope (Route/Modul). Nutze Kontextwissen nur, wenn es eindeutig passt; andernfalls ignoriere es. Antworte kurz, präzise und auf Deutsch. WICHTIG: Nutze die verfügbaren Tools proaktiv, um dem Nutzer zu helfen. Wenn der Nutzer nach Informationen fragt (z.B. "welche Teams", "zeige mir Projekte"), rufe das entsprechende Tool automatisch auf. Warte nicht darauf, dass der Nutzer explizit nach einem Tool fragt.';

        $layerBlock = ($resolved && !$resolved->isEmpty()) ? $resolved->rendered_block : null;
        $systemPrompt = $layerBlock
            ? trim($layerBlock . "\n\n" . $baseInstruction)
            : $baseInstruction;

        return [
            'ok' => true,
            'data' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $displayName,
                ] : null,
                'team' => $team ? [
                    'id' => $team->id,
                    'name' => $team->name ?? null,
                ] : null,
                'route' => $routeName,
                'module' => $module,
                'url' => $url,
                'current_time' => now()->format('Y-m-d H:i:s'),
                'timezone' => $configTimezone,
                '_debug_timezone' => [
                    'config' => $configTimezone,
                    'env' => $envTimezone,
                    'php' => $phpTimezone,
                ],
                'system_prompt' => $systemPrompt,
                'semantic_layer' => ($resolved && !$resolved->isEmpty()) ? $resolved->toArray() : null,
            ],
            'message' => 'Aktueller User und Team Kontext geladen'
        ];
    }
}

?>

