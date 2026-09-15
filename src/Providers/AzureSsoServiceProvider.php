<?php

namespace Platform\Core\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use Platform\Core\Middleware\ResolveAzureTenant;
use Platform\Core\Providers\TenantAwareMicrosoftProvider;

class AzureSsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/azure-sso.php', 'azure-sso');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/azure-sso.php');

        $this->app->make(Router::class)->aliasMiddleware(
            'azure.tenant',
            ResolveAzureTenant::class
        );

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $cfg = config('azure-sso');

        // #723: common + leere allowed_tenants-Whitelist ist praktisch immer ein
        // Konfigurationsfehler - die .All-Graph-Scopes sind sonst der einzige Türsteher
        // gegen fremde Tenants (siehe Board "Entra SSO — Konsolidierung & Härtung").
        $tenant = $cfg['tenant'] ?? ($cfg['tenant_id'] ?? 'common');
        if ($tenant === 'common' && empty(config('auth-policy.allowed_tenants', []))) {
            \Log::warning('azure-sso: tenant=common ohne auth-policy.allowed_tenants gesetzt — jeder Microsoft-Tenant kann sich einloggen.');
        }

        Socialite::extend('azure-tenant', function () use ($cfg) {
            return Socialite::buildProvider(
                TenantAwareMicrosoftProvider::class,
                [
                    'client_id'     => $cfg['client_id'] ?? null,
                    'client_secret' => $cfg['client_secret'] ?? null,
                    'redirect'      => $cfg['redirect'] ?? null,
                    'tenant'        => $cfg['tenant'] ?? ($cfg['tenant_id'] ?? 'common'),
                ]
            );
        });
    }
}


