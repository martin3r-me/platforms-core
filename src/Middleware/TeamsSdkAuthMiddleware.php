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
            Log::info('Teams SDK Auth: No context found, allowing request (Teams SDK will handle auth)');
            return $next($request);
        }

        // User-Info aus Teams Context validieren
        $userInfo = $this->validateTeamsContext($teamsContext);
        
        if (!$userInfo) {
            Log::warning('Teams SDK Auth: Invalid context, allowing request (Teams SDK will handle auth)');
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
}
