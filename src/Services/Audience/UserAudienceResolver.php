<?php

namespace Platform\Core\Services\Audience;

use Platform\Core\Contracts\AudienceResolverInterface;
use Platform\Core\Models\User;
use Platform\Core\Models\Team;

/** Ziel = eine einzelne Person. */
class UserAudienceResolver implements AudienceResolverInterface
{
    public function type(): string
    {
        return 'user';
    }

    public function typeLabel(): string
    {
        return 'Einzelne Person';
    }

    public function resolve(int $targetId, array $options = [], ?int $teamId = null): array
    {
        return [$targetId];
    }

    public function label(int $targetId, ?int $teamId = null): ?string
    {
        return User::query()->whereKey($targetId)->value('name');
    }

    public function options(?int $teamId = null): array
    {
        if (!$teamId) {
            return [];
        }

        $team = Team::find($teamId);
        if (!$team) {
            return [];
        }

        return $team->users()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn ($u) => ['id' => (int) $u->id, 'label' => (string) $u->name])
            ->all();
    }
}
