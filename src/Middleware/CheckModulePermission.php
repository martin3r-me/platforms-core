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

        $team = method_exists($user, 'currentTeam') ? $user->currentTeam : null;

        $hasPermission = $user->modules()->where('module_id', $module->id)->wherePivot('enabled', true)->exists();

        Log::info('CheckModulePermission: User checked', [
            'user_id'      => $user?->id,
            'module_id'    => $module->id,
            'user_allowed' => $hasPermission,
        ]);

        if (!$hasPermission && $team) {
            $hasPermission = $team->modules()->where('module_id', $module->id)->wherePivot('enabled', true)->exists();
            Log::info('CheckModulePermission: Team checked', [
                'team_id'      => $team->id ?? null,
                'module_id'    => $module->id,
                'team_allowed' => $hasPermission,
            ]);
        }

        if (!$hasPermission) {
            Log::warning('CheckModulePermission: Zugriff verweigert.', [
                'user_id'   => $user?->id,
                'team_id'   => $team->id ?? null,
                'module_id' => $module->id,
            ]);
            abort(403, 'Du hast für dieses Modul keine Berechtigung.');
        }

        Log::info('CheckModulePermission: Zugriff erlaubt', [
            'user_id'   => $user?->id,
            'team_id'   => $team->id ?? null,
            'module_id' => $module->id,
        ]);

        return $next($request);
    }
}