<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\KeyResultMetricProvider;

/**
 * Zentrale Registry für KR-Metrik-Provider. Jedes Modul registriert seinen
 * Provider im Boot (resolve(KeyResultMetricRegistry::class)->register(...)),
 * analog zur EntityLinkRegistry. Die OKR-Engine liest hier den Katalog und
 * löst metric_key → Provider auf.
 */
class KeyResultMetricRegistry
{
    /** @var KeyResultMetricProvider[] */
    protected array $providers = [];

    /** @var array<string, KeyResultMetricProvider> metric_key => provider */
    protected array $byMetricKey = [];

    protected ?array $cachedCatalog = null;

    public function register(KeyResultMetricProvider $provider): void
    {
        $this->providers[] = $provider;

        foreach ($provider->metricDefinitions() as $def) {
            $key = $def['metric_key'] ?? null;
            if ($key !== null) {
                $this->byMetricKey[$key] = $provider;
            }
        }

        $this->cachedCatalog = null;
    }

    public function providerFor(string $metricKey): ?KeyResultMetricProvider
    {
        return $this->byMetricKey[$metricKey] ?? null;
    }

    /**
     * Gemergter Katalog aller Provider (für Discovery).
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        if ($this->cachedCatalog === null) {
            $this->cachedCatalog = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->metricDefinitions() as $def) {
                    $this->cachedCatalog[] = $def;
                }
            }
        }

        return $this->cachedCatalog;
    }

    public function definition(string $metricKey): ?array
    {
        foreach ($this->catalog() as $def) {
            if (($def['metric_key'] ?? null) === $metricKey) {
                return $def;
            }
        }

        return null;
    }

    public function hasMetric(string $metricKey): bool
    {
        return isset($this->byMetricKey[$metricKey]);
    }
}
