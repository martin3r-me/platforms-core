<?php

namespace Platform\Core\Tools\DataRead;

class Bootstrap
{
    public static function register(): void
    {
        app()->singleton(ProviderRegistry::class, function () {
            // Empty registry; providers are loaded from manifests at boot via ManifestLoader
            return new ProviderRegistry();
        });
    }
}
