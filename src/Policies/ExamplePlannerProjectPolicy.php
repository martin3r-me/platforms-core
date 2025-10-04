<?php

namespace Platform\Core\Policies;

use Platform\Core\Models\User;
use Platform\Core\Traits\HasRoleAccess;
use Platform\Core\Enums\StandardRole;

/**
 * Beispiel-Implementierung für PlannerProjectPolicy
 * Zeigt wie Module die Basis-Policies nutzen können
 */
class ExamplePlannerProjectPolicy extends RolePolicy
{
    use HasRoleAccess;

    /**
     * Modulspezifische Rollen-Logik
     */
    protected function getUserRole(User $user, $model): ?string
    {
        // Beispiel: Pivot-Tabelle project_users
        $relation = $model->projectUsers()->where('user_id', $user->id)->first();
        return $relation?->role ?? null;
    }

    /**
     * Modulspezifische Berechtigung überschreiben
     */
    public function delete(User $user, $model): bool
    {
        // Nur Owner darf löschen
        if ($this->isOwner($user, $model)) {
            return true;
        }

        // Oder Admin-Rolle
        return $this->hasRole($user, $model, StandardRole::getAdminRoles());
    }

    /**
     * Zusätzliche modulspezifische Berechtigungen
     */
    public function invite(User $user, $model): bool
    {
        return $this->hasRole($user, $model, StandardRole::getAdminRoles());
    }

    public function assign(User $user, $model): bool
    {
        return $this->hasRole($user, $model, StandardRole::getWriteRoles());
    }
}
