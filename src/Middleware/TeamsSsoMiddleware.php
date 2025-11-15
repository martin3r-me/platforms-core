<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\TeamInvitationService;

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
            // Versuche SSO, aber lade Seite normal
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
        
        // Token in Session speichern (für aktuelle Requests)
        session(['microsoft_access_token_' . $user->id => $teamsToken]);
        
        // Token in Datenbank speichern (für Commands/Background-Jobs)
        $this->saveMicrosoftToken($user, $teamsToken);
        
        // Offene Teameinladungen automatisch akzeptieren (Teams-Embedded)
        app(TeamInvitationService::class)->acceptAllForUser($user);
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

        try {
            // Token validieren und User authentifizieren (ohne Redirect)
            $user = $this->authenticateWithTeamsToken($teamsToken, $request);
            
            if ($user) {
                Auth::login($user, true);
                // Auto-Akzept in Config-Flow auch durchführen
                app(TeamInvitationService::class)->acceptAllForUser($user);
                Log::info('Teams SSO: User authenticated for config route', ['user_id' => $user->id]);
            } else {
                Log::warning('Teams SSO: Authentication failed for config route');
            }
        } catch (\Throwable $e) {
            Log::error('Teams SSO: Error during config route authentication', ['error' => $e->getMessage()]);
            // Bei Fehlern einfach weitermachen, kein Redirect
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

            // User finden oder aktualisieren (azure_id bevorzugt, fallback email)
            $userModelClass = config('auth.providers.users.model');
            $user = $userModelClass::query()
                ->when($userInfo['id'] ?? null, fn($q) => $q->where('azure_id', $userInfo['id']))
                ->when(!($userInfo['id'] ?? null) && ($userInfo['email'] ?? null), fn($q) => $q->orWhere('email', $userInfo['email']))
                ->first();

            if (!$user && ($userInfo['email'] ?? null)) {
                $user = $userModelClass::query()->where('email', $userInfo['email'])->first();
            }

            if (!$user) {
                $user = new $userModelClass();
            }

            $isNewUser = ! $user->exists;

            $user->azure_id = $userInfo['id'] ?? $user->azure_id;
            if (($userInfo['name'] ?? null) || ! $user->name) {
                $user->name = $userInfo['name'] ?? ($userInfo['email'] ?? $user->name);
            }
            if ($userInfo['email'] ?? null) {
                if (! $user->email || $user->email === $userInfo['email']) {
                    $user->email = $userInfo['email'];
                }
            }

            $user->save();

            if ($isNewUser) {
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

    /**
     * Speichert Microsoft OAuth Token in der Datenbank
     */
    private function saveMicrosoftToken($user, string $token): void
    {
        try {
            // Prüfe ob Token-Tabelle existiert
            if (!\Illuminate\Support\Facades\Schema::hasTable('microsoft_oauth_tokens')) {
                return;
            }

            \Platform\Core\Models\MicrosoftOAuthToken::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $token,
                    'expires_at' => now()->addHour(), // Teams Token sind typischerweise 1 Stunde gültig
                    'scopes' => ['User.Read', 'Calendars.ReadWrite', 'Calendars.ReadWrite.Shared'],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to save Microsoft OAuth token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
