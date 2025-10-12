<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\AuthAccessPolicy;

class TeamsSdkAuthMiddleware
{
    /**
     * Middleware für Microsoft Teams Tab Apps die über das Teams SDK authentifizieren
     * ohne Laravel Auth zu verwenden
     */
    public function handle(Request $request, Closure $next)
    {
        // Nur für Teams-Embedded-Routes aktivieren
        if (!$this->isTeamsRequest($request)) {
            return $next($request);
        }

        Log::info('🔍 TEAMS SDK AUTH MIDDLEWARE RUNNING', [
            'path' => $request->getPathInfo(),
            'headers' => $request->headers->all(),
            'query' => $request->query->all(),
            'method' => $request->getMethod(),
            'referer' => $request->header('referer'),
            'user_agent' => $request->header('user-agent')
        ]);

        // Teams SDK Context aus Request extrahieren
        $teamsContext = $this->extractTeamsContext($request);
        
        if (!$teamsContext) {
            Log::info('Teams SDK Auth: No context found, trying JWT token validation');
            
            // Fallback: Versuche JWT Token zu validieren
            $jwtUser = $this->validateJwtToken($request);
            if ($jwtUser) {
                Log::info('Teams SDK Auth: JWT user found', ['email' => $jwtUser['email']]);
                $request->attributes->set('teams_user', $jwtUser);
                $request->attributes->set('teams_context', ['user' => $jwtUser]);
                return $next($request);
            }
            
            // Fallback: Versuche SSO Headers zu extrahieren
            $ssoUser = $this->extractSsoUser($request);
            if ($ssoUser) {
                Log::info('Teams SDK Auth: SSO user found', ['email' => $ssoUser['email']]);
                $request->attributes->set('teams_user', $ssoUser);
                $request->attributes->set('teams_context', ['user' => $ssoUser]);
                return $next($request);
            }
            
            Log::info('Teams SDK Auth: No context, JWT or SSO found, allowing request (Teams SDK will handle auth)');
            return $next($request);
        }

        // User-Info aus Teams Context validieren
        $userInfo = $this->validateTeamsContext($teamsContext);
        
        if (!$userInfo) {
            Log::warning('Teams SDK Auth: Invalid context, trying SSO headers');
            
            // Fallback: Versuche SSO Headers zu extrahieren
            $ssoUser = $this->extractSsoUser($request);
            if ($ssoUser) {
                Log::info('Teams SDK Auth: SSO user found as fallback', ['email' => $ssoUser['email']]);
                $request->attributes->set('teams_user', $ssoUser);
                $request->attributes->set('teams_context', ['user' => $ssoUser]);
                return $next($request);
            }
            
            Log::warning('Teams SDK Auth: Invalid context and no SSO, allowing request (Teams SDK will handle auth)');
            return $next($request);
        }

        // AuthAccessPolicy prüfen (ohne Laravel Auth)
        /** @var AuthAccessPolicy $policy */
        $policy = app(AuthAccessPolicy::class);
        
        if (!$policy->isEmailAllowed($userInfo['email'])) {
            Log::warning('Teams SDK Auth: Email not allowed', ['email' => $userInfo['email']]);
            return response()->json(['error' => 'Access denied'], 403);
        }

        // User-Info an Request anhängen für spätere Verwendung
        $request->attributes->set('teams_user', $userInfo);
        $request->attributes->set('teams_context', $teamsContext);

        Log::info('Teams SDK Auth: Context validated', [
            'email' => $userInfo['email'],
            'user_id' => $userInfo['id'] ?? null
        ]);

        return $next($request);
    }

    private function isTeamsRequest(Request $request): bool
    {
        $path = $request->getPathInfo();
        $isEmbedded = str_contains($path, '/embedded/') || 
                      $request->header('X-Teams-Embedded') === 'true' ||
                      $request->query('teams_embedded') === 'true';
        
        Log::info('🔍 TEAMS REQUEST CHECK', [
            'path' => $path,
            'is_embedded' => $isEmbedded,
            'x_teams_embedded' => $request->header('X-Teams-Embedded'),
            'teams_embedded_query' => $request->query('teams_embedded')
        ]);
        
        return $isEmbedded;
    }

