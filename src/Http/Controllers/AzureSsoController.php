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
        // Eigene state-Verwaltung, da der Provider stateless() läuft (Socialite validiert
        // dann nicht selbst). Ohne das ist der Callback offen für Login-CSRF/Session-Fixation
        // (Angreifer startet den Flow, Opfer ruft dessen Callback-URL auf).
        $state = \Illuminate\Support\Str::random(40);
        session(['azure_sso_state' => $state]);

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
                'https://graph.microsoft.com/Team.ReadBasic.All',
                'https://graph.microsoft.com/Channel.ReadBasic.All',
                'https://graph.microsoft.com/ChannelMessage.Read.All',
                'https://graph.microsoft.com/ChannelMessage.Send',
                'https://graph.microsoft.com/Chat.ReadWrite',
                'https://graph.microsoft.com/ChatMessage.Read',
                'https://graph.microsoft.com/ChatMessage.Send',
            ])
            ->with(['state' => $state, 'response_mode' => 'query', 'prompt' => 'select_account']);
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

        // 1b. state-Prüfung gegen Login-CSRF (vor jedem Token-Exchange, da stateless() Socialite
        // dazu bringt, die eigene Validierung zu überspringen).
        $expectedState = session('azure_sso_state');
        session()->forget('azure_sso_state');

        if (! $expectedState || ! hash_equals((string) $expectedState, (string) $request->query('state'))) {
            \Log::warning('azure-sso: state mismatch', [
                'session_id' => session()->getId(),
                'has_expected_state' => (bool) $expectedState,
                'has_request_state' => $request->has('state'),
            ]);
            abort(403, 'Ungültiger Login-Vorgang. Bitte erneut anmelden.');
        }

        // 2. Token exchange
        \Log::info('Azure SSO: Starting token exchange');

        $provider = $this->callbackProvider();

        try {
            $azureUser = $provider->user();

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

        // 2b. Tenant-Check: tid stammt aus den bereits signaturgeprüften id_token-Claims
        // (Provider::getClaims() validiert Signatur, iss und exp gegen die JWKS des Tenants).
        try {
            $tid = $provider->getClaims()?->tid;
        } catch (\Throwable $e) {
            \Log::error('Azure SSO: id_token validation failed', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return redirect()->route('azure-sso.login')
                ->with('error', 'Azure SSO konnte nicht abgeschlossen werden (Token-Validierung).');
        }

        if (! $policy->isTenantAllowed($tid)) {
            \Log::warning('azure-sso: tenant not allowed', ['tid' => $tid]);
            abort(403, 'Dieser Microsoft-Tenant ist für diese Instanz nicht freigegeben.');
        }

        // 2c. Email-Allowlist-Check — VOR dem User-Lookup, damit auch kein Bestandsuser
        // reaktiviert wird, dessen Adresse inzwischen aus AUTH_ALLOWED_EMAILS/-_DOMAINS
        // gefallen ist. Bewusst außerhalb des folgenden try/catch(\Throwable), sonst würde
        // ein abort()/redirect hier vom generischen Catch als "SSO Login fehlgeschlagen"
        // umgedeutet (analog zum isTenantAllowed-Check oben).
        $email = $azureUser->getEmail()
                 ?: ($azureUser->user['preferred_username'] ?? $azureUser->user['upn'] ?? null);

        if (! $policy->isEmailAllowed($email)) {
            \Log::warning('azure-sso: email not allowed', ['email' => $email, 'tid' => $tid]);
            return redirect()->route('azure-sso.login')->withErrors([
                'sso' => 'Für diesen Zugang ist kein Konto freigegeben.',
            ]);
        }

        // User processing - wrapped in try-catch to catch any exceptions
        try {
            $azureId = $azureUser->getId();
            $name    = $azureUser->getName() ?: ($azureUser->user['name'] ?? null);
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

                if ($user) {
                    // Bestehender Account wird per Email an diese azure_id gebunden (Claiming).
                    // Abgesichert durch Tenant-Bindung (isTenantAllowed) + tid-Check vorgelagert,
                    // nicht durch eine Verifikation der Email selbst (Work-Accounts haben kein
                    // email_verified-Claim).
                    \Log::info('azure-sso: linked existing account via email', [
                        'user_id' => $user->id,
                        'email' => $email,
                        'tid' => $tid,
                    ]);
                }
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

            // Persönliches Team sicherstellen - für neue UND bestehende User
            $hasPersonalTeam = $user->teams()->where('personal_team', true)->exists();

            if (!$hasPersonalTeam) {
                \Log::info('Azure SSO: Creating personal team for user', [
                    'user_id' => $user->id,
                    'is_new_user' => $isNewUser,
                ]);
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
                        'Team.ReadBasic.All',
                        'Channel.ReadBasic.All',
                        'ChannelMessage.Read.All',
                        'ChannelMessage.Send',
                        'Chat.ReadWrite',
                        'ChatMessage.Read',
                        'ChatMessage.Send',
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
                'Team.ReadBasic.All',
                'Channel.ReadBasic.All',
                'ChannelMessage.Read.All',
                'ChannelMessage.Send',
                'Chat.ReadWrite',
                'ChatMessage.Read',
                'ChatMessage.Send',
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


