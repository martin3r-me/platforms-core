<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmbeddedHeaderAuth
{
    /**
     * Versucht bei fehlender Session anhand von Headern den Nutzer einzuloggen.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            $email = $request->header('X-User-Email');
            $name = $request->header('X-User-Name');

            if ($email) {
                $userModelClass = config('auth.providers.users.model');
                /** @var \Illuminate\Database\Eloquent\Model $user */
                $user = $userModelClass::where('email', $email)->first();

                if (!$user) {
                    $user = new $userModelClass();
                    $user->email = $email;
                    $user->name = $name ?: $email;
                    $user->save();

                    // Persönliches Team anlegen, falls Projekt dies erwartet
                    try {
                        \Platform\Core\PlatformCore::createPersonalTeamFor($user);
                    } catch (\Throwable $e) {
                        // ignorieren – optional abhängig vom Projekt
                    }
                }

                Auth::login($user);
                // Session sicherstellen
                try { $request->session()->regenerate(); } catch (\Throwable $e) {}
            }
        }

        return $next($request);
    }
}


