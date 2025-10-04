<?php

namespace Platform\Core\Traits;

use Platform\Core\Models\User;

trait HasOwnerAccess
{
    /**
     * Prüft ob User Owner der Ressource ist
     */
    protected function isOwner(User $user, $model): bool
    {
        return isset($model->user_id) && $model->user_id === $user->id;
    }

    /**
     * Owner hat immer vollen Zugriff
     */
    protected function ownerCanAccess(User $user, $model): bool
    {
        return $this->isOwner($user, $model);
    }
}
