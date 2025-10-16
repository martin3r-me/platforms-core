<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Platform\Core\PlatformCore;
use Platform\Core\Contracts\AuthAccessPolicy;

class AzureSsoController extends Controller
{
    protected function provider()
    {
        return Socialite::driver('azure-tenant')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with(['response_mode' => 'query']);
    }

    public function redirectToProvider()
    {
        \Log::debug('Azure SSO redirect', [
            'tenant'   => config('azure-sso.tenant') ?? config('azure-sso.tenant_id'),
            'redirect' => config('azure-sso.redirect'),
        ]);

        return $this->provider()->redirect();
    }

    public function handleProviderCallback(Request $request)
    {
        /** @var AuthAccessPolicy $policy */
        $policy = app(AuthAccessPolicy::class);

        if ($request->has('error')) {
            \Log::warning('Azure SSO error on callback', $request->only('error', 'error_description'));
            return redirect()->route('azure-sso.login')
                ->with('error', $request->input('error_description', 'Azure SSO error'));
        }

        try {
            $azureUser = $this->provider()->user();
        } catch (\Throwable $e) {
            \Log::error('Azure SSO token exchange failed', [
                'message' => $e->getMessage(),
                'class'   => get_class($e),
            ]);
            return redirect()->route('azure-sso.login')
                ->with('error', 'Azure SSO konnte nicht abgeschlossen werden (Token-Exchange).');
        }

        $azureId = $azureUser->getId();
        $name    = $azureUser->getName() ?: ($azureUser->user['name'] ?? null);
        $email   = $azureUser->getEmail()
                   ?: ($azureUser->user['preferred_username'] ?? $azureUser->user['upn'] ?? null);
        $avatar  = $azureUser->getAvatar();

        if (! $policy->isEmailAllowed($email)) {
            \Log::warning('SSO denied by policy: email not allowed', ['email' => $email]);
            return redirect()->route('azure-sso.login')->with('error', 'Zugriff verweigert.');
        }

        $tenant = config('services.microsoft.tenant');
        if (! $policy->isTenantAllowed($tenant)) {
            \Log::warning('SSO denied by policy: tenant not allowed', ['tenant' => $tenant]);
            return redirect()->route('azure-sso.login')->with('error', 'Zugriff verweigert.');
        }

        $userModelClass = config('azure-sso.user_model') ?: config('auth.providers.users.model');

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

        if ($isNewUser) {
            PlatformCore::createPersonalTeamFor($user);
        }

        Auth::login($user, true);

        return redirect()->intended(config('azure-sso.post_login_redirect', '/'));
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
}


