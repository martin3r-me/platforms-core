<?php

namespace Platform\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\ConfigAuthAccessPolicy;
use Platform\Core\Contracts\CrmCompanyResolverInterface;
use Platform\Core\Contracts\CrmContactResolverInterface;
use Platform\Core\Services\NullCrmCompanyResolver;
use Platform\Core\Services\NullCrmContactResolver;
use Platform\Core\Registry\CommandRegistry;

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
        // Core-Commands registrieren (z. B. Agent-Handoff)
        CommandRegistry::register('core', [
            [
                'key' => 'core.transfer_agent',
                'description' => 'Wechselt zum Ziel-Agent/Modul (Handoff).',
                'parameters' => [
                    ['name' => 'destination', 'type' => 'string', 'required' => true, 'description' => 'Modulschlüssel, z. B. planner, okr, hcm'],
                ],
                'impact' => 'low',
                'phrases' => [ 'wechsle zu {destination}', 'öffne {destination}', 'agent {destination}' ],
                'slots' => [ ['name' => 'destination'] ],
                'guard' => 'web',
                'handler' => ['service', \Platform\Core\Services\CoreAgentService::class.'@transferAgent'],
            ],
        ]);
    }

    private function registerModules(): void
    {
        // Hier können Module registriert werden
        // Wird von den einzelnen Modulen selbst aufgerufen
    }
}

