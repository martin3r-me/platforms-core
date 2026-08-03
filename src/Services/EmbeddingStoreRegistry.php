<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\EmbeddingStoreContract;
use RuntimeException;

/**
 * Registry benannter Embedding-Stores — 1:1 Spiegel von EmbeddingProviderRegistry.
 *
 * Erlaubt, pro Anwendungsfall zwischen Backends zu wählen (z.B. 'qdrant' für den
 * großen Rezept-/Pairing-Korpus, 'mysql' für kleine team-scoped Datensätze), statt
 * pauschal ein Backend zu binden.
 *
 * Auflösungsreihenfolge (resolve()):
 *   1. expliziter Store-Name (Call-Argument)          — höchste Priorität
 *   2. config('embeddings.routing.{entityType}')      — deklaratives Routing pro Entity-Type
 *   3. config('embeddings.store')                      — globaler Default
 */
class EmbeddingStoreRegistry
{
    /** @var array<string, EmbeddingStoreContract> */
    private array $stores = [];

    public function register(string $name, EmbeddingStoreContract $store): void
    {
        $this->stores[$name] = $store;
    }

    public function get(string $name): ?EmbeddingStoreContract
    {
        return $this->stores[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->stores[$name]);
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->stores);
    }

    public function default(): EmbeddingStoreContract
    {
        return $this->getOrFail((string) config('embeddings.store', 'mysql'));
    }

    /**
     * Wählt den Store für einen konkreten Aufruf.
     *
     * @param string|null $store      Expliziter Store-Name (höchste Priorität).
     * @param string|null $entityType Für deklaratives Routing via config('embeddings.routing').
     */
    public function resolve(?string $store, ?string $entityType = null): EmbeddingStoreContract
    {
        if ($store !== null && $store !== '') {
            return $this->getOrFail($store);
        }

        if ($entityType !== null && $entityType !== '') {
            $routed = config("embeddings.routing.{$entityType}");
            if (is_string($routed) && $routed !== '') {
                return $this->getOrFail($routed);
            }
        }

        return $this->default();
    }

    private function getOrFail(string $name): EmbeddingStoreContract
    {
        $store = $this->stores[$name] ?? null;
        if ($store === null) {
            throw new RuntimeException(
                "Embedding store '{$name}' is not registered. Available: "
                . (implode(', ', $this->names()) ?: '(none)')
            );
        }

        return $store;
    }
}
