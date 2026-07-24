<?php

namespace Platform\Core\Authz;

/**
 * Capability-Vokabular + Rank-Ordnung. Spiegelt authz_capability, aber im
 * Speicher — damit der Resolver pro Check keinen DB-Join braucht.
 *
 * Regel: der Resolver fragt IMMER capability-zentrisch ("ist Rank >= gefordert?"),
 * nie rollen-zentrisch. Neue Modul-Verben werden hier additiv ergänzt, ohne
 * den Resolver-Kern zu ändern.
 */
final class Capability
{
    /** Content-Capabilities, geordnet. 'write' erfüllt 'read'. */
    public const RANKS = [
        'read'  => 10,
        'write' => 20,
        'owner' => 30,
    ];

    public static function rank(string $capability): int
    {
        return self::RANKS[$capability] ?? 0;
    }

    /** Alle Capability-Codes, deren Rank die geforderte Stufe erfüllt. */
    public static function satisfying(string $required): array
    {
        $need = self::rank($required);

        return array_keys(array_filter(self::RANKS, fn ($rank) => $rank >= $need));
    }

    /**
     * Mappt eine Gate-Ability auf die geforderte Content-Capability.
     * Gibt null zurück, wenn die Ability nicht content-bezogen ist
     * (dann wird sie im Shadow-Vergleich übersprungen).
     */
    public static function fromAbility(string $ability): ?string
    {
        $a = strtolower($ability);

        foreach (['delete', 'forcedelete', 'restore', 'manage', 'admin', 'owner'] as $needle) {
            if (str_contains($a, $needle)) {
                return 'owner';
            }
        }

        foreach (['create', 'update', 'store', 'edit', 'write', 'publish'] as $needle) {
            if (str_contains($a, $needle)) {
                return 'write';
            }
        }

        foreach (['viewany', 'view', 'read', 'list', 'show', 'index'] as $needle) {
            if (str_contains($a, $needle)) {
                return 'read';
            }
        }

        return null;
    }
}
