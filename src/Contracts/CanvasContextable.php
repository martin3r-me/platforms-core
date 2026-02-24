<?php

namespace Platform\Core\Contracts;

/**
 * Interface for models that can serve as context for Canvas modules (BMC, Project Canvas, etc.)
 *
 * Allows any module to provide contextual information to canvas tools,
 * e.g. linking a BMC canvas to a specific project, company, or other entity.
 */
interface CanvasContextable
{
    /**
     * Returns the canvas context type identifier (e.g. 'project', 'company', 'product').
     */
    public function canvasType(): string;

    /**
     * Returns a human-readable label for this context (e.g. project name).
     */
    public function canvasLabel(): string;

    /**
     * Returns structured context data for canvas tools / LLM consumption.
     *
     * @return array<string, mixed>
     */
    public function getCanvasContext(): array;
}
