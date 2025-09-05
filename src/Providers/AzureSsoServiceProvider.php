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


