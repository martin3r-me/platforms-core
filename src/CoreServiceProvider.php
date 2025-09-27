<?php

namespace Platform\Core;

use Illuminate\Support\ServiceProvider;
use Livewire\LivewireServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\ConfigAuthAccessPolicy;
use Platform\Core\Contracts\CrmCompanyResolverInterface;
use Platform\Core\Contracts\CrmContactResolverInterface;
use Platform\Core\Services\NullCrmCompanyResolver;
use Platform\Core\Services\NullCrmContactResolver;
use Platform\Core\Contracts\CrmCompanyOptionsProviderInterface;
use Platform\Core\Services\NullCrmCompanyOptionsProvider;

// Command-Klasse importieren!
use Platform\Core\Commands\TrackBillableUsage;
use Platform\Core\Commands\CreateMonthlyInvoices;

class CoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Views & Migrations
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'platform');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Module ServiceProvider automatisch laden
        $this->loadModuleServiceProviders();

        // Konfigurationen veröffentlichen
        $this->publishes([
            __DIR__.'/../config/agent.php' => config_path('agent.php'),
        ], 'config');

        // Livewire-Komponenten registrieren (mit Präfix "core")
        $this->registerLivewireComponents();

        // Routes registrieren
        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware('web')
            ->group(__DIR__.'/../routes/guest.php');

        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware(['web', 'auth'])
            ->group(__DIR__.'/../routes/web.php');

        // Nach Laden der Module und Routen: alle Modul-GET-Routen als Tools exportieren
        // RouteToolExporter entfernt - Sidebar soll leer sein

        // Command registrieren (nur in der Konsole)
        if ($this->app->runningInConsole()) {
            $this->commands([
                TrackBillableUsage::class,
                CreateMonthlyInvoices::class,
            ]);
        }

        // Automatische Modell-Registrierung (nach Migrations/Boot der Module)
        try {
            (new \Platform\Core\Services\ModelAutoRegistrar())->scanAndRegister();
        } catch (\Throwable $e) {
            // still halten, falls Module/Tabellen noch nicht geladen
        }
    }

    public function register(): void
    {
        $this->app->register(LivewireServiceProvider::class);
        $this->mergeConfigFrom(__DIR__.'/../config/platform.php', 'platform');
        $this->mergeConfigFrom(__DIR__.'/../config/agent.php', 'agent');

        // Auth Policy Config einbinden und Service binden
        $this->mergeConfigFrom(__DIR__.'/../config/auth-policy.php', 'auth-policy');
        $this->app->singleton(AuthAccessPolicy::class, function () {
            return new ConfigAuthAccessPolicy();
        });

        // Default-Resolver binden (können vom CRM überschrieben werden)
        $this->app->singleton(CrmCompanyResolverInterface::class, function () {
            return new NullCrmCompanyResolver();
        });
        $this->app->singleton(CrmContactResolverInterface::class, function () {
            return new NullCrmContactResolver();
        });
        $this->app->singleton(CrmCompanyOptionsProviderInterface::class, function () {
            return new NullCrmCompanyOptionsProvider();
        });

        // CommandRegistry entfernt - Sidebar soll leer sein
    }

    protected function registerLivewireComponents(): void
    {
        $componentPath = __DIR__.'/Livewire';
        $namespace = 'Platform\\Core\\Livewire';
        $prefix = 'core';

        if (!is_dir($componentPath)) {
            return;
        }

        foreach (scandir($componentPath) as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $class = $namespace.'\\'.pathinfo($file, PATHINFO_FILENAME);
            if (class_exists($class)) {
                $alias = $prefix.'.'.Str::kebab(pathinfo($file, PATHINFO_FILENAME));
                Livewire::component($alias, $class);
            }
        }
    }

    protected function loadModuleServiceProviders(): void
    {
        $modulesPath = realpath(__DIR__.'/../../modules');
        if (!$modulesPath || !is_dir($modulesPath)) {
            return;
        }

        $modules = array_filter(glob($modulesPath.'/*'), 'is_dir');
        foreach ($modules as $moduleDir) {
            $moduleKey = basename($moduleDir);
            $serviceProviderClass = 'Platform\\'.Str::studly($moduleKey).'\\'.Str::studly($moduleKey).'ServiceProvider';
            
            if (class_exists($serviceProviderClass)) {
                \Log::info("CoreServiceProvider: Lade {$serviceProviderClass}");
                $this->app->register($serviceProviderClass);
            } else {
                \Log::info("CoreServiceProvider: Klasse {$serviceProviderClass} nicht gefunden");
            }
        }
    }
}