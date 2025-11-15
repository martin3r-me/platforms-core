<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\TeamInvitationService;

class TeamsSsoController extends Controller
{
    public function authenticate(Request $request)
    {
        // Teams JS SDK Token erhalten
        $teamsToken = $request->input('access_token');
        
        if (!$teamsToken) {
            return response()->json([
                'error' => 'No access token provided',
                'auth_url' => route('azure-sso.login')
            ], 401);
        }

        try {
            // Microsoft Graph API Token validieren
            $userInfo = $this->validateMicrosoftToken($teamsToken);
            
            if (!$userInfo) {
                return response()->json([
                    'error' => 'Invalid token',
                    'auth_url' => route('azure-sso.login')
                ], 401);
            }

            // AuthAccessPolicy prüfen
            /** @var AuthAccessPolicy $policy */
            $policy = app(AuthAccessPolicy::class);
            
            if (!$policy->isEmailAllowed($userInfo['email'])) {
                Log::warning('Teams SSO: Email not allowed', ['email' => $userInfo['email']]);
                return response()->json([
                    'error' => 'Access denied',
                    'auth_url' => route('azure-sso.login')
                ], 403);
            }

            // User finden oder erstellen
            $user = $this->findOrCreateUser($userInfo);
            
            if (!$user) {
                return response()->json([
                    'error' => 'User creation failed',
                    'auth_url' => route('azure-sso.login')
                ], 500);
            }

            // User authentifizieren
            Auth::login($user, true);

            // Token in Session speichern (für aktuelle Requests)
            session(['microsoft_access_token_' . $user->id => $teamsToken]);

            // Token in Datenbank speichern (für Commands/Background-Jobs)
            $this->saveMicrosoftToken($user, $teamsToken);

            // Offene Teameinladungen automatisch akzeptieren
            app(TeamInvitationService::class)->acceptAllForUser($user);
            
            Log::info('Teams SSO: User authenticated successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ],
                'redirect_url' => $request->input('redirect_url', '/')
            ]);

        } catch (\Throwable $e) {
            Log::error('Teams SSO authentication failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Authentication failed',
                'auth_url' => route('azure-sso.login')
            ], 500);
        }
    }

    public function getAuthStatus(Request $request)
    {
        if (Auth::check()) {
            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id' => Auth::id(),
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email
                ]
            ]);
        }

        return response()->json([
            'authenticated' => false,
            'auth_url' => route('azure-sso.login')
        ]);
    }

    private function validateMicrosoftToken(string $token): ?array
    {
        try {
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
     * 
     * HINWEIS: Teams SSO liefert keinen Refresh Token. 
     * Für automatisches Token-Refresh muss sich der User einmal über Azure SSO einloggen.
     */
    private function saveMicrosoftToken($user, string $token, ?string $refreshToken = null, ?int $expiresIn = null): void
    {
        try {
            // Prüfe ob Token-Tabelle existiert
            if (!\Illuminate\Support\Facades\Schema::hasTable('microsoft_oauth_tokens')) {
                return;
            }

            // Wenn bereits ein Refresh Token existiert, behalten wir es
            $existingToken = \Platform\Core\Models\MicrosoftOAuthToken::where('user_id', $user->id)->first();
            $refreshTokenToSave = $refreshToken ?? $existingToken?->refresh_token;

            \Platform\Core\Models\MicrosoftOAuthToken::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $token,
                    'refresh_token' => $refreshTokenToSave, // Refresh Token behalten falls vorhanden
                    'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : now()->addHour(),
                    'scopes' => ['User.Read', 'Calendars.ReadWrite', 'Calendars.ReadWrite.Shared'],
                ]
            );
            
            if (!$refreshTokenToSave) {
                Log::info('Teams SSO: No refresh token available. User should login via Azure SSO to get refresh token for automatic renewal.', [
                    'user_id' => $user->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to save Microsoft OAuth token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function findOrCreateUser(array $userInfo)
    {
        $userModelClass = config('auth.providers.users.model');
        
        // Nutzer anhand azure_id oder email finden (bevorzugt azure_id)
        $user = $userModelClass::query()
            ->when($userInfo['id'] ?? null, fn($q) => $q->where('azure_id', $userInfo['id']))
            ->when(!($userInfo['id'] ?? null) && ($userInfo['email'] ?? null), fn($q) => $q->orWhere('email', $userInfo['email']))
            ->first();

        // Falls nicht per azure_id gefunden, explizit via email suchen
        if (!$user && ($userInfo['email'] ?? null)) {
            $user = $userModelClass::query()->where('email', $userInfo['email'])->first();
        }

        if (!$user) {
            $user = new $userModelClass();
        }

        $isNewUser = !$user->exists;

        // Felder aktualisieren, ohne Email-Kollisionen zu erzeugen
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
    }
}
