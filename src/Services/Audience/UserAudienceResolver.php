<?php

namespace Platform\Core\Services\Audience;

use App\Models\User;
use Platform\Core\Contracts\AudienceResolverInterface;

/** Ziel = eine einzelne Person. */
class UserAudienceResolver implements AudienceResolverInterface
{
    public function type(): string
    {
        return 'user';
    }

    public function resolve(int $targetId, array $options = [], ?int $teamId = null): array
    {
        return [$targetId];
    }

    public function label(int $targetId, ?int $teamId = null): ?string
    {
        return User::query()->whereKey($targetId)->value('name');
    }
}
