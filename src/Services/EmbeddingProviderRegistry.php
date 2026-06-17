<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\EmbeddingProviderContract;

/**
 * Registry für Embedding-Provider — 1:1 Spiegel von LLMProviderRegistry.
 */
class EmbeddingProviderRegistry
{
    /**
     * @var array<string, EmbeddingProviderContract>
     */
    private array $providers = [];

    public function register(EmbeddingProviderContract $provider): void
    {
        $this->providers[$provider->getName()] = $provider;

        Log::info('[EmbeddingProviderRegistry] Provider registriert', [
            'provider' => $provider->getName(),
            'model' => $provider->getModel(),
            'dimensions' => $provider->getDimensions(),
        ]);
    }

    public function get(string $name): ?EmbeddingProviderContract
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return array<string, EmbeddingProviderContract>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return array<string, EmbeddingProviderContract>
     */
    public function getAvailableProviders(): array
    {
        return array_filter($this->providers, fn($p) => $p->isAvailable());
    }

    public function getDefaultProvider(): ?EmbeddingProviderContract
    {
        $configured = config('embeddings.default_provider');
        if (is_string($configured) && isset($this->providers[$configured])) {
            $provider = $this->providers[$configured];
            if ($provider->isAvailable()) {
                return $provider;
            }
        }

        $available = $this->getAvailableProviders();
        return !empty($available) ? reset($available) : null;
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }
}
