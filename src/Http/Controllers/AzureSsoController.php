<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Platform\Core\PlatformCore;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\TeamInvitationService;

class AzureSsoController extends Controller
{
    /**
     * Provider für den initialen Redirect (mit prompt=select_account für Account-Auswahl)
     */
    protected function redirectProvider()
    {
        return Socialite::driver('azure-tenant')
            ->stateless()
            ->scopes([
                'openid',
                'profile',
                'email',
                'offline_access', // WICHTIG: Benötigt für Refresh Token
                'https://graph.microsoft.com/User.Read',
                'https://graph.microsoft.com/Calendars.ReadWrite',
                'https://graph.microsoft.com/Calendars.ReadWrite.Shared',
            ])
            ->with(['response_mode' => 'query', 'prompt' => 'select_account']);
    }

    /**
     * Provider für den Callback/Token-Exchange (OHNE prompt, sonst startet Microsoft neuen Auth-Flow)
     */
    protected function callbackProvider()
    {
        return Socialite::driver('azure-tenant')
            ->stateless();
    }

    public function redirectToProvider()
    {
        \Log::debug('Azure SSO redirect', [
            'tenant' => config('azure-sso.tenant') ?? config('azure-sso.tenant_id'),
            'redirect' => config('azure-sso.redirect'),
            'client_id' => config('services.microsoft.client_id'),
            'post_login_redirect' => config('azure-sso.post_login_redirect'),
        ]);

        return $this->redirectProvider()->redirect();
    }

