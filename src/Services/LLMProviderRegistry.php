<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\LLMProviderContract;
use Illuminate\Support\Facades\Log;

/**
 * Registry für LLM-Provider
 * 
 * Verwaltet verschiedene LLM-Provider und ermöglicht Fallback
 */
class LLMProviderRegistry
{
    /**
     * @var LLMProviderContract[]
     */
    private array $providers = [];

    /**
     * Registriert einen Provider
     */
    public function register(LLMProviderContract $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
        
        Log::info("[LLMProviderRegistry] Provider registriert", [
            'provider' => $provider->getName(),
        ]);
    }

    /**
     * Gibt einen Provider zurück
     */
    public function get(string $name): ?LLMProviderContract
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Gibt alle Provider zurück
     * 
     * @return LLMProviderContract[]
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Gibt verfügbare Provider zurück (nur die, die konfiguriert sind)
     * 
     * @return LLMProviderContract[]
     */
    public function getAvailableProviders(): array
    {
        return array_filter($this->providers, fn($p) => $p->isAvailable());
    }

    /**
     * Gibt Standard-Provider zurück (erster verfügbarer)
     */
    public function getDefaultProvider(): ?LLMProviderContract
    {
        $available = $this->getAvailableProviders();
        return !empty($available) ? reset($available) : null;
    }

    /**
     * Prüft ob Provider registriert ist
     */
    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }
}

