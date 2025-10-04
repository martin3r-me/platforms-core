<?php

namespace Platform\Core\Policies;

use Platform\Core\Models\User;
use Platform\Core\Enums\StandardRole;

abstract class BasePolicy
{
    /**
     * Prüft ob User im aktuellen Team ist
     */
    protected function hasTeamAccess(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Prüft ob User Owner der Ressource ist
     */
    protected function isOwner(User $user, $model): bool
    {
        return isset($model->user_id) && $model->user_id === $user->id;
    }

    /**
     * Prüft ob User im Team der Ressource ist
     */
    protected function isInTeam(User $user, $model): bool
    {
        return isset($model->team_id) && 
               $user->currentTeam && 
               $model->team_id === $user->currentTeam->id;
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
     * Hole die Rolle des Users für ein Model
     * Muss in der konkreten Policy implementiert werden
     */
    abstract protected function getUserRole(User $user, $model): ?string;

    /**
     * Standard View-Berechtigung
     */
    public function view(User $user, $model): bool
    {
        // Owner hat immer Zugriff
        if ($this->isOwner($user, $model)) {
            return true;
        }

        // Team-Mitglieder haben Zugriff
        if ($this->isInTeam($user, $model)) {
            return true;
        }

        return false;
    }

    /**
     * Standard Update-Berechtigung
     */
    public function update(User $user, $model): bool
    {
        // Owner hat immer Zugriff
        if ($this->isOwner($user, $model)) {
            return true;
        }

        // Team-Mitglieder haben Zugriff
        if ($this->isInTeam($user, $model)) {
            return true;
        }

        return false;
    }

    /**
     * Standard Delete-Berechtigung (nur Owner)
     */
    public function delete(User $user, $model): bool
    {
        return $this->isOwner($user, $model);
    }
}
