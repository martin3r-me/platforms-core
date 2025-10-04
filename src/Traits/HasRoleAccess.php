<?php

namespace Platform\Core\Traits;

use Platform\Core\Models\User;
use Platform\Core\Enums\StandardRole;

trait HasRoleAccess
{
    /**
     * Hole die Rolle des Users für ein Model
     */
    protected function getUserRole(User $user, $model): ?string
    {
        // Standard-Implementierung für Pivot-Tabellen
        if (method_exists($model, 'members')) {
            $relation = $model->members()->where('user_id', $user->id)->first();
            return $relation?->role ?? null;
        }

        // Alternative: Direkte Relation
        if (method_exists($model, 'users')) {
            $relation = $model->users()->where('user_id', $user->id)->first();
            return $relation?->role ?? null;
        }

        return null;
    }

    /**
     * Prüft ob User eine bestimmte Rolle hat
     */
    protected function hasRole(User $user, $model, array $roles): bool
    {
        $userRole = $this->getUserRole($user, $model);
        return $userRole && in_array($userRole, $roles, true);
    }

    /**
     * Prüft Schreibzugriff
     */
    protected function canWrite(User $user, $model): bool
    {
        return $this->hasRole($user, $model, StandardRole::getWriteRoles());
    }

    /**
     * Prüft Leszugriff
     */
    protected function canRead(User $user, $model): bool
    {
        return $this->hasRole($user, $model, StandardRole::getReadRoles());
    }

    /**
     * Prüft Admin-Zugriff
     */
    protected function canAdmin(User $user, $model): bool
    {
        return $this->hasRole($user, $model, StandardRole::getAdminRoles());
    }
}
