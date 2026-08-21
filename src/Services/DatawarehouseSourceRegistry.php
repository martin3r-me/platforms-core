<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\DatawarehouseSourceProviderInterface;

/**
 * Zentrale Registry für DWH-Stream-Quellen. Jedes Modul registriert seine
 * Quelle(n) im Boot (resolve(DatawarehouseSourceRegistry::class)->register(...)),
 * analog zur KeyResultMetricRegistry. platform-datawarehouse liest hier den
 * Katalog und löst source_key → Provider auf.
 */
class DatawarehouseSourceRegistry
{
    /** @var array<string, DatawarehouseSourceProviderInterface> source_key => provider */
    protected array $providers = [];

    public function register(DatawarehouseSourceProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /** @return array<string, DatawarehouseSourceProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    public function get(string $key): ?DatawarehouseSourceProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }
}
