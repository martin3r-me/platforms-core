<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolExecution;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tool Idempotency Service
 * 
 * Verwaltet Idempotency-Keys für Tool-Ausführungen:
 * - Generiert idempotency_key pro Tool-Run
 * - Prüft auf Duplikate (gleicher Key + Tool + Args)
 * - Retry nur bei idempotenten Tools
 */
class ToolIdempotencyService
{
    private const CACHE_PREFIX = 'tool_idempotency:';
    private const CACHE_TTL = 86400; // 24 Stunden
    
    /**
     * Generiert einen Idempotency-Key für einen Tool-Run
     */
    public function generateKey(string $toolName, array $arguments, ToolContext $context): string
    {
        // Normalisiere Arguments (sortiert, entfernt null-Werte)
        $normalizedArgs = $this->normalizeArguments($arguments);
        
        $keyData = [
            'tool' => $toolName,
            'args' => $normalizedArgs,
            'user_id' => $context->user?->id,
            'team_id' => $context->team?->id,
        ];
        
        return hash('sha256', json_encode($keyData));
    }
    
    /**
     * Normalisiert Argumente für Idempotency-Key (sortiert, entfernt null-Werte)
     */
    private function normalizeArguments(array $arguments): array
    {
        // Sortiere nach Keys
        ksort($arguments);
        
        // Entferne null-Werte (können variieren, aber sollten nicht den Key beeinflussen)
        $normalized = [];
        foreach ($arguments as $key => $value) {
            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Prüft ob bereits ein Tool-Run mit diesem Idempotency-Key existiert
     * 
     * @return ToolExecution|null Das bereits existierende Execution oder null
     */
    public function checkDuplicate(string $idempotencyKey): ?ToolExecution
    {
        // Cache prüfen (schneller)
        $cacheKey = self::CACHE_PREFIX . $idempotencyKey;
        $cachedExecutionId = Cache::get($cacheKey);
        
        if ($cachedExecutionId !== null) {
            $execution = ToolExecution::find($cachedExecutionId);
            if ($execution && $execution->success) {
                Log::info("[ToolIdempotency] Duplikat gefunden (Cache)", [
                    'idempotency_key' => substr($idempotencyKey, 0, 16) . '...',
                    'execution_id' => $execution->id,
                ]);
                return $execution;
            }
        }
        
        // Datenbank prüfen (langsamer, aber zuverlässiger)
        // Prüfe nur in den letzten 24 Stunden (Performance)
        $execution = ToolExecution::where('metadata->idempotency_key', $idempotencyKey)
            ->where('created_at', '>=', now()->subDay())
            ->where('success', true)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if ($execution) {
            // Cache speichern
            Cache::put($cacheKey, $execution->id, self::CACHE_TTL);
            
            Log::info("[ToolIdempotency] Duplikat gefunden (DB)", [
                'idempotency_key' => substr($idempotencyKey, 0, 16) . '...',
                'execution_id' => $execution->id,
            ]);
            
            return $execution;
        }
        
        return null;
    }
    
    /**
     * Speichert Idempotency-Key für einen Tool-Run
     */
    public function storeKey(string $idempotencyKey, int $executionId): void
    {
        $cacheKey = self::CACHE_PREFIX . $idempotencyKey;
        Cache::put($cacheKey, $executionId, self::CACHE_TTL);
    }
}

