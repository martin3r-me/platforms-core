<?php

namespace Platform\Core\Traits;

use Platform\Core\Models\User;

trait HasTeamAccess
{
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
     * Team-Mitglieder haben Zugriff
     */
    protected function teamCanAccess(User $user, $model): bool
    {
        return $this->isInTeam($user, $model);
    }
}
