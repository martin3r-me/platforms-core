<?php

namespace Platform\Core\Policies;

use Platform\Core\Models\User;
use Platform\Core\Models\Team;
use Platform\Core\Enums\TeamRole;

class TeamPolicy
{
    /**
     * Hilfsfunktion: Hole die Rolle des Users im Team aus der Pivot-Tabelle.
     */
    protected function getUserRole(User $user, Team $team): ?string
    {
        $relation = $team->users->firstWhere('id', $user->id);
        return $relation?->pivot?->role ?? null;
    }

    /**
     * Darf der User dieses Team sehen?
     */
    public function view(User $user, Team $team): bool
    {
        // Jeder, der eine Pivot-Rolle im Team hat (Owner, Admin, Member), darf das Team sehen
        return in_array($this->getUserRole($user, $team), [
            TeamRole::OWNER->value,
            TeamRole::ADMIN->value,
            TeamRole::MEMBER->value,
        ], true);
    }

    /**
     * Darf der User dieses Team bearbeiten?
     */
    public function update(User $user, Team $team): bool
    {
        // Owner und Admins dürfen das Team bearbeiten
        return in_array($this->getUserRole($user, $team), [
            TeamRole::OWNER->value,
            TeamRole::ADMIN->value,
        ], true);
    }

    /**
     * Darf der User das Team löschen?
     */
    public function delete(User $user, Team $team): bool
    {
        // Nur der Owner darf das Team löschen
        return $this->getUserRole($user, $team) === TeamRole::OWNER->value;
    }

    /**
     * Darf der User Mitglieder einladen?
     */
    public function invite(User $user, Team $team): bool
    {
        // Owner und Admins dürfen einladen
        return in_array($this->getUserRole($user, $team), [
            TeamRole::OWNER->value,
            TeamRole::ADMIN->value,
        ], true);
    }

    /**
     * Darf der User ein anderes Mitglied entfernen?
     */
    public function removeMember(User $user, Team $team, User $target): bool
    {
        $userRole = $this->getUserRole($user, $team);
        $targetRole = $this->getUserRole($target, $team);

        // Owner kann alle entfernen außer sich selbst
        if ($userRole === TeamRole::OWNER->value && $user->id !== $target->id) {
            return true;
        }

        // Admin kann Mitglieder (aber nicht sich selbst oder Owner) entfernen
        if ($userRole === TeamRole::ADMIN->value) {
            if (
                $targetRole === TeamRole::MEMBER->value &&
                $target->id !== $user->id
            ) {
                return true;
            }
        }

        return false;
    }
}