    public function handleProviderCallback(Request $request)
    {
        // 1. Log callback entry
        \Log::info('Azure SSO callback received', [
            'url' => $request->fullUrl(),
            'has_code' => $request->has('code'),
            'has_error' => $request->has('error'),
            'has_state' => $request->has('state'),
            'all_params' => $request->query(),
            'session_id' => session()->getId(),
            'intended_url' => session()->get('url.intended'),
        ]);

        /** @var AuthAccessPolicy $policy */
        $policy = app(AuthAccessPolicy::class);

        if ($request->has('error')) {
            \Log::warning('Azure SSO error on callback', $request->only('error', 'error_description'));
            return redirect()->route('azure-sso.login')
                ->with('error', $request->input('error_description', 'Azure SSO error'));
        }

        // 2. Token exchange
        \Log::info('Azure SSO: Starting token exchange');

        try {
            $azureUser = $this->callbackProvider()->user();

            \Log::info('Azure SSO: Token exchange successful', [
                'azure_id' => $azureUser->getId(),
                'email' => $azureUser->getEmail(),
                'has_token' => !empty($azureUser->token),
                'has_refresh_token' => !empty($azureUser->refreshToken),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Azure SSO token exchange failed', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('azure-sso.login')
                ->with('error', 'Azure SSO konnte nicht abgeschlossen werden (Token-Exchange).');
        }

        // User processing - wrapped in try-catch to catch any exceptions
        try {
            $azureId = $azureUser->getId();
            $name    = $azureUser->getName() ?: ($azureUser->user['name'] ?? null);
            $email   = $azureUser->getEmail()
                       ?: ($azureUser->user['preferred_username'] ?? $azureUser->user['upn'] ?? null);
            $avatar  = $azureUser->getAvatar();

            \Log::info('Azure SSO: Extracted user data', [
                'azure_id' => $azureId,
                'name' => $name,
                'email' => $email,
                'has_avatar' => !empty($avatar),
            ]);

            $userModelClass = config('azure-sso.user_model') ?: config('auth.providers.users.model');

            \Log::info('Azure SSO: Looking up user', [
                'model_class' => $userModelClass,
                'azure_id' => $azureId,
                'email' => $email,
            ]);

            // Bestehenden Nutzer anhand azure_id ODER email finden (bevorzugt azure_id)
            $user = $userModelClass::query()
                ->when($azureId, fn($q) => $q->where('azure_id', $azureId))
                ->when(!$azureId && $email, fn($q) => $q->orWhere('email', $email))
                ->first();

            // Wenn kein Nutzer per azure_id gefunden wurde, aber eine Email existiert,
            // versuche den Nutzer strikt per Email zu finden (Unique-Constraint beachten)
            if (! $user && $email) {
                $user = $userModelClass::query()->where('email', $email)->first();
            }

            \Log::info('Azure SSO: User lookup result', [
                'found' => $user !== null,
                'user_id' => $user?->id,
                'is_new' => !$user,
            ]);

            if (! $user) {
                $user = new $userModelClass();
            }

            $isNewUser = ! $user->exists;

            // azure_id immer setzen, um zukünftige Logins stabil zu verknüpfen
            $user->azure_id = $azureId;
            if ($name || ! $user->name) {
                $user->name = $name ?: ($email ?? 'Azure User');
            }
            // Email nur setzen, wenn leer oder identisch, um Duplicate-Key zu vermeiden
            if ($email) {
                if (! $user->email || $user->email === $email) {
                    $user->email = $email;
                }
            }
            if ($avatar) {
                $user->avatar = $avatar;
            }

            $user->save();

            \Log::info('Azure SSO: User resolved', [
                'user_id' => $user->id,
                'email' => $user->email,
                'is_new_user' => $isNewUser,
                'azure_id' => $azureId,
            ]);

            if ($isNewUser) {
                \Log::info('Azure SSO: Creating personal team for new user', ['user_id' => $user->id]);
                try {
                    PlatformCore::createPersonalTeamFor($user);
                    \Log::info('Azure SSO: Personal team created successfully', ['user_id' => $user->id]);
                } catch (\Throwable $e) {
                    \Log::error('Azure SSO: Failed to create personal team', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            Auth::login($user, true);
            \Log::info('Azure SSO: User logged in', ['user_id' => $user->id, 'session_id' => session()->getId()]);

            // Token aus Socialite Provider holen und speichern
            try {
                $token = $azureUser->token ?? null;

                // Refresh Token kann auf verschiedene Weise zurückgegeben werden
                // Socialite gibt den Refresh Token manchmal nicht direkt zurück,
                // daher versuchen wir mehrere Wege
                $refreshToken = null;

                // 1. Direktes Property
                if (isset($azureUser->refreshToken)) {
                    $refreshToken = $azureUser->refreshToken;
                }
                // 2. Alternative Property-Name
                elseif (isset($azureUser->refresh_token)) {
                    $refreshToken = $azureUser->refresh_token;
                }
                // 3. Getter-Methode
                elseif (method_exists($azureUser, 'getRefreshToken')) {
                    $refreshToken = $azureUser->getRefreshToken();
                }
                // 4. Aus Token-Response extrahieren (falls verfügbar)
                elseif (method_exists($azureUser, 'accessTokenResponse')) {
                    $tokenResponse = $azureUser->accessTokenResponse;
                    $refreshToken = $tokenResponse['refresh_token'] ?? null;
                }

                $expiresIn = $azureUser->expiresIn ?? 3600; // Default: 1 Stunde

                if ($token) {
                    session(['microsoft_access_token_' . $user->id => $token]);

                    // Scopes aus dem Token extrahieren (falls verfügbar)
                    // Socialite gibt die Scopes nicht direkt zurück, daher verwenden wir die angeforderten Scopes
                    $scopes = [
                        'User.Read',
                        'Calendars.ReadWrite',
                        'Calendars.ReadWrite.Shared',
                    ];

                    // Log für Debugging
                    if (!$refreshToken) {
                        \Log::warning('Azure SSO: No refresh token received. User may need to re-authenticate.', [
                            'user_id' => $user->id,
                            'email' => $email,
                            'has_token' => !empty($token),
                        ]);
                    } else {
                        \Log::info('Azure SSO: Refresh token received successfully', [
                            'user_id' => $user->id,
                            'email' => $email,
                        ]);
                    }

                    $this->saveMicrosoftToken($user, $token, $refreshToken, $expiresIn, $scopes);
                }
            } catch (\Throwable $e) {
                \Log::warning('Failed to save Azure SSO token', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Offene Teameinladungen automatisch akzeptieren
            app(TeamInvitationService::class)->acceptAllForUser($user);

            // 3. Log post-login redirect
            $redirectTo = config('azure-sso.post_login_redirect', '/');
            $intendedUrl = redirect()->intended($redirectTo)->getTargetUrl();

            \Log::info('Azure SSO: Login complete, redirecting', [
                'user_id' => $user->id,
                'email' => $user->email,
                'configured_redirect' => $redirectTo,
                'actual_redirect' => $intendedUrl,
                'session_id' => session()->getId(),
            ]);

            return redirect()->intended($redirectTo);

        } catch (\Throwable $e) {
            \Log::error('Azure SSO: User processing failed', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('azure-sso.login')
                ->with('error', 'SSO Login fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $url = config('azure-sso.logout_url');

        return $url
            ? redirect()->away($url)
            : redirect(config('azure-sso.post_logout_redirect', '/'));
    }

    /**
     * Speichert Microsoft OAuth Token in der Datenbank
     */
    private function saveMicrosoftToken($user, string $token, ?string $refreshToken = null, ?int $expiresIn = null, ?array $scopes = null): void
    {
        try {
            // Prüfe ob Token-Tabelle existiert
            if (!\Illuminate\Support\Facades\Schema::hasTable('microsoft_oauth_tokens')) {
                return;
            }

            // Scopes verwenden oder Standard-Scopes
            $scopesToSave = $scopes ?? [
                'User.Read',
                'Calendars.ReadWrite',
                'Calendars.ReadWrite.Shared',
            ];

            // Bestehenden Token prüfen, um Refresh Token zu behalten falls vorhanden
            $existingToken = \Platform\Core\Models\MicrosoftOAuthToken::where('user_id', $user->id)->first();
            
            // Refresh Token behalten, falls kein neuer übergeben wurde
            $refreshTokenToSave = $refreshToken ?? $existingToken?->refresh_token;

            \Platform\Core\Models\MicrosoftOAuthToken::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $token,
                    'refresh_token' => $refreshTokenToSave, // Refresh Token behalten falls vorhanden
                    'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : now()->addHour(),
                    'scopes' => $scopesToSave,
                ]
            );
            
            // Warnung wenn kein Refresh Token vorhanden
            if (!$refreshTokenToSave) {
                \Log::warning('Azure SSO: No refresh token saved. User will need to re-authenticate when token expires.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to save Microsoft OAuth token', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}


