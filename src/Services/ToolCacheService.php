<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Cache;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Service für Tool-Result-Caching
 * 
 * Cached Ergebnisse von read-only Tools für bessere Performance
 */
class ToolCacheService
{
    private const CACHE_PREFIX = 'tool_result:';
    private const DEFAULT_TTL = 3600; // 1 Stunde

    /**
     * Generiert einen Cache-Key für ein Tool
     * 
     * Cache-Key enthält:
     * - Tool-Name
     * - Args-Hash (normalisierte Argumente)
     * - User-ID
     * - Team-ID
     * - Scope (optional, z.B. "team:5" oder "user:10")
     */
    private function generateCacheKey(
        string $toolName,
        array $arguments,
        ToolContext $context,
        ?string $scope = null
    ): string {
        // Normalisiere Argumente und erstelle Hash
        $normalizedArgs = $this->normalizeArguments($arguments);
        $argsHash = hash('sha256', json_encode($normalizedArgs));
        
        // Cache-Key basierend auf tool_name + args_hash + user_id + team_id + scope
        $keyData = [
            'tool' => $toolName,
            'args_hash' => $argsHash,
            'user_id' => $context->user?->id,
            'team_id' => $context->team?->id,
            'scope' => $scope,
        ];
        
        return self::CACHE_PREFIX . md5(json_encode($keyData));
    }

    /**
     * Normalisiert Argumente für Cache-Key (sortiert, entfernt null-Werte)
     */
    private function normalizeArguments(array $arguments): array
    {
        // Sortiere nach Keys
        ksort($arguments);
        
        // Entferne null-Werte (können variieren, aber sollten nicht den Cache-Key beeinflussen)
        $normalized = [];
        foreach ($arguments as $key => $value) {
            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }
        
        return $normalized;
    }

    /**
     * Prüft ob ein Tool gecacht werden sollte
     */
    public function shouldCache(string $toolName, ToolContract $tool): bool
    {
        // Prüfe Config (wird später implementiert)
        $config = config('tools.cache.enabled', true);
        if (!$config) {
            return false;
        }

        // Prüfe per-Tool Config
        $toolConfig = config("tools.tools.{$toolName}.cache", null);
        if ($toolConfig !== null) {
            return (bool)$toolConfig;
        }

        // Default: Cache nur read-only Tools mit risk_level === 'safe'
        // Prüfe ob Tool Metadata hat und read_only + safe ist
        if ($tool instanceof \Platform\Core\Contracts\ToolMetadataContract) {
            $metadata = $tool->getMetadata();
            $readOnly = (bool)($metadata['read_only'] ?? false);
            $riskLevel = $metadata['risk_level'] ?? 'write';
            return $readOnly && $riskLevel === 'safe';
        }

        // Heuristik: Tools mit "list", "get", "describe" im Namen sind meist read-only
        $readOnlyPatterns = ['list', 'get', 'describe', 'search', 'find'];
        foreach ($readOnlyPatterns as $pattern) {
            if (stripos($toolName, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Holt gecachtes Result (falls vorhanden)
     * 
     * Unterstützt stale-while-revalidate:
     * - Wenn Cache fresh: direkt zurückgeben
     * - Wenn Cache stale: zurückgeben, aber im Hintergrund aktualisieren (wird später implementiert)
     */
    public function get(
        string $toolName,
        array $arguments,
        ToolContext $context,
        ToolContract $tool,
        ?string $scope = null
    ): ?ToolResult {
        if (!$this->shouldCache($toolName, $tool)) {
            return null;
        }

        $cacheKey = $this->generateCacheKey($toolName, $arguments, $context, $scope);
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            return null;
        }

        // Prüfe ob Cache stale ist (stale-while-revalidate)
        $isStale = false;
        if (isset($cached['expires_at'])) {
            $expiresAt = $cached['expires_at'];
            $staleAt = $cached['stale_at'] ?? null;
            
            if ($staleAt && now()->timestamp > $staleAt) {
                $isStale = true;
            }
        }

        // Rekonstruiere ToolResult aus Cache
        $result = ToolResult::success(
            $cached['data'] ?? null,
            $cached['message'] ?? null,
            array_merge($cached['metadata'] ?? [], [
                'cache_hit' => true,
                'cache_stale' => $isStale,
            ])
        );

        // TODO: Bei stale Cache: Im Hintergrund aktualisieren (wird später implementiert)
        // if ($isStale) {
        //     dispatch(new RefreshStaleCacheJob($toolName, $arguments, $context, $scope));
        // }

        return $result;
    }

    /**
     * Speichert ein Tool-Result im Cache
     * 
     * Unterstützt stale-while-revalidate:
     * - TTL: Zeit bis Cache als "stale" gilt
     * - stale_ttl: Zeit bis Cache komplett abläuft (z.B. 1h + 24h stale)
     */
    public function put(
        string $toolName,
        array $arguments,
        ToolContext $context,
        ToolContract $tool,
        ToolResult $result,
        ?int $ttl = null,
        ?string $scope = null
    ): void {
        if (!$this->shouldCache($toolName, $tool)) {
            return;
        }

        // Cache nur erfolgreiche Results
        if (!$result->success) {
            return;
        }

        $cacheKey = $this->generateCacheKey($toolName, $arguments, $context, $scope);
        
        // TTL aus Config oder Default
        if ($ttl === null) {
            $ttl = config("tools.tools.{$toolName}.cache_ttl", self::DEFAULT_TTL);
        }
        
        // stale_ttl aus Config (optional, z.B. 24h = 86400)
        $staleTtl = config("tools.tools.{$toolName}.cache_stale_ttl", null);
        if ($staleTtl === null) {
            // Default: 3x TTL für stale-while-revalidate
            $staleTtl = $ttl * 3;
        }

        $now = now()->timestamp;
        $expiresAt = $now + $ttl;
        $staleAt = $now + $staleTtl;

        Cache::put($cacheKey, [
            'data' => $result->data,
            'message' => $result->metadata['message'] ?? null,
            'metadata' => $result->metadata,
            'expires_at' => $expiresAt,
            'stale_at' => $staleAt,
            'created_at' => $now,
        ], $staleTtl); // Cache für stale_ttl speichern
    }

    /**
     * Invalidiert Cache für ein Tool
     */
    public function invalidate(string $toolName, ?int $userId = null, ?int $teamId = null): void
    {
        // Einfache Invalidation: Alle Cache-Keys mit diesem Tool-Namen
        // WICHTIG: Dies ist eine vereinfachte Implementierung
        // In Production könnte man Tags verwenden (Redis) oder einen Cache-Tag-Service
        
        // Für jetzt: Cache wird automatisch nach TTL ablaufen
        // Später: Tag-basierte Invalidation implementieren
    }

    /**
     * Invalidiert alle Tool-Caches
     */
    public function invalidateAll(): void
    {
        // WICHTIG: Implementierung hängt vom Cache-Driver ab
        // Für jetzt: Cache wird automatisch nach TTL ablaufen
    }

    /**
     * Gibt Cache-Statistiken zurück
     */
    public function getStatistics(): array
    {
        // WICHTIG: Implementierung hängt vom Cache-Driver ab
        // Für jetzt: Basis-Statistiken
        return [
            'cache_enabled' => config('tools.cache.enabled', true),
            'default_ttl' => self::DEFAULT_TTL,
        ];
    }
}

