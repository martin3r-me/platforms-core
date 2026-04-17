<?php

namespace Platform\Core\SemanticLayer\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Team;
use Platform\Core\SemanticLayer\DTOs\ResolvedLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\SemanticLayer\Schema\LayerSchemaValidator;

/**
 * Kern des Semantic Base Layers: löst pro Aufruf den effektiven Layer auf,
 * indem alle zutreffenden Layer (Global + Team) in sort_order gemergt werden.
 *
 * Multi-Layer Merge (N-Layer):
 *   1. Lade alle aktiven Global-Layer → filter nach appliesToContext($module)
 *   2. Lade alle aktiven Team-Layer → filter nach appliesToContext($module)
 *   3. Production-Status überschreibt Gate (wie bisher)
 *   4. Sort: Global vor Team, dann sort_order
 *   5. Merge sequentiell (perspektive=letzte nicht-leere, arrays=dedup-merge)
 *
 * Bei $module === null → nur ungated Leitbild-Layer.
 *
 * Cache: 1h TTL, Schlüssel enthält Hash aller beitragenden Version-IDs.
 */
class SemanticLayerResolver
{
    public const CACHE_TTL_SECONDS = 3600;
    public const CACHE_KEY_PREFIX = 'semantic_layer:resolved';

    public function __construct(
        private readonly SemanticLayerScaffold $scaffold,
        private readonly LayerSchemaValidator $validator,
    ) {
    }

    /**
     * Löst den effektiven Layer für Team + Modul-Kontext auf.
     *
     * @param Team|null $team    Team-Scope (null → nur Global)
     * @param string|null $module  Aktuelles Modul/Kontext-Key (null → nur ungated Layer)
     */
    public function resolveFor(?Team $team, ?string $module): ResolvedLayer
    {
        try {
            $contributing = $this->collectContributingLayers($team, $module);

            if (empty($contributing)) {
                return ResolvedLayer::empty();
            }

            $cacheKey = $this->buildCacheKey(
                teamId: $team?->id,
                module: $module,
                contributing: $contributing,
            );

            return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($contributing) {
                return $this->buildResolved($contributing);
            });
        } catch (\Throwable $e) {
            Log::warning('[SemanticLayerResolver] resolveFor failed', [
                'team_id' => $team?->id,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);
            return ResolvedLayer::empty();
        }
    }

    /**
     * Cache invalidieren — wird von Model-Events getriggert.
     */
    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . ':version_bump');
    }

    /**
     * Sammelt alle Layer+Version-Paare, die zum Kontext beitragen.
     *
     * @return array<int, array{layer: SemanticLayer, version: SemanticLayerVersion}>
     */
    private function collectContributingLayers(?Team $team, ?string $module): array
    {
        $contributing = [];

        // Global layers
        $globalLayers = SemanticLayer::globalLayers();
        foreach ($globalLayers as $layer) {
            if ($this->layerContributes($layer, $module)) {
                $version = $this->loadActiveVersion($layer);
                if ($version !== null) {
                    $contributing[] = ['layer' => $layer, 'version' => $version];
                }
            }
        }

        // Team layers
        if ($team) {
            $teamLayers = SemanticLayer::forTeamLayers($team->id);
            foreach ($teamLayers as $layer) {
                if ($this->layerContributes($layer, $module)) {
                    $version = $this->loadActiveVersion($layer);
                    if ($version !== null) {
                        $contributing[] = ['layer' => $layer, 'version' => $version];
                    }
                }
            }
        }

        return $contributing;
    }

    /**
     * Prüft, ob ein Layer zum Kontext beiträgt.
     *
     * - Layer muss aktiv sein (pilot/production)
     * - Production-Status überschreibt Gate (Layer gilt überall)
     * - Sonst: appliesToContext($module)
     */
    private function layerContributes(SemanticLayer $layer, ?string $module): bool
    {
        if (!$layer->isActive()) {
            return false;
        }

        // Production überschreibt Gate — Layer gilt überall
        if ($layer->status === SemanticLayer::STATUS_PRODUCTION) {
            return true;
        }

        // Pilot: Gate prüfen
        return $layer->appliesToContext($module);
    }

    private function loadActiveVersion(SemanticLayer $layer): ?SemanticLayerVersion
    {
        if ($layer->current_version_id === null) {
            return null;
        }
        return SemanticLayerVersion::find($layer->current_version_id);
    }

    /**
     * @param array<int, array{layer: SemanticLayer, version: SemanticLayerVersion}> $contributing
     */
    private function buildResolved(array $contributing): ResolvedLayer
    {
        $scopeChain = [];
        $versionChain = [];
        $labelChain = [];

        $perspektive = '';
        $ton = [];
        $heuristiken = [];
        $negativRaum = [];

        foreach ($contributing as $entry) {
            $layer = $entry['layer'];
            $version = $entry['version'];

            // Scope chain with label
            if ($layer->scope_type === SemanticLayer::SCOPE_GLOBAL) {
                $scopeChain[] = 'global:' . $layer->label;
            } else {
                $scopeChain[] = 'team:' . ($layer->scope_id ?? '?') . ':' . $layer->label;
            }

            $labelChain[] = $layer->label . ':v' . $version->semver;
            $versionChain[] = $version->semver;

            // Perspektive: letzte nicht-leere gewinnt
            $extPerspektive = trim((string) $version->perspektive);
            if ($extPerspektive !== '') {
                $perspektive = $extPerspektive;
            }

            // Arrays: merge + deduplication
            $ton = $this->mergeDedup($ton, $version->ton ?? []);
            $heuristiken = $this->mergeDedup($heuristiken, $version->heuristiken ?? []);
            $negativRaum = $this->mergeDedup($negativRaum, $version->negativ_raum ?? []);
        }

        $rendered = $this->scaffold->render(
            perspektive: $perspektive,
            ton: $ton,
            heuristiken: $heuristiken,
            negativRaum: $negativRaum,
            versionChain: $versionChain,
            labelChain: $labelChain,
        );

        $tokens = $this->validator->estimateTokens($rendered);

        return new ResolvedLayer(
            perspektive: $perspektive,
            ton: $ton,
            heuristiken: $heuristiken,
            negativ_raum: $negativRaum,
            scope_chain: $scopeChain,
            version_chain: $versionChain,
            token_count: $tokens,
            rendered_block: $rendered,
        );
    }

    /**
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, string>
     */
    private function mergeDedup(array $a, array $b): array
    {
        $merged = array_merge($a, $b);
        $seen = [];
        $result = [];
        foreach ($merged as $item) {
            $key = mb_strtolower(trim((string) $item));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = trim((string) $item);
        }
        return $result;
    }

    /**
     * @param array<int, array{layer: SemanticLayer, version: SemanticLayerVersion}> $contributing
     */
    private function buildCacheKey(
        ?int $teamId,
        ?string $module,
        array $contributing,
    ): string {
        $bump = (int) Cache::rememberForever(
            self::CACHE_KEY_PREFIX . ':version_bump',
            fn () => 1
        );

        // Hash aller beitragenden Version-IDs
        $versionIds = array_map(fn ($e) => $e['version']->id, $contributing);
        $hash = md5(implode(',', $versionIds));

        return sprintf(
            '%s:b%d:t%s:m%s:h%s',
            self::CACHE_KEY_PREFIX,
            $bump,
            $teamId ?? 'none',
            $module ?? 'none',
            substr($hash, 0, 12),
        );
    }
}
