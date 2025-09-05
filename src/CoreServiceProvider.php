<?php

namespace Platform\Core;

use Illuminate\Support\ServiceProvider;
use Livewire\LivewireServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\Contracts\AuthAccessPolicy;
use Platform\Core\Services\ConfigAuthAccessPolicy;

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

        // Livewire-Komponenten registrieren (mit Präfix "core")
        $this->registerLivewireComponents();

        // Routes registrieren
        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware('web')
            ->group(__DIR__.'/../routes/guest.php');

        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware(['web', 'auth'])
            ->group(__DIR__.'/../routes/web.php');

        // Command registrieren (nur in der Konsole)
        if ($this->app->runningInConsole()) {
            $this->commands([
                TrackBillableUsage::class,
                CreateMonthlyInvoices::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(LivewireServiceProvider::class);
        $this->mergeConfigFrom(__DIR__.'/../config/platform.php', 'platform');

        // Auth Policy Config einbinden und Service binden
        $this->mergeConfigFrom(__DIR__.'/../config/auth-policy.php', 'auth-policy');
        $this->app->singleton(AuthAccessPolicy::class, function () {
            return new ConfigAuthAccessPolicy();
        });
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
}