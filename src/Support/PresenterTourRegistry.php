<?php

namespace Platform\Core\Support;

use Platform\Core\Contracts\PresenterTourProvider;

/**
 * Singleton-Registry: das tour-Modul registriert hier seinen Provider. Das Presenter-Overlay
 * fragt sie nach dem aktiven Tour-Schritt — ohne das tour-Modul hart zu kennen.
 */
class PresenterTourRegistry
{
    protected ?PresenterTourProvider $provider = null;

    public function setProvider(PresenterTourProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function hasProvider(): bool
    {
        return $this->provider !== null;
    }

    public function activeStep(int $userId, int $teamId): ?array
    {
        return $this->provider?->activeStep($userId, $teamId);
    }

    public function advance(int $userId, int $teamId): void
    {
        $this->provider?->advance($userId, $teamId);
    }

    public function stop(int $userId, int $teamId): void
    {
        $this->provider?->stop($userId, $teamId);
    }
}