    private function extractTeamsContext(Request $request): ?array
    {
        // Teams Context aus verschiedenen Quellen extrahieren
        $context = null;

        // 1. Aus Request Headers
        if ($request->hasHeader('X-Teams-Context')) {
            $context = json_decode($request->header('X-Teams-Context'), true);
        }

        // 2. Aus Query Parameters
        if (!$context && $request->has('teams_context')) {
            $context = json_decode($request->query('teams_context'), true);
        }

        // 3. Aus Request Body (für POST requests)
        if (!$context && $request->isMethod('POST') && $request->has('teams_context')) {
            $context = $request->input('teams_context');
        }

        // 4. Aus Microsoft Teams SDK Headers (falls verfügbar)
        if (!$context) {
            $teamsHeaders = [
                'X-Teams-User-Id' => $request->header('X-Teams-User-Id'),
                'X-Teams-User-Email' => $request->header('X-Teams-User-Email'),
                'X-Teams-User-Name' => $request->header('X-Teams-User-Name'),
                'X-Teams-Tenant-Id' => $request->header('X-Teams-Tenant-Id'),
                'X-Teams-Team-Id' => $request->header('X-Teams-Team-Id'),
                'X-Teams-Channel-Id' => $request->header('X-Teams-Channel-Id'),
            ];
            
            $hasTeamsHeaders = array_filter($teamsHeaders);
            if (!empty($hasTeamsHeaders)) {
                $context = [
                    'user' => [
                        'id' => $teamsHeaders['X-Teams-User-Id'],
                        'email' => $teamsHeaders['X-Teams-User-Email'],
                        'name' => $teamsHeaders['X-Teams-User-Name'],
                    ],
                    'tenant' => $teamsHeaders['X-Teams-Tenant-Id'],
                    'team' => $teamsHeaders['X-Teams-Team-Id'],
                    'channel' => $teamsHeaders['X-Teams-Channel-Id'],
                ];
            }
        }

        Log::info('Teams SDK Auth: Context extraction', [
            'has_context' => !is_null($context),
            'context_keys' => $context ? array_keys($context) : null,
            'user_email' => $context['user']['email'] ?? null
        ]);

        return $context;
    }

    private function validateTeamsContext(array $context): ?array
    {
        try {
            // Basis-Validierung des Teams Context
            if (!isset($context['user']) || !isset($context['user']['email'])) {
                return null;
            }

            $user = $context['user'];
            
            // Mindest-Validierung der User-Daten
            if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            return [
                'id' => $user['id'] ?? null,
                'email' => $user['email'],
                'name' => $user['name'] ?? $user['email'],
                'tenant_id' => $context['tenant'] ?? null,
                'team_id' => $context['team'] ?? null,
                'channel_id' => $context['channel'] ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('Teams SDK Auth: Context validation failed', [
                'error' => $e->getMessage(),
                'context_preview' => json_encode(array_slice($context, 0, 3))
            ]);
            return null;
        }
    }

    /**
     * Extrahiert SSO User aus Request Headers
     */
    private function extractSsoUser(Request $request): ?array
    {
        try {
            // Microsoft Teams SSO Headers
            $email = $request->header('X-User-Email') ?: 
                     $request->header('X-User-Principal-Name') ?: 
                     $request->query('user_email') ?: 
                     $request->query('user_principal_name');
            
            $name = $request->header('X-User-Name') ?: 
                    $request->header('X-User-Display-Name') ?: 
                    $request->query('user_name') ?: 
                    $request->query('user_display_name');
            
            $userId = $request->header('X-User-Id') ?: 
                      $request->header('X-User-Object-Id') ?: 
                      $request->query('user_id') ?: 
                      $request->query('user_object_id');
            
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
            
            Log::info('Teams SDK Auth: SSO user extracted', [
                'email' => $email,
                'name' => $name,
                'user_id' => $userId
            ]);
            
            return [
                'id' => $userId,
                'email' => $email,
                'name' => $name ?: $email,
                'tenant_id' => $request->header('X-Tenant-Id'),
                'team_id' => $request->header('X-Team-Id'),
                'channel_id' => $request->header('X-Channel-Id'),
            ];
            
        } catch (\Throwable $e) {
            Log::error('Teams SDK Auth: SSO extraction failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validiert Microsoft Teams JWT Token
     */
    private function validateJwtToken(Request $request): ?array
    {
        try {
            // JWT Token aus verschiedenen Quellen extrahieren
            $token = $request->header('Authorization') ?: 
                     $request->header('X-Teams-Token') ?: 
                     $request->query('token') ?: 
                     $request->input('token');
            
            if (!$token) {
                return null;
            }
            
            // Bearer Token Format bereinigen
            if (str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }
            
            Log::info('Teams SDK Auth: JWT token found, attempting validation');
            
            // JWT Token dekodieren (ohne Validierung für jetzt)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                Log::warning('Teams SDK Auth: Invalid JWT format');
                return null;
            }
            
            $payload = json_decode(base64_decode($parts[1]), true);
            if (!$payload) {
                Log::warning('Teams SDK Auth: Could not decode JWT payload');
                return null;
            }
            
            // Teams-spezifische Claims extrahieren
            $email = $payload['preferred_username'] ?? $payload['email'] ?? $payload['upn'] ?? null;
            $name = $payload['name'] ?? $payload['given_name'] . ' ' . $payload['family_name'] ?? $email;
            $userId = $payload['oid'] ?? $payload['sub'] ?? null;
            $tenantId = $payload['tid'] ?? null;
            
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Teams SDK Auth: Invalid email in JWT', ['email' => $email]);
                return null;
            }
            
            Log::info('Teams SDK Auth: JWT user extracted', [
                'email' => $email,
                'name' => $name,
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            
            return [
                'id' => $userId,
                'email' => $email,
                'name' => $name,
                'tenant_id' => $tenantId,
                'team_id' => null,
                'channel_id' => null,
            ];
            
        } catch (\Throwable $e) {
            Log::error('Teams SDK Auth: JWT validation failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
