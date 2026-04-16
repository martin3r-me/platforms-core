<?php

namespace Platform\Core\SemanticLayer;

use Illuminate\Support\ServiceProvider;
use Platform\Core\SemanticLayer\Console\Commands\LayerActivateCommand;
use Platform\Core\SemanticLayer\Console\Commands\LayerCreateCommand;
use Platform\Core\SemanticLayer\Console\Commands\LayerEnableModuleCommand;
use Platform\Core\SemanticLayer\Console\Commands\LayerListCommand;
use Platform\Core\SemanticLayer\Console\Commands\LayerShowCommand;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\SemanticLayer\Schema\LayerSchemaValidator;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\SemanticLayer\Services\SemanticLayerScaffold;

class SemanticLayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SemanticLayerScaffold::class);
        $this->app->singleton(LayerSchemaValidator::class);
        $this->app->singleton(SemanticLayerResolver::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LayerCreateCommand::class,
                LayerListCommand::class,
                LayerActivateCommand::class,
                LayerEnableModuleCommand::class,
                LayerShowCommand::class,
            ]);
        }

        // Cache-Invalidierung bei Änderungen am Layer/Version
        $invalidate = function () {
            try {
                app(SemanticLayerResolver::class)->forgetCache();
            } catch (\Throwable $e) {
                // Defensive: Event-Listener darf nicht brechen
            }
        };

        SemanticLayer::saved($invalidate);
        SemanticLayer::deleted($invalidate);
        SemanticLayerVersion::saved($invalidate);
        SemanticLayerVersion::deleted($invalidate);
    }
}
