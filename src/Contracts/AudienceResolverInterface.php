<?php

namespace Platform\Core\Contracts;

/**
 * Löst ein "Ziel" (z.B. ein User, ein Team, ein Org-Knoten) in konkrete User-IDs auf
 * und liefert die Auswahlmöglichkeiten für einen Ziel-Picker.
 *
 * Entkopplungs-Naht: Konsumenten (z.B. Academy) delegieren "welche Personen stecken
 * hinter diesem Ziel?" und "welche Ziele gibt es?" an registrierte Resolver, ohne die
 * Ziel-Quelle (z.B. das Organisation-Modul) direkt zu kennen.
 */
interface AudienceResolverInterface
{
    /** Ziel-Typ, den dieser Resolver bedient, z.B. 'user', 'team', 'org_entity'. */
    public function type(): string;

    /** Menschenlesbares Label des Ziel-Typs für den Picker, z.B. "Ganzes Team". */
    public function typeLabel(): string;

    /**
     * Löst ein Ziel in konkrete User-IDs auf.
     *
     * @param  array<string,mixed>  $options
     * @return array<int,int>  User-IDs
     */
    public function resolve(int $targetId, array $options = [], ?int $teamId = null): array;

    /** Menschenlesbares Label eines konkreten Ziels (für UI/Reports). */
    public function label(int $targetId, ?int $teamId = null): ?string;

    /**
     * Auswählbare Ziele dieses Typs (für den Picker), team-bezogen.
     *
     * @return array<int,array{id:int,label:string}>
     */
    public function options(?int $teamId = null): array;
}
