<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\CanvasResolverInterface;

class NullCanvasResolver implements CanvasResolverInterface
{
    public function displayName(?int $canvasId): ?string
    {
        return null;
    }

    public function url(?int $canvasId): ?string
    {
        return null;
    }

    public function status(?int $canvasId): ?string
    {
        return null;
    }
}
