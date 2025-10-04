<?php

namespace Platform\Core\Policies;

use Platform\Core\Models\User;

class TeamPolicy extends BasePolicy
{
    /**
     * Team-Mitglieder haben Zugriff
     */
    public function view(User $user, $model): bool
    {
        // Owner hat immer Zugriff
        if ($this->isOwner($user, $model)) {
            return true;
        }

        // Team-Mitglieder haben Zugriff
        return $this->isInTeam($user, $model);
    }

    public function update(User $user, $model): bool
    {
        // Owner hat immer Zugriff
        if ($this->isOwner($user, $model)) {
            return true;
        }

        // Team-Mitglieder haben Zugriff
        return $this->isInTeam($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        // Nur Owner darf löschen
        return $this->isOwner($user, $model);
    }

    /**
     * Team-Policy braucht keine Rollen-Logik
     */
    protected function getUserRole(User $user, $model): ?string
    {
        return null;
    }
}