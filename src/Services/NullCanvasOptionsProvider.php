<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CanvasOptionsProviderInterface;

class NullCanvasOptionsProvider implements CanvasOptionsProviderInterface
{
    public function options(?int $teamId, ?string $query = null, int $limit = 20): array
    {
        return [];
    }
}
