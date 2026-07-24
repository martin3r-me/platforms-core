<?php

namespace Platform\Core\Authz;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\AuthzGrant;
use Platform\Core\Models\User;

/**
 * Der zentrale Resolver — zwei Fragen, eine Engine:
 *
 *   mayUseModule()  → Modul-/Tool-Oberfläche (scope_type = 'module', pauschal an der Person)
 *   may()           → Content-Sichtbarkeit   (scope_type = 'team'|'entity', fein, via Closure)
 *
 * Aktueller Ausbaustand: der Team-Scope (Bootstrap) ist voll aktiv. Der
 * Entity-Scope ist bereits verdrahtet (Closure + resource_link), wird aber
 * erst wirksam, sobald organization den Baum materialisiert (Schritt 3).
 */
class AuthzResolver
{
    public function mode(): string
    {
        return (string) config('authz.mode', 'off');
    }

    /**
     * Darf der User dieses Modul / Tool überhaupt nutzen? (pauschal)
     */
    public function mayUseModule(User $user, string $module): bool
    {
        $teamId = $this->teamId($user);
        if ($teamId === null) {
            return false;
        }

        return $this->baseGrantQuery($user, $teamId)
            ->where('scope_type', 'module')
            ->where(fn ($q) => $q->where('scope_key', $module)->orWhere('scope_key', '*'))
            ->exists();
    }

    /**
     * Darf der User die geforderte Capability auf der Ressource ausüben?
     *
     * @param  string       $capability   read | write | owner
     * @param  string|null  $resourceType Klassenname/Alias des Objekts (null = kontextfrei)
     */
    public function may(User $user, string $capability, ?string $resourceType = null, ?int $resourceId = null): bool
    {
        $teamId = $this->teamId($user);
        if ($teamId === null) {
            return false;
        }

        $allowedCaps = Capability::satisfying($capability);
        if ($allowedCaps === []) {
            return false;
        }

        return $this->baseGrantQuery($user, $teamId)
            ->whereIn('scope_type', ['team', 'entity'])
            ->whereIn('capability', $allowedCaps)
            ->where(function ($q) use ($teamId, $resourceType, $resourceId) {
                // (a) Team-Wurzel-Grant erreicht per Definition allen Content.
                $q->where(fn ($q) => $q->where('scope_type', 'team')->where('scope_id', $teamId));

                // (b) Entity-Grant erreicht die Ressource, wenn sein Scope ein
                //     Vorfahre der Entity ist, an der die Ressource hängt.
                if ($resourceType !== null && $resourceId !== null) {
                    $q->orWhere(function ($q) use ($teamId, $resourceType, $resourceId) {
                        $q->where('scope_type', 'entity')
                            ->whereIn('scope_id', function (Builder $sub) use ($teamId, $resourceType, $resourceId) {
                                $sub->select('c.ancestor_id')
                                    ->from('authz_scope_closure as c')
                                    ->join('authz_resource_link as l', 'l.scope_id', '=', 'c.descendant_id')
                                    ->where('l.resource_type', $resourceType)
                                    ->where('l.resource_id', $resourceId)
                                    ->where('c.team_id', $teamId);
                            });
                    });
                }
            })
            ->exists();
    }

    /**
     * Basis-Query: gültige Grants dieses Subjekts (User + ggf. Person-Entity)
     * im aktuellen Team.
     */
    protected function baseGrantQuery(User $user, int $teamId): \Illuminate\Database\Eloquent\Builder
    {
        $now = now();

        return AuthzGrant::query()
            ->where('team_id', $teamId)
            ->where(function ($q) use ($user) {
                // Subjekt ist der User selbst — und (später) sein Person-Entity.
                $q->where(fn ($q) => $q->where('subject_type', 'user')->where('subject_id', $user->id));

                $personEntityId = $this->personEntityId($user);
                if ($personEntityId !== null) {
                    $q->orWhere(fn ($q) => $q->where('subject_type', 'entity')->where('subject_id', $personEntityId));
                }
            })
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $now));
    }

    protected function teamId(User $user): ?int
    {
        return optional($user->currentTeam)->id;
    }

    /**
     * User → Person-Entity (organization). Bewusst weich: existiert die
     * organization-Tabelle nicht (Kernel ohne organization), gibt es kein
     * Person-Entity und der Resolver arbeitet rein auf User-Grants.
     */
    protected function personEntityId(User $user): ?int
    {
        static $available = null;
        if ($available === null) {
            $available = DB::getSchemaBuilder()->hasTable('organization_entities');
        }
        if (! $available) {
            return null;
        }

        return DB::table('organization_entities')
            ->where('linked_user_id', $user->id)
            ->value('id');
    }
}
