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
 * indem Global-Core + optional Team-Extension gemergt werden.
 *
 * Merge-Regeln ("inherit + extend, never override"):
 *   - perspektive: Extension überschreibt Core nur, wenn Extension gesetzt
 *   - ton/heuristiken/negativ_raum: array_merge mit Deduplication
 *
 * Cold-Start: enabled_modules muss das aktuelle Modul enthalten, sonst empty.
 * Wenn $module === null (z.B. MCP-Discovery), wird der Layer trotzdem geliefert,
 * damit Clients ihn sehen können (Discovery-Transparenz).
 *
 * Cache: 1h TTL, Schlüssel enthält Version-IDs → bei Version-Update neuer Key.
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
     * @param string|null $module  Aktuelles Modul (null → Discovery-Modus, kein Enabled-Gate)
     */
    public function resolveFor(?Team $team, ?string $module): ResolvedLayer
    {
        try {
            $globalLayer = SemanticLayer::global();
            $globalVersion = $this->loadActiveVersion($globalLayer);

            $teamLayer = $team ? SemanticLayer::forTeam($team->id) : null;
            $teamVersion = $this->loadActiveVersion($teamLayer);

            // Nichts da → leer
            if ($globalVersion === null && $teamVersion === null) {
                return ResolvedLayer::empty();
            }

            // Cold-Start: Modul-Gate (nur wenn Modul angegeben)
            if ($module !== null && $module !== '') {
                $enabledOnGlobal = $globalLayer?->hasModuleEnabled($module) ?? false;
                $enabledOnTeam = $teamLayer?->hasModuleEnabled($module) ?? false;

                // Production-Status überschreibt Modul-Gate (Layer gilt dann überall)
                $productionOnGlobal = $globalLayer?->status === SemanticLayer::STATUS_PRODUCTION;
                $productionOnTeam = $teamLayer?->status === SemanticLayer::STATUS_PRODUCTION;

                $active = $enabledOnGlobal || $enabledOnTeam || $productionOnGlobal || $productionOnTeam;
                if (!$active) {
                    return ResolvedLayer::empty();
                }
            }

            $cacheKey = $this->buildCacheKey(
                teamId: $team?->id,
                module: $module,
                globalVersionId: $globalVersion?->id,
                teamVersionId: $teamVersion?->id,
            );

            return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use (
                $globalVersion,
                $teamVersion
            ) {
                return $this->buildResolved($globalVersion, $teamVersion);
            });
        } catch (\Throwable $e) {
            // Defensive: nie den Haupt-Flow brechen
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
        // Kein Pattern-Delete im Default-Cache. Wir bumpen einen globalen
        // Version-Counter, dessen Wert in den Key einfließt, und invalidieren
        // damit effektiv alle bestehenden Einträge.
        Cache::forget(self::CACHE_KEY_PREFIX . ':version_bump');
    }

    private function loadActiveVersion(?SemanticLayer $layer): ?SemanticLayerVersion
    {
        if ($layer === null) {
            return null;
        }
        if (!$layer->isActive()) {
            return null;
        }
        if ($layer->current_version_id === null) {
            return null;
        }
        return SemanticLayerVersion::find($layer->current_version_id);
    }

    private function buildResolved(
        ?SemanticLayerVersion $globalVersion,
        ?SemanticLayerVersion $teamVersion,
    ): ResolvedLayer {
        $scopeChain = [];
        $versionChain = [];

        $perspektive = '';
        $ton = [];
        $heuristiken = [];
        $negativRaum = [];

        if ($globalVersion !== null) {
            $scopeChain[] = 'global';
            $versionChain[] = $globalVersion->semver;
            $perspektive = (string) $globalVersion->perspektive;
            $ton = array_values($globalVersion->ton ?? []);
            $heuristiken = array_values($globalVersion->heuristiken ?? []);
            $negativRaum = array_values($globalVersion->negativ_raum ?? []);
        }

        if ($teamVersion !== null) {
            $teamLayer = $teamVersion->layer;
            $scopeChain[] = 'team:' . ($teamLayer?->scope_id ?? '?');
            $versionChain[] = $teamVersion->semver;

            // perspektive: Extension überschreibt nur, wenn gesetzt
            $extPerspektive = trim((string) $teamVersion->perspektive);
            if ($extPerspektive !== '') {
                $perspektive = $extPerspektive;
            }

            // Arrays: merge + deduplication (Case-sensitive, trim)
            $ton = $this->mergeDedup($ton, $teamVersion->ton ?? []);
            $heuristiken = $this->mergeDedup($heuristiken, $teamVersion->heuristiken ?? []);
            $negativRaum = $this->mergeDedup($negativRaum, $teamVersion->negativ_raum ?? []);
        }

        $rendered = $this->scaffold->render(
            perspektive: $perspektive,
            ton: $ton,
            heuristiken: $heuristiken,
            negativRaum: $negativRaum,
            versionChain: $versionChain,
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

    private function buildCacheKey(
        ?int $teamId,
        ?string $module,
        ?int $globalVersionId,
        ?int $teamVersionId,
    ): string {
        $bump = (int) Cache::rememberForever(
            self::CACHE_KEY_PREFIX . ':version_bump',
            fn () => 1
        );
        return sprintf(
            '%s:b%d:t%s:m%s:gv%s:tv%s',
            self::CACHE_KEY_PREFIX,
            $bump,
            $teamId ?? 'none',
            $module ?? 'none',
            $globalVersionId ?? 'none',
            $teamVersionId ?? 'none',
        );
    }
}
