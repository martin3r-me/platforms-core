<?php

namespace Platform\Core\Support;

use Platform\Core\Contracts\CatalogCombinationProvider;

/**
 * Singleton-Registry: Katalog-Module registrieren ihre Vermengungsgruppen-Provider. Konsumenten
 * (z.B. der Termin) fragen die Gruppe einer Katalog-Referenz ab und prüfen auf Konflikte —
 * ohne die Katalog-Module hart zu kennen (lose Kopplung über den Contract).
 *
 * Regel: In einem Termin/einer Bescheinigung ist höchstens EINE nicht-leere Gruppe erlaubt.
 */
class CatalogCombinationRegistry
{
    /** @var CatalogCombinationProvider[] */
    protected array $providers = [];

    public function register(CatalogCombinationProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /** Gruppe einer einzelnen Referenz (oder null). */
    public function combinationGroup(string $catalogType, int $catalogId): ?string
    {
        foreach ($this->providers as $provider) {
            if (in_array($catalogType, $provider->supportedTypes(), true)) {
                return $provider->combinationGroup($catalogType, $catalogId);
            }
        }
        return null;
    }

    /**
     * Distinkte, nicht-leere Gruppen einer Referenzliste.
     *
     * @param array<array{type:?string,id:mixed}> $refs
     * @return string[]
     */
    public function groupsFor(array $refs): array
    {
        $groups = [];
        foreach ($refs as $ref) {
            $g = $this->combinationGroup((string) ($ref['type'] ?? ''), (int) ($ref['id'] ?? 0));
            if ($g !== null && $g !== '') {
                $groups[$g] = true;
            }
        }
        return array_keys($groups);
    }

    /** Konflikt = mehr als eine nicht-leere Gruppe in der Referenzliste. */
    public function hasConflict(array $refs): bool
    {
        return count($this->groupsFor($refs)) > 1;
    }
}
