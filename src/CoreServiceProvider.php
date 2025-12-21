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
use Platform\Core\Contracts\CrmContactOptionsProviderInterface;
use Platform\Core\Services\NullCrmContactOptionsProvider;
use Platform\Core\Contracts\CrmCompanyContactsProviderInterface;
use Platform\Core\Services\NullCrmCompanyContactsProvider;
use Platform\Core\Services\IntelligentAgent;
use Platform\Core\Services\ToolRegistry;
use Platform\Core\Services\ToolExecutor;
use Platform\Core\Services\AgentOrchestrator;
use Platform\Core\Contracts\CounterKeyResultSyncer;
use Platform\Core\Services\NullCounterKeyResultSyncer;

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
        
        // Teams SSO Routes laden
        $this->loadRoutesFrom(__DIR__.'/../routes/teams-sso.php');
        
        // Teams SSO Middleware registrieren
        $this->app->make(\Illuminate\Routing\Router::class)->aliasMiddleware(
            'teams.sso',
            \Platform\Core\Middleware\TeamsSsoMiddleware::class
        );

        // Teams SDK Auth Middleware registrieren (ohne Laravel Auth)
        $this->app->make(\Illuminate\Routing\Router::class)->aliasMiddleware(
            'teams.sdk.auth',
            \Platform\Core\Middleware\TeamsSdkAuthMiddleware::class
        );

        // API Authentifizierungs-Middleware registrieren
        $this->app->make(\Illuminate\Routing\Router::class)->aliasMiddleware(
            'api.auth',
            \Platform\Core\Http\Middleware\ApiAuthenticate::class
        );

        // Module ServiceProvider automatisch laden
        $this->loadModuleServiceProviders();

        // Konfigurationen veröffentlichen
        // Agent-Config Publishes entfernt – Agent ausgelagert

        // Livewire-Komponenten registrieren (mit Präfix "core")
        $this->registerLivewireComponents();

        // Routes registrieren
        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware(['web', 'detect.module.guard'])
            ->group(__DIR__.'/../routes/guest.php');

        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware(['web', 'auth'])
            ->group(__DIR__.'/../routes/web.php');

        // API-Routen registrieren
        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware(['api'])
            ->prefix('api')
            ->group(__DIR__.'/../routes/api.php');

        // Keine Agent/Schema Logs mehr

        // Command registrieren (nur in der Konsole)
        if ($this->app->runningInConsole()) {
            $this->commands([
                TrackBillableUsage::class,
                CreateMonthlyInvoices::class,
                \Platform\Core\Console\Commands\SecurityHashKeyCommand::class,
                \Platform\Core\Console\Commands\CreateApiTokenCommand::class,
                \Platform\Core\Console\Commands\CreateEndpointApiTokenCommand::class,
                \Platform\Core\Console\Commands\RefreshMicrosoftTokens::class,
                \Platform\Core\Console\Commands\SyncCounterKeyResultsCommand::class,
            ]);
        }

        // Automatische Modell-Registrierung entfernt
    }

    public function register(): void
    {
        $this->app->register(LivewireServiceProvider::class);
        $this->mergeConfigFrom(__DIR__.'/../config/platform.php', 'platform');
        $this->mergeConfigFrom(__DIR__.'/../config/security.php', 'security');
        $this->mergeConfigFrom(__DIR__.'/../config/checkins.php', 'checkins');
        // Agent-Config entfernt – Agent ausgelagert

        // Counter→KeyResult Sync (Default: No-Op; OKR-Modul kann überschreiben)
        $this->app->singleton(CounterKeyResultSyncer::class, function () {
            return new NullCounterKeyResultSyncer();
        });

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
        $this->app->singleton(CrmContactOptionsProviderInterface::class, function () {
            return new NullCrmContactOptionsProvider();
        });
        $this->app->singleton(CrmCompanyContactsProviderInterface::class, function () {
            return new NullCrmCompanyContactsProvider();
        });

        // AI Agent Services entfernt – kommen in separates Modul

        // CommandRegistry entfernt - Sidebar soll leer sein

        // Bootstrap DataRead Provider Registry
        \Platform\Core\Tools\DataRead\Bootstrap::register();

        // Load manifest-backed providers if available
        $this->app->afterResolving(\Platform\Core\Tools\DataRead\ProviderRegistry::class, function ($registry) {
            try {
                (new \Platform\Core\Tools\DataRead\ManifestLoader())->loadFromStorage($registry);
            } catch (\Throwable $e) {
                \Log::warning('CoreServiceProvider: Manifest loading skipped: '.$e->getMessage());
            }
        });

        // Tool Registry & Executor als Singleton registrieren
        $this->app->singleton(\Platform\Core\Tools\ToolRegistry::class);
        $this->app->singleton(\Platform\Core\Tools\ToolExecutor::class);

        // Tools registrieren (nach dem Boot, damit alle Dependencies verfügbar sind)
        $this->app->booted(function () {
            $registry = $this->app->make(\Platform\Core\Tools\ToolRegistry::class);
            
            // Bestehende Tools registrieren
            $registry->register($this->app->make(\Platform\Core\Tools\DataReadTool::class));
            $registry->register($this->app->make(\Platform\Core\Tools\DataWriteTool::class));
            
            // Test-Tool registrieren (für Entwicklung/Testing)
            $registry->register($this->app->make(\Platform\Core\Tools\EchoTool::class));
        });
    }


    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Core\\Livewire';
        $prefix = 'core';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            // core.dashboard aus core + dashboard.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            // Debug: Ausgabe der registrierten Komponente
            \Log::info("Registering Livewire component: {$alias} -> {$class}");

            Livewire::component($alias, $class);
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