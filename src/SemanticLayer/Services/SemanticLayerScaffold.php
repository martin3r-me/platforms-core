<?php

namespace Platform\Core\SemanticLayer\Services;

/**
 * Pure Render-Funktion. Keine Persistenz, kein State.
 *
 * Erzeugt den Prompt-Block-Text im fixierten Format:
 *
 *   [SEMANTIC LAYER · leitbild:v1.0.0 + mcp:v0.2.0]
 *   Perspektive: {perspektive}
 *
 *   Ton:
 *   - {ton[0]}
 *   ...
 *
 *   Heuristiken (im Zweifel):
 *   - {heuristiken[0]}
 *   ...
 *
 *   Was wir nie sagen / sind:
 *   - {negativ_raum[0]}
 *   ...
 *   [/SEMANTIC LAYER]
 *
 * Reihenfolge ist fix — Perspektive zuerst, da frühe Tokens höchste Attention haben.
 */
class SemanticLayerScaffold
{
    /**
     * @param array<int, string> $ton
     * @param array<int, string> $heuristiken
     * @param array<int, string> $negativRaum
     * @param array<int, string> $versionChain  z.B. ['1.0.0'] oder ['1.0.0', '0.2.0']
     * @param array<int, string> $labelChain    z.B. ['leitbild:v1.0.0', 'mcp:v0.2.0']
     */
    public function render(
        string $perspektive,
        array $ton,
        array $heuristiken,
        array $negativRaum,
        array $versionChain = [],
        array $labelChain = [],
    ): string {
        // Label-aware header wenn labelChain vorhanden, sonst Fallback auf versionChain
        if (!empty($labelChain)) {
            $versionLabel = implode(' + ', $labelChain);
        } elseif (!empty($versionChain)) {
            $versionLabel = 'v' . implode(' + v', $versionChain);
        } else {
            $versionLabel = 'v?';
        }

        $lines = [];
        $lines[] = "[SEMANTIC LAYER · {$versionLabel}]";
        $lines[] = 'Perspektive: ' . trim($perspektive);

        if (!empty($ton)) {
            $lines[] = '';
            $lines[] = 'Ton:';
            foreach ($ton as $item) {
                $lines[] = '- ' . trim((string) $item);
            }
        }

        if (!empty($heuristiken)) {
            $lines[] = '';
            $lines[] = 'Heuristiken (im Zweifel):';
            foreach ($heuristiken as $item) {
                $lines[] = '- ' . trim((string) $item);
            }
        }

        if (!empty($negativRaum)) {
            $lines[] = '';
            $lines[] = 'Was wir nie sagen / sind:';
            foreach ($negativRaum as $item) {
                $lines[] = '- ' . trim((string) $item);
            }
        }

        $lines[] = '[/SEMANTIC LAYER]';

        return implode("\n", $lines);
    }
}
