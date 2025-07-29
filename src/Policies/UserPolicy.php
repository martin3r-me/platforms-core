<?php

namespace Platform\Core\Policies;

use Platform\Core\Models\User;

class UserPolicy
{
    /**
     * Darf der eingeloggte User das User-Profil sehen?
     */
    public function view(User $user, User $target): bool
    {
        // Jeder darf sich selbst sehen
        if ($user->id === $target->id) {
            return true;
        }

        // Beispiel: Admins dürfen alle User sehen
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Optional: Team-Admins oder ähnliche Rollen dürfen User im Team sehen (wenn du so etwas hast)
        // if ($user->currentTeam && $user->isTeamAdmin()) {
        //     return $target->teams->contains($user->currentTeam);
        // }

        // Sonst kein Zugriff
        return false;
    }

    /**
     * Darf der eingeloggte User das User-Profil bearbeiten?
     */
    public function update(User $user, User $target): bool
    {
        // Jeder darf sich selbst bearbeiten
        if ($user->id === $target->id) {
            return true;
        }

        // Admins dürfen beliebige User bearbeiten
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Darf der eingeloggte User ein User-Konto löschen?
     */
    public function delete(User $user, User $target): bool
    {
        // Admins dürfen beliebige User löschen, außer sich selbst
        if (
            method_exists($user, 'isAdmin') &&
            $user->isAdmin() &&
            $user->id !== $target->id
        ) {
            return true;
        }

        // User kann ggf. sein eigenes Konto löschen (optional)
        // if ($user->id === $target->id) {
        //     return true;
        // }

        return false;
    }

    /**
     * Darf der eingeloggte User die Rolle eines Users ändern?
     */
    public function changeRole(User $user, User $target): bool
    {
        // Nur Admins dürfen Rollen ändern
        return method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    // Weitere Methoden je nach Use Case (z.B. impersonate, deactivate ...)
}