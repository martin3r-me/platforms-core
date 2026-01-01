<?php

namespace Platform\Core\Services;

use Platform\Core\Tools\ToolRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Tool-Name-Mapper Service
 * 
 * Verwaltet Mapping zwischen canonical names (planner.projects.create) 
 * und provider names (planner_projects_create) für verschiedene LLM-Provider.
 * 
 * Features:
 * - Reversibles Mapping mit Registry-Validierung
 * - Logging immer mit canonical name
 * - Cache für Mappings
 */
class ToolNameMapper
{
    private const CACHE_PREFIX = 'tool_name_mapping:';
    private const CACHE_TTL = 86400; // 24 Stunden
    
    private array $mappingCache = [];
    
    public function __construct(
        private ?ToolRegistry $registry = null
    ) {
        // Lazy-Load Registry
        if ($this->registry === null) {
            try {
                $this->registry = app(ToolRegistry::class);
            } catch (\Throwable $e) {
                // Registry nicht verfügbar - wird später verwendet
            }
        }
    }
    
    /**
     * Konvertiert canonical name zu provider name
     * 
     * @param string $canonicalName z.B. "planner.projects.create"
     * @return string z.B. "planner_projects_create"
     */
    public function toProvider(string $canonicalName): string
    {
        // Cache prüfen
        if (isset($this->mappingCache[$canonicalName])) {
            return $this->mappingCache[$canonicalName];
        }
        
        // Persistent Cache prüfen
        $cacheKey = self::CACHE_PREFIX . 'to_provider:' . $canonicalName;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->mappingCache[$canonicalName] = $cached;
            return $cached;
        }
        
        // Validierung: Prüfe ob Tool existiert
        if ($this->registry) {
            if (!$this->registry->has($canonicalName)) {
                // Tool existiert nicht - verwende trotzdem Mapping (für Backwards-Kompatibilität)
                \Log::warning("[ToolNameMapper] Tool '{$canonicalName}' nicht in Registry gefunden", [
                    'canonical_name' => $canonicalName
                ]);
            }
        }
        
        // Mapping: Punkte → Unterstriche
        $providerName = str_replace('.', '_', $canonicalName);
        
        // Cache speichern
        $this->mappingCache[$canonicalName] = $providerName;
        Cache::put($cacheKey, $providerName, self::CACHE_TTL);
        
