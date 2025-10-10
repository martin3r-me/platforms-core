<?php

namespace Platform\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\ConfigAuthAccessPolicy;
use Platform\Core\Contracts\CrmCompanyResolverInterface;
use Platform\Core\Contracts\CrmContactResolverInterface;
use Platform\Core\Services\NullCrmCompanyResolver;
use Platform\Core\Services\NullCrmContactResolver;
use Platform\Core\Services\IntelligentAgent;
use Platform\Core\Services\ToolRegistry;
use Platform\Core\Services\ToolExecutor;
// CommandRegistry entfernt

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthAccessPolicy::class, fn() => new ConfigAuthAccessPolicy());
        // Default-Resolver (können von CRM überschrieben werden)
        $this->app->singleton(CrmCompanyResolverInterface::class, fn() => new NullCrmCompanyResolver());
        $this->app->singleton(CrmContactResolverInterface::class, fn() => new NullCrmContactResolver());
        
        // Agent Services registrieren
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(ToolExecutor::class);
        $this->app->singleton(IntelligentAgent::class);
        
        // Registriere Module beim Booten
        $this->app->booted(function () {
            $this->registerModules();
        });
    }

    public function boot(): void
    {
        // Views für Core-Package registrieren
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'core');
        
        // Teams SSO Routes laden
        $this->loadRoutesFrom(__DIR__ . '/../../routes/teams-sso.php');
        
        // Teams SSO Middleware registrieren
        $this->app->make(\Illuminate\Routing\Router::class)->aliasMiddleware(
            'teams.sso',
            \Platform\Core\Middleware\TeamsSsoMiddleware::class
        );
        
        // Livewire-Komponenten registrieren
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::component('core.modal-team', \Platform\Core\Livewire\ModalTeam::class);
            \Livewire\Livewire::component('core.modal-user', \Platform\Core\Livewire\ModalUser::class);
            \Livewire\Livewire::component('core.modal-modules', \Platform\Core\Livewire\ModalModules::class);
            \Livewire\Livewire::component('core.modal-pricing', \Platform\Core\Livewire\ModalPricing::class);
            \Livewire\Livewire::component('core.navbar', \Platform\Core\Livewire\Navbar::class);
            \Livewire\Livewire::component('core.dashboard', \Platform\Core\Livewire\Dashboard::class);
            \Livewire\Livewire::component('core.login', \Platform\Core\Livewire\Login::class);
            \Livewire\Livewire::component('core.register', \Platform\Core\Livewire\Register::class);
        }
    }

    private function registerModules(): void
    {
        // Hier können Module registriert werden
        // Wird von den einzelnen Modulen selbst aufgerufen
    }
}