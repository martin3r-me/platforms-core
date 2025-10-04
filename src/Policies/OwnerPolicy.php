<?php

namespace Platform\Core\Policies;

use Platform\Core\Models\User;

class OwnerPolicy extends BasePolicy
{
    /**
     * Nur Owner hat Zugriff
     */
    public function view(User $user, $model): bool
    {
        return $this->isOwner($user, $model);
    }

    public function update(User $user, $model): bool
    {
        return $this->isOwner($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return $this->isOwner($user, $model);
    }

    /**
     * Owner-Policy braucht keine Rollen-Logik
     */
    protected function getUserRole(User $user, $model): ?string
    {
        return null;
    }
}