        return $providerName;
    }
    
    /**
     * Konvertiert provider name zu canonical name
     * 
     * @param string $providerName z.B. "planner_projects_create"
     * @return string z.B. "planner.projects.create"
     */
    public function toCanonical(string $providerName): string
    {
        // Cache prüfen
        if (isset($this->mappingCache[$providerName])) {
            return $this->mappingCache[$providerName];
        }
        
        // Persistent Cache prüfen
        $cacheKey = self::CACHE_PREFIX . 'to_canonical:' . $providerName;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $this->mappingCache[$providerName] = $cached;
            return $cached;
        }
        
        // Versuche zuerst, ob es ein Tool mit genau diesem Namen gibt (Backwards-Kompatibilität)
        if ($this->registry && $this->registry->has($providerName)) {
            $this->mappingCache[$providerName] = $providerName;
            Cache::put($cacheKey, $providerName, self::CACHE_TTL);
            return $providerName;
        }
        
        // Standard-Mapping: Unterstriche → Punkte
        $canonicalName = str_replace('_', '.', $providerName);
        
        // Validierung: Prüfe ob Tool existiert
        if ($this->registry) {
            if ($this->registry->has($canonicalName)) {
                // Tool existiert - Mapping ist korrekt
                $this->mappingCache[$providerName] = $canonicalName;
                Cache::put($cacheKey, $canonicalName, self::CACHE_TTL);
                return $canonicalName;
            } else {
                // Tool existiert nicht mit Standard-Mapping
                // LOOSE: Suche in allen registrierten Tools nach ähnlichen Namen
                $foundTool = $this->findSimilarToolName($providerName);
                if ($foundTool) {
                    // Gefunden! Cache speichern
                    $this->mappingCache[$providerName] = $foundTool;
                    Cache::put($cacheKey, $foundTool, self::CACHE_TTL);
                    \Log::info("[ToolNameMapper] Ähnliches Tool gefunden (LOOSE)", [
                        'provider_name' => $providerName,
                        'canonical_name' => $foundTool
                    ]);
                    return $foundTool;
                }
                
                // Kein ähnliches Tool gefunden - verwende trotzdem Standard-Mapping (für Backwards-Kompatibilität)
                \Log::warning("[ToolNameMapper] Tool '{$canonicalName}' nicht in Registry gefunden", [
                    'provider_name' => $providerName,
                    'canonical_name' => $canonicalName
                ]);
            }
        }
        
        // Cache speichern
        $this->mappingCache[$providerName] = $canonicalName;
        Cache::put($cacheKey, $canonicalName, self::CACHE_TTL);
        
        return $canonicalName;
    }
    
    /**
     * Gibt canonical name zurück (für Logging)
     * 
     * WICHTIG: Immer canonical name für Logging verwenden!
     */
    public function getCanonicalName(string $name): string
    {
        // Wenn bereits canonical (enthält Punkt), direkt zurückgeben
        if (str_contains($name, '.')) {
            return $name;
        }
        
        // Sonst konvertieren
        return $this->toCanonical($name);
    }
    
    /**
     * Findet ähnlichen Tool-Namen in der Registry (LOOSE)
     * 
     * Vergleicht normalisierte Namen: "planner_project_slots_GET" sollte "planner.project_slots.GET" finden
     * Funktioniert für alle Tool-Namen, ohne hardcoded Patterns
     */
    private function findSimilarToolName(string $providerName): ?string
    {
        if (!$this->registry) {
            return null;
        }
        
        // Normalisiere Provider-Name (entferne alle Trennzeichen für Vergleich)
        $normalizedProvider = $this->normalizeForComparison($providerName);
        
        $allTools = $this->registry->all();
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($allTools as $toolName => $tool) {
            // Normalisiere Tool-Name für Vergleich
            $normalizedTool = $this->normalizeForComparison($toolName);
            
            // Exakter Match nach Normalisierung?
            if ($normalizedProvider === $normalizedTool) {
                return $toolName;
            }
            
            // Ähnlichkeits-Score berechnen
            $score = $this->calculateSimilarity($normalizedProvider, $normalizedTool);
            if ($score > $bestScore && $score >= 0.8) { // Mindestens 80% Ähnlichkeit
                $bestScore = $score;
                $bestMatch = $toolName;
            }
        }
        
        return $bestMatch;
    }
    
    /**
     * Normalisiert Namen für Vergleich (entfernt Trennzeichen)
     * 
     * "planner.project_slots.GET" → "plannerprojectslotsGET"
     * "planner_project_slots_GET" → "plannerprojectslotsGET"
     */
    private function normalizeForComparison(string $name): string
    {
        // Entferne alle Trennzeichen (Punkte, Unterstriche)
        return strtolower(preg_replace('/[._-]/', '', $name));
    }
    
    /**
     * Berechnet Ähnlichkeit zwischen zwei normalisierten Namen
     * 
     * @return float 0.0 - 1.0 (1.0 = identisch)
     */
    private function calculateSimilarity(string $normalized1, string $normalized2): float
    {
        // Exakter Match
        if ($normalized1 === $normalized2) {
            return 1.0;
        }
        
        // Ein Name enthält den anderen?
        if (str_contains($normalized1, $normalized2) || str_contains($normalized2, $normalized1)) {
            $minLen = min(strlen($normalized1), strlen($normalized2));
            $maxLen = max(strlen($normalized1), strlen($normalized2));
            return $minLen / $maxLen; // Verhältnis der Längen
        }
        
        // Levenshtein-Distanz (vereinfacht)
        $len1 = strlen($normalized1);
        $len2 = strlen($normalized2);
        $maxLen = max($len1, $len2);
        
        if ($maxLen === 0) {
            return 1.0;
        }
        
        // Einfache Ähnlichkeit: gemeinsame Zeichen
        $common = 0;
        $shorter = $len1 < $len2 ? $normalized1 : $normalized2;
        $longer = $len1 >= $len2 ? $normalized1 : $normalized2;
        
        for ($i = 0; $i < strlen($shorter); $i++) {
            if (str_contains($longer, $shorter[$i])) {
                $common++;
            }
        }
        
        return $common / $maxLen;
    }
}

