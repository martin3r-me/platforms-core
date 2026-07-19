<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\SeoSignalServiceInterface;

/**
 * No-Op-Fallback, wenn das SEO-Modul nicht installiert ist.
 */
class NullSeoSignalService implements SeoSignalServiceInterface
{
    public function getSignals(int $teamId, string $url): ?array
    {
        return null;
    }

    public function getSignalsForNode(int $teamId, int $entityId): array
    {
        return [];
    }

    public function getSignalsBySource(int $teamId, string $sourceModule, array $sourceIds): array
    {
        return [];
    }
}
