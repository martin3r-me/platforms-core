<?php

namespace Platform\Core\Authz;

use Illuminate\Database\Eloquent\Builder;
use Platform\Core\Models\User;

/**
 * Listen-Sichtbarkeit „nichts pauschal" als Query-Filter (nicht Einzel-Checks):
 *
 *   WHERE ersteller_spalte = user   -- eigenes Objekt (Residual)
 *      OR id IN (authz_resource_link für dieses Model an erreichbaren Entities)
 *
 * Registriert als Eloquent-Builder-Macro `visibleTo($user, $cap)` — jedes Modul
 * legt es mit EINER Zeile auf seine Listen. Filtert genau das, was der per-Objekt
 * `may()||owns()` auch entscheiden würde, nur mengenweise.
 */
class VisibilityScope
{
    public static function apply(Builder $query, ?User $user, string $cap = 'read'): Builder
    {
        $model = $query->getModel();
        $resourceType = $model::class;
        $table = $model->getTable();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        /** @var AuthzResolver $resolver */
        $resolver = app(AuthzResolver::class);
        $ownerCol = $resolver->ownerColumn($resourceType);
        $reachable = $resolver->reachableEntityIds($user, $cap);

        return $query->where(function ($q) use ($ownerCol, $reachable, $resourceType, $user, $table) {
            $any = false;

            // (a) Ersteller: das eigene Objekt.
            if ($ownerCol !== null) {
                $q->orWhere($table.'.'.$ownerCol, $user->id);
                $any = true;
            }

            // (b) hängt an einer Entity, die der User über den Baum erreicht.
            if ($reachable !== []) {
                $q->orWhereIn($table.'.id', function ($sub) use ($resourceType, $reachable) {
                    $sub->select('resource_id')
                        ->from('authz_resource_link')
                        ->where('resource_type', $resourceType)
                        ->whereIn('scope_id', $reachable);
                });
                $any = true;
            }

            // Kein Ersteller-Feld und keine Reichweite → nichts sichtbar.
            if (! $any) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
