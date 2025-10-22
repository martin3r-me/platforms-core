<?php

namespace Platform\Core\Tools\DataRead;

use Platform\Core\Tools\DataRead\Providers\PlannerTaskProvider;
use Platform\Core\Tools\DataRead\Providers\OkrKeyResultProvider;

class Bootstrap
{
    public static function register(): void
    {
        app()->singleton(ProviderRegistry::class, function () {
            $registry = new ProviderRegistry();
            // Register core providers
            $registry->register(new PlannerTaskProvider());
            // Optional: register OKR provider if model exists
            if (class_exists('Platform\\Okr\\Models\\OkrKeyResult')) {
                $registry->register(new OkrKeyResultProvider());
            }
            return $registry;
        });
    }
}
