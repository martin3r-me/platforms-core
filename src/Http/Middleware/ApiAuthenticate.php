<?php

namespace Platform\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Authentifizierungs-Middleware
 * 
 * Prüft Sanctum-Token und stellt sicher, dass der Benutzer authentifiziert ist.
 * Unterstützt auch optional Header-basierte Authentifizierung für eingebettete Szenarien.
 */
class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Versuche zuerst Sanctum-Authentifizierung
        if (Auth::guard('sanctum')->check()) {
            // Setze den authentifizierten User für den Request
            Auth::setUser(Auth::guard('sanctum')->user());
            return $next($request);
        }

        // Fallback: Header-basierte Authentifizierung (für eingebettete Szenarien)
        if ($this->authenticateViaHeaders($request)) {
            return $next($request);
        }

        // Keine Authentifizierung gefunden
        return response()->json([
            'success' => false,
            'message' => 'Nicht authentifiziert',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Versucht Authentifizierung über Header (z.B. für Teams-Embedding)
     */
    protected function authenticateViaHeaders(Request $request): bool
    {
        $email = $request->header('X-User-Email');
        
        if (!$email) {
            return false;
        }

        $userModelClass = config('auth.providers.users.model');
        
        if (!class_exists($userModelClass)) {
            return false;
        }

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $userModelClass::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        Auth::setUser($user);
        return true;
    }
}

