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
use Platform\Core\Services\AgentOrchestrator;
use Platform\Core\Contracts\CounterKeyResultSyncer;
use Platform\Core\Services\NullCounterKeyResultSyncer;
use Platform\Core\Registry\ModuleRegistry;

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

        // Force JSON Response Middleware registrieren (für Playground)
        $this->app->make(\Illuminate\Routing\Router::class)->aliasMiddleware(
            'force.json',
            \Platform\Core\Http\Middleware\ForceJsonResponse::class
        );

        // Module ServiceProvider automatisch laden
        $this->loadModuleServiceProviders();

        // Konfigurationen veröffentlichen
        // Agent-Config Publishes entfernt – Agent ausgelagert

        // Event-Listener für Tools registrieren
        $this->registerToolEventListeners();

        // Livewire-Komponenten registrieren (mit Präfix "core")
        $this->registerLivewireComponents();

        // Routes registrieren
        // Comms Webhooks (no auth, no module guard)
        Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            // Important: webhooks must not run through CSRF (419). We authenticate via BasicAuth/HMAC instead.
            ->middleware(['api'])
            ->group(__DIR__.'/../routes/comms-webhooks.php');

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
                \Platform\Core\Console\Commands\EncryptCheckinTodos::class,
                \Platform\Core\Console\Commands\DebugToolsCommand::class,
                \Platform\Core\Console\Commands\TestOpenAiCommand::class,
                \Platform\Core\Console\Commands\SyncAiModelsCommand::class,
                \Platform\Core\Console\Commands\ListToolsCommand::class,
                \Platform\Core\Console\Commands\TestToolOrchestrationCommand::class,
                \Platform\Core\Console\Commands\MakeToolCommand::class,
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

        // Tool Registry, Executor & Orchestrator als Singleton registrieren
        $this->app->singleton(\Platform\Core\Tools\ToolRegistry::class);
        $this->app->singleton(\Platform\Core\Tools\ToolExecutor::class);
        
        // Versionierung & Audit Services
        $this->app->singleton(\Platform\Core\Services\ModelVersioningService::class);
        $this->app->singleton(\Platform\Core\Services\UndoService::class);
        $this->app->singleton(\Platform\Core\Services\AuditTrailService::class);
        $this->app->singleton(\Platform\Core\Services\ActionSummaryService::class);
        $this->app->singleton(\Platform\Core\Services\ToolExecutionContextService::class);
        $this->app->singleton(\Platform\Core\Services\IntentionVerificationService::class);
        
        $this->app->singleton(\Platform\Core\Tools\ToolOrchestrator::class, function ($app) {
            return new \Platform\Core\Tools\ToolOrchestrator(
                $app->make(\Platform\Core\Tools\ToolExecutor::class),
                $app->make(\Platform\Core\Tools\ToolRegistry::class)
            );
        });

        // Tool Auto-Discovery & Registrierung (lazy - erst wenn Registry tatsächlich verwendet wird)
        // INNOVATIV: Auto-Discovery + manuelle Registrierung - Module entscheiden selbst
        $this->app->afterResolving(\Platform\Core\Tools\ToolRegistry::class, function ($registry) {
            // Prüfe ob wir beim package:discover sind (keine vollständige App)
            if ($this->app->runningInConsole() && !$this->app->runningUnitTests()) {
                $command = $_SERVER['argv'][1] ?? '';
                if ($command === 'package:discover' || str_contains($command, 'package:discover')) {
                    return; // Skip beim package:discover
                }
            }
            
            // Wenn ToolRegistry sehr früh resolved wurde (z.B. während register()),
            // ist die App hier ggf. noch nicht "booted" – dann müssen wir die
            // eigentliche Tool-Registrierung nach boot verschieben.
            if (!$this->app->isBooted()) {
                $this->app->booted(function () use ($registry) {
                    try {
                        // Core/Discovery Tools nachziehen (müssen immer verfügbar sein)
                        $this->ensureDiscoveryToolsRegistered($registry);
                    } catch (\Throwable $e) {
                        // Silent fail
                    }
                });
                return;
            }
            
            // Wenn bereits Modul-Tools registriert wurden, dürfen wir NICHT sofort returnen,
            // sonst fehlen die Core/Discovery-Tools (tools.GET, core.teams.GET, ...).
            // Wir skippen nur, wenn die Discovery-Tools bereits vorhanden sind.
            // WICHTIG: Core-Tools werden trotzdem idempotent registriert, damit Core-Write-Tools
            // (z.B. core.teams.POST) über tools.GET discoverbar bleiben.
            $hasDiscoveryTools =
                $registry->has('tools.GET') &&
                $registry->has('tools.request') &&
                $registry->has('core.modules.GET') &&
                $registry->has('core.context.GET') &&
                $registry->has('core.user.GET') &&
                $registry->has('core.teams.GET');
            
            // 1. Auto-Discovery: Lade Tools automatisch aus Core (und Module nur, wenn Registry leer ist)
            try {
                // 1.1: Core-Tools laden (LOOSE COUPLED - automatisch aus Verzeichnis)
                // WICHTIG: immer laden (idempotent), auch wenn Discovery-Tools bereits vorhanden sind.
                $coreTools = \Platform\Core\Tools\ToolLoader::loadCoreTools();
                foreach ($coreTools as $tool) {
                    try {
                        if (!$registry->has($tool->getName())) {
                            $registry->register($tool);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning("[ToolRegistry] Core-Tool konnte nicht registriert werden", [
                            'tool' => get_class($tool),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // 1.2: Module-Tools laden
                if (count($registry->all()) === 0) {
                    $modulesPath = realpath(__DIR__ . '/../../modules');
                    if ($modulesPath && is_dir($modulesPath)) {
                        $moduleTools = \Platform\Core\Tools\ToolLoader::loadFromAllModules($modulesPath);
                        foreach ($moduleTools as $tool) {
                            try {
                                if (!$registry->has($tool->getName())) {
                                    $registry->register($tool);
                                }
                            } catch (\Throwable $e) {
                                \Log::warning("[ToolRegistry] Auto-Discovery Tool konnte nicht registriert werden", [
                                    'tool' => get_class($tool),
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning("[ToolRegistry] Auto-Discovery fehlgeschlagen", ['error' => $e->getMessage()]);
            }
            
            // 2. Fallback: Discovery-Tools sicherstellen (auch wenn Module bereits Tools registriert haben)
            // (Sollte normalerweise nicht nötig sein, da Auto-Discovery Core-Tools lädt)
            // WICHTIG: Diese Tools haben möglicherweise Dependencies, daher Fallback
            $this->ensureDiscoveryToolsRegistered($registry);
            
            // Context-Tools (werden normalerweise via Auto-Discovery geladen)
            // GetContextTool, GetUserTool, GetModulesTool werden automatisch geladen
            
            // DataReadTool und DataWriteTool nur wenn Dependencies verfügbar sind
            if (!$registry->has('data.read')) {
                try {
                    $registry->register($this->app->make(\Platform\Core\Tools\DataReadTool::class));
                } catch (\Throwable $e) {
                    // Silent fail - Dependencies möglicherweise nicht verfügbar
                }
            }
            
            if (!$registry->has('data.write')) {
                try {
                    $registry->register($this->app->make(\Platform\Core\Tools\DataWriteTool::class));
                } catch (\Throwable $e) {
                    // Silent fail - Dependencies möglicherweise nicht verfügbar
                }
            }
        });
    }

    /**
     * Stellt sicher, dass die Core/Discovery-Tools immer registriert sind.
     * Diese Tools sind die Grundlage für "loose" Tool-Discovery (tools.GET → nachladen).
     */
    private function ensureDiscoveryToolsRegistered(\Platform\Core\Tools\ToolRegistry $registry): void
    {
        // tools.GET (ListToolsTool)
        if (!$registry->has('tools.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\ListToolsTool::class)); } catch (\Throwable $e) {}
        }

        // tools.request
        if (!$registry->has('tools.request')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\RequestToolTool::class)); } catch (\Throwable $e) {}
        }

        // core context/user/modules/teams
        if (!$registry->has('core.context.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\GetContextTool::class)); } catch (\Throwable $e) {}
        }
        if (!$registry->has('core.user.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\GetUserTool::class)); } catch (\Throwable $e) {}
        }
        if (!$registry->has('core.modules.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\GetModulesTool::class)); } catch (\Throwable $e) {}
        }
        if (!$registry->has('core.teams.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\ListTeamsTool::class)); } catch (\Throwable $e) {}
        }

        // Core: Team erstellen (wird von LLM häufig gebraucht, darf nicht "unsichtbar" sein)
        if (class_exists(\Platform\Core\Tools\CreateTeamTool::class) && !$registry->has('core.teams.POST')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\CreateTeamTool::class)); } catch (\Throwable $e) {}
        }

        // Optional (nice-to-have): team members
        if (class_exists(\Platform\Core\Tools\ListTeamUsersTool::class) && !$registry->has('core.teams.users.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\ListTeamUsersTool::class)); } catch (\Throwable $e) {}
        }

        // Team membership management
        if (class_exists(\Platform\Core\Tools\AddTeamUserTool::class) && !$registry->has('core.teams.users.POST')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\AddTeamUserTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\RemoveTeamUserTool::class) && !$registry->has('core.teams.users.DELETE')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\RemoveTeamUserTool::class)); } catch (\Throwable $e) {}
        }

        // Core AI Models (DB Source of Truth): tools for listing/updating models must be discoverable via module=core
        if (class_exists(\Platform\Core\Tools\ListAiModelsTool::class) && !$registry->has('core.ai_models.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\ListAiModelsTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\UpdateAiModelTool::class) && !$registry->has('core.ai_models.PUT')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\UpdateAiModelTool::class)); } catch (\Throwable $e) {}
        }

        // Communication Tools (core.comms.* + communication.overview.GET)
        // Diese Tools müssen immer registriert sein, damit sie via tools.GET(module="communication") gefunden werden
        if (class_exists(\Platform\Core\Tools\Communication\CommunicationOverviewTool::class) && !$registry->has('communication.overview.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Communication\CommunicationOverviewTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\CommsOverviewTool::class) && !$registry->has('core.comms.overview.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\CommsOverviewTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\ListChannelsTool::class) && !$registry->has('core.comms.channels.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\ListChannelsTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\CreateChannelTool::class) && !$registry->has('core.comms.channels.POST')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\CreateChannelTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\UpdateChannelTool::class) && !$registry->has('core.comms.channels.PUT')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\UpdateChannelTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\DeleteChannelTool::class) && !$registry->has('core.comms.channels.DELETE')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\DeleteChannelTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\ListEmailThreadsTool::class) && !$registry->has('core.comms.email_threads.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\ListEmailThreadsTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\CreateEmailThreadTool::class) && !$registry->has('core.comms.email_threads.POST')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\CreateEmailThreadTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\UpdateEmailThreadTool::class) && !$registry->has('core.comms.email_threads.PUT')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\UpdateEmailThreadTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\DeleteEmailThreadTool::class) && !$registry->has('core.comms.email_threads.DELETE')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\DeleteEmailThreadTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\ListEmailMessagesTool::class) && !$registry->has('core.comms.email_messages.GET')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\ListEmailMessagesTool::class)); } catch (\Throwable $e) {}
        }
        if (class_exists(\Platform\Core\Tools\Comms\SendEmailMessageTool::class) && !$registry->has('core.comms.email_messages.POST')) {
            try { $registry->register($this->app->make(\Platform\Core\Tools\Comms\SendEmailMessageTool::class)); } catch (\Throwable $e) {}
        }
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

            // Debug: Ausgabe der registrierten Komponente (nur bei DEBUG-Level)
            // \Log::debug("Registering Livewire component: {$alias} -> {$class}");

            try {
            Livewire::component($alias, $class);
            } catch (\Throwable $e) {
                // Fehler beim Registrieren der Komponente loggen, aber nicht abbrechen
                \Log::warning("Failed to register Livewire component {$alias}: " . $e->getMessage());
            }
        }
    }

    protected function registerToolEventListeners(): void
    {
        // Event-Listener für Tool-Events registrieren
        \Illuminate\Support\Facades\Event::listen(
            \Platform\Core\Events\ToolExecuted::class,
            \Platform\Core\Listeners\LogToolExecution::class . '@handleToolExecuted'
        );

        \Illuminate\Support\Facades\Event::listen(
            \Platform\Core\Events\ToolFailed::class,
            \Platform\Core\Listeners\LogToolExecution::class . '@handleToolFailed'
        );

        // TrackToolMetrics Listener (wird später implementiert, wenn ToolMetricsService existiert)
        // Wird in TrackToolMetrics Listener selbst geprüft, ob Service verfügbar ist
        \Illuminate\Support\Facades\Event::listen(
            \Platform\Core\Events\ToolExecuted::class,
            \Platform\Core\Listeners\TrackToolMetrics::class . '@handleToolExecuted'
        );

        \Illuminate\Support\Facades\Event::listen(
            \Platform\Core\Events\ToolFailed::class,
            \Platform\Core\Listeners\TrackToolMetrics::class . '@handleToolFailed'
        );

        // Model-Versionierung: Automatische Versionierung während Tool-Ausführungen
        // Nutze Eloquent Events (created, updated, deleted) für alle Models
        \Illuminate\Support\Facades\Event::listen(
            'eloquent.created: *',
            \Platform\Core\Listeners\ModelVersioningListener::class . '@handleCreated'
        );
        \Illuminate\Support\Facades\Event::listen(
            'eloquent.updated: *',
            \Platform\Core\Listeners\ModelVersioningListener::class . '@handleUpdated'
        );
        \Illuminate\Support\Facades\Event::listen(
            'eloquent.deleted: *',
            \Platform\Core\Listeners\ModelVersioningListener::class . '@handleDeleted'
        );
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
                // \Log::debug("CoreServiceProvider: Lade {$serviceProviderClass}");
                $this->app->register($serviceProviderClass);
            } else {
                // \Log::debug("CoreServiceProvider: Klasse {$serviceProviderClass} nicht gefunden");
            }
        }
    }
}