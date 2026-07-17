<?php

namespace Platform\Core\Contracts;

/**
 * Löst ein "Ziel" (z.B. ein User, ein Team, ein Org-Knoten) in konkrete User-IDs auf.
 *
 * Dient als Entkopplungs-Naht: Module wie Academy delegieren die Frage
 * "welche Personen stecken hinter diesem Ziel?" an registrierte Resolver,
 * ohne die Ziel-Quelle (z.B. das Organisation-Modul) direkt zu kennen.
 */
interface AudienceResolverInterface
{
    /** Ziel-Typ, den dieser Resolver bedient, z.B. 'user', 'team', 'org_entity'. */
    public function type(): string;

    /**
     * Löst ein Ziel in konkrete User-IDs auf.
     *
     * @param  array<string,mixed>  $options  Ziel-spezifische Optionen (z.B. include_subteams)
     * @return array<int,int>  User-IDs
     */
    public function resolve(int $targetId, array $options = [], ?int $teamId = null): array;

    /** Menschenlesbares Label des Ziels (für UI/Reports). */
    public function label(int $targetId, ?int $teamId = null): ?string;
}
