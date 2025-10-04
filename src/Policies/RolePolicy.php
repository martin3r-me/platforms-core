<?php

namespace Platform\Core\Policies;

use Platform\Core\Models\User;
use Platform\Core\Enums\StandardRole;

class RolePolicy extends BasePolicy
{
    /**
     * Rollenbasierte Berechtigung
     */
    public function view(User $user, $model): bool
    {
        // Owner hat immer Zugriff
        if ($this->isOwner($user, $model)) {
            return true;
        }

        // Team-Mitglieder mit Leserolle
        if ($this->isInTeam($user, $model)) {
            return $this->hasRole($user, $model, StandardRole::getReadRoles());
        }

        return false;
    }

    public function update(User $user, $model): bool
    {
        // Owner hat immer Zugriff
        if ($this->isOwner($user, $model)) {
            return true;
        }

        // Team-Mitglieder mit Schreibrolle
        if ($this->isInTeam($user, $model)) {
            return $this->hasRole($user, $model, StandardRole::getWriteRoles());
        }

        return false;
    }

    public function delete(User $user, $model): bool
    {
        // Nur Owner oder Admin
        if ($this->isOwner($user, $model)) {
            return true;
        }

        if ($this->isInTeam($user, $model)) {
            return $this->hasRole($user, $model, StandardRole::getAdminRoles());
        }

        return false;
    }

    /**
     * Muss in der konkreten Policy implementiert werden
     */
    protected function getUserRole(User $user, $model): ?string
    {
        // Beispiel-Implementierung - muss überschrieben werden
        if (method_exists($model, 'members')) {
            $relation = $model->members()->where('user_id', $user->id)->first();
            return $relation?->role ?? null;
        }

        return null;
    }
}
