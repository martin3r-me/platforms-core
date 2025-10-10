<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\AuthAccessPolicy;

class TeamsSsoMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Nur für Teams-Embedded-Routes aktivieren (außer Config-Routes)
        if (!$this->isTeamsRequest($request)) {
            return $next($request);
        }

        // Config-Routes: Einloggen aber nicht weiterleiten
        if ($this->isConfigRoute($request)) {
            // Versuche SSO, aber leite nicht weiter
            $this->attemptSsoLogin($request);
            return $next($request);
        }

        // Prüfen ob bereits authentifiziert
        if (Auth::check()) {
            return $next($request);
        }

        // Teams SSO Token aus Request extrahieren
        $teamsToken = $this->extractTeamsToken($request);
        
        if (!$teamsToken) {
            Log::info('Teams SSO: No token found, redirecting to Azure SSO');
            return redirect()->route('azure-sso.login');
        }

        // Token validieren und User authentifizieren
        $user = $this->authenticateWithTeamsToken($teamsToken, $request);
        
        if (!$user) {
            Log::warning('Teams SSO: Authentication failed');
            return redirect()->route('azure-sso.login');
        }

        Auth::login($user, true);
        Log::info('Teams SSO: User authenticated', ['user_id' => $user->id]);

        return $next($request);
    }

    private function attemptSsoLogin(Request $request): void
    {
        // Nur versuchen wenn noch nicht eingeloggt
        if (Auth::check()) {
            return;
        }

        // Teams SSO Token aus Request extrahieren
        $teamsToken = $this->extractTeamsToken($request);
        
        if (!$teamsToken) {
            Log::info('Teams SSO: No token found for config route');
            return;
        }

        // Token validieren und User authentifizieren (ohne Redirect)
        $user = $this->authenticateWithTeamsToken($teamsToken, $request);
        
        if ($user) {
            Auth::login($user, true);
            Log::info('Teams SSO: User authenticated for config route', ['user_id' => $user->id]);
        } else {
            Log::warning('Teams SSO: Authentication failed for config route');
        }
    }

    private function isTeamsRequest(Request $request): bool
    {
        // Prüfen ob Request von Teams kommt
        $userAgent = $request->userAgent();
        $referer = $request->header('referer');
        
        return str_contains($userAgent, 'Teams') || 
               str_contains($referer, 'teams.microsoft.com') ||
               $request->has('teams_context');
    }

    private function isConfigRoute(Request $request): bool
    {
        // Config-Routes identifizieren (dort soll User Projekt auswählen können)
        $path = $request->path();
        return str_contains($path, '/embedded/teams/config') ||
               str_contains($path, '/embedded/planner/teams/config') ||
               str_contains($path, '/embedded/test');
    }

    private function extractTeamsToken(Request $request): ?string
    {
        // Token aus verschiedenen Quellen extrahieren
        return $request->header('Authorization') ?: 
               $request->input('access_token') ?: 
               $request->cookie('teams_token');
    }

    private function authenticateWithTeamsToken(string $token, Request $request)
    {
        try {
            // Microsoft Graph API Token validieren
            $userInfo = $this->validateMicrosoftToken($token);
            
            if (!$userInfo) {
                return null;
            }

            // AuthAccessPolicy prüfen
            /** @var AuthAccessPolicy $policy */
            $policy = app(AuthAccessPolicy::class);
            
            if (!$policy->isEmailAllowed($userInfo['email'])) {
                Log::warning('Teams SSO: Email not allowed', ['email' => $userInfo['email']]);
                return null;
            }

            // User finden oder erstellen
            $userModelClass = config('auth.providers.users.model');
            $user = $userModelClass::query()
                ->where('email', $userInfo['email'])
                ->orWhere('azure_id', $userInfo['id'])
                ->first();

            if (!$user) {
                $user = new $userModelClass();
                $user->email = $userInfo['email'];
                $user->name = $userInfo['name'] ?? $userInfo['email'];
                $user->azure_id = $userInfo['id'];
                $user->save();
                
                // Personal Team erstellen
                \Platform\Core\PlatformCore::createPersonalTeamFor($user);
            }

            return $user;

        } catch (\Throwable $e) {
            Log::error('Teams SSO authentication failed', [
                'error' => $e->getMessage(),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);
            return null;
        }
    }

    private function validateMicrosoftToken(string $token): ?array
    {
        try {
            // Microsoft Graph API aufrufen um Token zu validieren
            $response = \Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])->get('https://graph.microsoft.com/v1.0/me');

            if (!$response->successful()) {
                Log::warning('Teams SSO: Microsoft Graph API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            
            return [
                'id' => $data['id'] ?? null,
                'email' => $data['mail'] ?? $data['userPrincipalName'] ?? null,
                'name' => $data['displayName'] ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('Teams SSO: Token validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
