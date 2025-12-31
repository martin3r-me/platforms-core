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
     */
    private function generateCacheKey(
        string $toolName,
        array $arguments,
        ToolContext $context
    ): string {
        // Cache-Key basierend auf tool_name + arguments + user_id + team_id
        $keyData = [
            'tool' => $toolName,
            'args' => $this->normalizeArguments($arguments),
            'user_id' => $context->user?->id,
            'team_id' => $context->team?->id,
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

        // Default: Cache nur read-only Tools
        // Prüfe ob Tool Metadata hat und read_only ist
        if ($tool instanceof \Platform\Core\Contracts\ToolMetadataContract) {
            $metadata = $tool->getMetadata();
            return (bool)($metadata['read_only'] ?? false);
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
     */
    public function get(
        string $toolName,
        array $arguments,
        ToolContext $context,
        ToolContract $tool
    ): ?ToolResult {
        if (!$this->shouldCache($toolName, $tool)) {
            return null;
        }

        $cacheKey = $this->generateCacheKey($toolName, $arguments, $context);
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            return null;
        }

        // Rekonstruiere ToolResult aus Cache
        return ToolResult::success(
            $cached['data'] ?? null,
            $cached['message'] ?? null,
            $cached['metadata'] ?? []
        );
    }

    /**
     * Speichert ein Tool-Result im Cache
     */
    public function put(
        string $toolName,
        array $arguments,
        ToolContext $context,
        ToolContract $tool,
        ToolResult $result,
        ?int $ttl = null
    ): void {
        if (!$this->shouldCache($toolName, $tool)) {
            return;
        }

        // Cache nur erfolgreiche Results
        if (!$result->success) {
            return;
        }

        $cacheKey = $this->generateCacheKey($toolName, $arguments, $context);
        
        // TTL aus Config oder Default
        if ($ttl === null) {
            $ttl = config("tools.tools.{$toolName}.cache_ttl", self::DEFAULT_TTL);
        }

        Cache::put($cacheKey, [
            'data' => $result->data,
            'message' => $result->metadata['message'] ?? null,
            'metadata' => $result->metadata,
        ], $ttl);
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

