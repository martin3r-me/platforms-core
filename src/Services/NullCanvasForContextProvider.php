<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CanvasForContextProviderInterface;

class NullCanvasForContextProvider implements CanvasForContextProviderInterface
{
    public function forContext(string $contextType, int $contextId): array
    {
        return [];
    }
}
