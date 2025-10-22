<?php

namespace Platform\Core\Tools\DataRead;

class ProviderRegistry
{
    /** @var array<string, EntityReadProvider> */
    private array $providers = [];

    public function register(EntityReadProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /** @return array<string, EntityReadProvider> */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $key): ?EntityReadProvider
    {
        return $this->providers[$key] ?? null;
    }
}
