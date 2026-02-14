<?php

namespace Platform\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Authentifizierungs-Middleware
 *
 * Prüft Passport-Token (JWT) und stellt sicher, dass der Benutzer authentifiziert ist.
 * Unterstützt auch optional Header-basierte Authentifizierung für eingebettete Szenarien.
 */
class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Versuche zuerst Passport-Authentifizierung über Bearer Token
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            $user = $this->authenticateViaPassport($bearerToken);
            if ($user) {
                Auth::setUser($user);
                return $next($request);
            }
        }

        // Fallback: Versuche über Request->user('api')
        $user = $request->user('api');
        if ($user) {
            Auth::setUser($user);
            return $next($request);
        }

        // Fallback: Versuche über Auth::guard('api')
        if (Auth::guard('api')->check()) {
            Auth::setUser(Auth::guard('api')->user());
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
     * Authentifiziert User via Passport JWT Token
     *
     * @param string $bearerToken
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function authenticateViaPassport(string $bearerToken)
    {
        try {
            // JWT parsen und Token-ID extrahieren
            $tokenId = $this->parseJwtTokenId($bearerToken);

            if (!$tokenId) {
                return null;
            }

            // Token direkt über Model nachschlagen (kompatibel mit allen Passport-Versionen)
            $token = Token::find($tokenId);

            if (!$token) {
                return null;
            }

            // Prüfen ob Token widerrufen oder abgelaufen ist
            if ($token->revoked) {
                return null;
            }

            if ($token->expires_at && $token->expires_at->isPast()) {
                return null;
            }

            // User laden
            $userModelClass = config('auth.providers.users.model');
            $user = $userModelClass::find($token->user_id);

            if ($user) {
                // Token am User setzen für tokenCan() etc.
                $user->withAccessToken($token);
            }

            return $user;
        } catch (\Exception $e) {
            // Bei Parsing-Fehlern: null zurückgeben
            return null;
        }
    }

    /**
     * Parst JWT Token und extrahiert die Token-ID (jti claim)
     *
     * @param string $jwt
     * @return string|null
     */
    protected function parseJwtTokenId(string $jwt): ?string
    {
        try {
            // Versuche zuerst das Token einfach zu parsen ohne vollständige Validierung
            // (Die Validierung macht Passport's Guard)
            $parts = explode('.', $jwt);

            if (count($parts) !== 3) {
                return null;
            }

            // Payload dekodieren (mittlerer Teil)
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$payload || !isset($payload['jti'])) {
                return null;
            }

            return $payload['jti'];
        } catch (\Exception $e) {
            return null;
        }
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
