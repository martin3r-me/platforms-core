<?php

namespace Platform\Core\Contracts;

interface CanvasResolverInterface
{
    /**
     * Gibt den Anzeigenamen eines Canvas zurück.
     */
    public function displayName(?int $canvasId): ?string;

    /**
     * Gibt die URL zur Canvas-Ansicht zurück.
     */
    public function url(?int $canvasId): ?string;

    /**
     * Gibt den Status eines Canvas zurück (draft, active, archived).
     */
    public function status(?int $canvasId): ?string;
}
