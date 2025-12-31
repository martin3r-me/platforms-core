<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ContextEnricherContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service für automatische Context-Erweiterung
 * 
 * Erweitert ToolContext mit zusätzlichen Informationen (User-History, Team-Context, etc.)
 */
class ToolContextEnrichmentService
{
    private const CACHE_PREFIX = 'context_enrichment:';
    private const CACHE_TTL = 300; // 5 Minuten

    /**
     * @var ContextEnricherContract[]
     */
    private array $enrichers = [];

    /**
     * Registriert einen Context-Enricher
     */
    public function registerEnricher(ContextEnricherContract $enricher): void
    {
        $this->enrichers[] = $enricher;
        
        // Sortiere nach Priorität (höher = zuerst)
        usort($this->enrichers, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
    }

    /**
     * Erweitert einen ToolContext
     */
    public function enrich(ToolContext $context, bool $useCache = true): ToolContext
    {
        // Prüfe Cache
        if ($useCache) {
            $cacheKey = self::CACHE_PREFIX . md5(json_encode([
                'user_id' => $context->user?->id,
                'team_id' => $context->team?->id,
            ]));
            
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $enrichedContext = $context;

        // Führe alle Enricher aus
        foreach ($this->enrichers as $enricher) {
            try {
                $enrichedContext = $enricher->enrich($enrichedContext);
            } catch (\Throwable $e) {
                // Silent fail - Enrichment sollte nicht die Tool-Ausführung blockieren
                Log::warning('[ContextEnrichment] Fehler beim Enrichen', [
                    'enricher' => get_class($enricher),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Cache erweiterten Context
        if ($useCache && isset($cacheKey)) {
            Cache::put($cacheKey, $enrichedContext, self::CACHE_TTL);
        }

        return $enrichedContext;
    }

    /**
     * Gibt alle registrierten Enricher zurück
     * 
     * @return ContextEnricherContract[]
     */
    public function getEnrichers(): array
    {
        return $this->enrichers;
    }

    /**
     * Invalidiert Context-Cache
     */
    public function invalidateCache(?int $userId = null, ?int $teamId = null): void
    {
        // Einfache Invalidation: Cache wird automatisch nach TTL ablaufen
        // Später: Tag-basierte Invalidation implementieren
    }
}

