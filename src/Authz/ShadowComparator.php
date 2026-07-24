<?php

namespace Platform\Core\Authz;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;

/**
 * Beobachtet jede Gate-Entscheidung (via Gate::after), berechnet die
 * Graph-Entscheidung und protokolliert NUR Abweichungen in authz_shadow_log.
 *
 * Ändert niemals eine Entscheidung. Ziel: authz_shadow_log wird zur Punch-List
 * — jede Zeile ist ein Fall, den der Graph heute noch nicht so entscheidet wie
 * die bestehende Policy. 0 Zeilen (auf einer Dimension) = Äquivalenz bewiesen.
 */
class ShadowComparator
{
    public function __construct(protected AuthzResolver $resolver)
    {
    }

    public function record(mixed $user, string $ability, mixed $result, array $arguments): void
    {
        if (! $user instanceof User) {
            return;
        }

        // Nur content-bezogene Abilities vergleichen.
        $capability = Capability::fromAbility($ability);
        if ($capability === null) {
            return;
        }

        // Nur echte Erlaubt/Verboten-Ergebnisse (kein "abstain"/null) vergleichen.
        if ($result === null) {
            return;
        }
        $legacy = (bool) $result;

        [$resourceType, $resourceId] = $this->extractResource($arguments);

        $graph = $this->resolver->may($user, $capability, $resourceType, $resourceId);

        if ($graph === $legacy) {
            return; // Übereinstimmung → keine Zeile
        }

        DB::table('authz_shadow_log')->insert([
            'user_id'       => $user->id,
            'ability'       => $ability,
            'capability'    => $capability,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'legacy_result' => $legacy,
            'graph_result'  => $graph,
            'team_id'       => optional($user->currentTeam)->id,
            'created_at'    => now(),
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    protected function extractResource(array $arguments): array
    {
        $first = $arguments[0] ?? null;

        if ($first instanceof Model) {
            return [$first::class, $first->getKey() !== null ? (int) $first->getKey() : null];
        }

        // Klassenname (z. B. bei viewAny/create): Typ ohne konkrete ID.
        if (is_string($first) && class_exists($first)) {
            return [$first, null];
        }

        return [null, null];
    }
}
