<?php

namespace Platform\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\ConfigAuthAccessPolicy;
use Platform\Core\Contracts\CrmCompanyResolverInterface;
use Platform\Core\Contracts\CrmContactResolverInterface;
use Platform\Core\Services\NullCrmCompanyResolver;
use Platform\Core\Services\NullCrmContactResolver;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthAccessPolicy::class, fn() => new ConfigAuthAccessPolicy());
        // Default-Resolver (können von CRM überschrieben werden)
        $this->app->singleton(CrmCompanyResolverInterface::class, fn() => new NullCrmCompanyResolver());
        $this->app->singleton(CrmContactResolverInterface::class, fn() => new NullCrmContactResolver());
        // Registriere Module beim Booten
        $this->app->booted(function () {
            $this->registerModules();
        });
    }

    public function boot(): void
    {
        // Hier können weitere Boot-Logik hinzugefügt werden
    }

    private function registerModules(): void
    {
        // Hier können Module registriert werden
        // Wird von den einzelnen Modulen selbst aufgerufen
    }
}

