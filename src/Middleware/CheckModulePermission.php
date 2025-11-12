<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Module;

class CheckModulePermission
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        $moduleKey = $request->attributes->get('current_module');

        Log::info('CheckModulePermission: Start', [
            'user_id'    => $user?->id,
            'module_key' => $moduleKey,
        ]);

        if (!$moduleKey) {
            Log::warning('CheckModulePermission: Kein Modul-Kontext erkannt.', [
                'user_id' => $user?->id,
            ]);
            abort(403, 'Kein Modul-Kontext erkannt.');
        }

        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            Log::warning('CheckModulePermission: Modul nicht bekannt.', [
                'user_id' => $user?->id,
                'module_key' => $moduleKey,
            ]);
            abort(403, 'Modul nicht bekannt.');
        }

        $baseTeam = $user->currentTeamRelation; // Basis-Team (nicht dynamisch)
        if (!$baseTeam) {
            abort(403, 'Kein Team zugeordnet.');
        }

        // Zentrale Berechtigungsprüfung verwenden
        $hasPermission = $module->hasAccess($user, $baseTeam);

        Log::info('CheckModulePermission: Berechtigung geprüft', [
            'user_id'      => $user?->id,
            'module_id'    => $module->id,
            'team_id'      => $baseTeam->id,
            'has_permission' => $hasPermission,
            'is_root_scoped' => $module->isRootScoped(),
        ]);

        if (!$hasPermission) {
            Log::warning('CheckModulePermission: Zugriff verweigert.', [
                'user_id'   => $user?->id,
                'team_id'   => $baseTeam->id ?? null,
                'module_id' => $module->id,
            ]);
            abort(403, 'Du hast für dieses Modul keine Berechtigung.');
        }

        Log::info('CheckModulePermission: Zugriff erlaubt', [
            'user_id'   => $user?->id,
            'team_id'   => $baseTeam->id ?? null,
            'module_id' => $module->id,
        ]);

        return $next($request);
    }
}