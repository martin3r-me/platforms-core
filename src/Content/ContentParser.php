<?php

namespace Platform\Core\Content;

/**
 * Wandelt Markdown (+ Callouts + Applet-Fences) in eine normalisierte
 * Block-Liste (AST-lite). Reine Logik, design-agnostisch — kennt keine Farben,
 * kein nx. Konsumenten (Strategie, Wiki, Notes, Reporting …) rendern die Blöcke
 * über die nx-Komponente <x-nx-content :blocks>.
 *
 * Rückgabe: ['blocks' => Block[], 'meta' => []]
 *   Block = ['type' => BlockType-value, ...typspezifische Felder]
 */
interface ContentParser
{
    /**
     * @return array{blocks: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function parse(?string $markdown): array;
}
