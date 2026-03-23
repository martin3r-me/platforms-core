<?php

namespace Platform\Core\Models\Concerns;

use Illuminate\Support\Collection;
use Platform\Core\Models\CoreEntityLink;
use Platform\Core\Services\EntityLinkService;

trait HasEntityLinks
{
    /**
     * Gibt die IDs aller verlinkten Entitäten eines bestimmten Typs zurück.
     *
     * @return array<int>
     */
    public function getLinkedIds(string $linkedType, ?string $linkType = null): array
    {
        return app(EntityLinkService::class)->getLinkedIds(
            $this->team_id,
            $this->getMorphClass(),
            $this->getKey(),
            $linkedType,
            $linkType,
        );
    }

    /**
     * Gibt alle CoreEntityLink-Records für diese Entity zurück.
     *
     * @return Collection<int, CoreEntityLink>
     */
    public function getEntityLinks(?string $linkedType = null, ?string $linkType = null): Collection
    {
        return app(EntityLinkService::class)->getLinked(
            $this->team_id,
            $this->getMorphClass(),
            $this->getKey(),
            $linkedType,
            $linkType,
        );
    }
}
