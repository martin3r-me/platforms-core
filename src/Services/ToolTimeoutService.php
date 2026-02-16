<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service für Timeout-Management
 * 
 * Verwaltet Timeouts für Tool-Ausführungen
 */
class ToolTimeoutService
{
    /**
     * Führt eine Operation mit Timeout aus
     * 
     * @param callable $operation Callback, der die Operation ausführt
     * @param int $timeoutSeconds Timeout in Sekunden
     * @param string $toolName Name des Tools (für Logging)
     * @return mixed Ergebnis der Operation
     * @throws \RuntimeException Bei Timeout
     */
    public function executeWithTimeout(
        callable $operation,
        int $timeoutSeconds,
        string $toolName = 'unknown'
    ) {
        $start = microtime(true);
        
        // PHP hat kein natives Timeout für Callbacks
        // Wir nutzen set_time_limit als Fallback
        $previousTimeLimit = ini_get('max_execution_time');
        
        try {
            // Setze Timeout (mit Puffer)
            set_time_limit($timeoutSeconds + 5);
            
            $result = $operation();
            
            $duration = microtime(true) - $start;
            
            // Prüfe ob Timeout fast erreicht wurde
            if ($duration > ($timeoutSeconds * 0.9)) {
                Log::warning("[ToolTimeout] Timeout fast erreicht", [
                    'tool' => $toolName,
                    'duration' => $duration,
                    'timeout' => $timeoutSeconds,
                ]);
            }
            
            return $result;
        } catch (\Throwable $e) {
            // Prüfe ob es ein Timeout-Fehler ist
            if (str_contains($e->getMessage(), 'Maximum execution time') || 
                str_contains($e->getMessage(), 'timeout')) {
                Log::error("[ToolTimeout] Timeout erreicht", [
                    'tool' => $toolName,
                    'timeout' => $timeoutSeconds,
                ]);
                
                throw new \RuntimeException("Tool-Ausführung hat Timeout erreicht ({$timeoutSeconds}s)", 0, $e);
            }
            
            throw $e;
        } finally {
            // Stelle vorherigen Timeout wieder her
            if ($previousTimeLimit !== false) {
                set_time_limit($previousTimeLimit);
            }
        }
    }

    /**
     * Holt Timeout für ein Tool (aus Config)
     */
    public function getTimeoutForTool(string $toolName): int
    {
        // Prüfe per-Tool Config
        $toolTimeout = config("tools.tools.{$toolName}.timeout", null);
        if ($toolTimeout !== null) {
            return (int)$toolTimeout;
        }

        // Default aus Config
        return (int)config('tools.timeout.default_timeout_seconds', 30);
    }

    /**
     * Prüft ob Timeout-Management aktiviert ist
     */
    public function isEnabled(): bool
    {
        return (bool)config('tools.timeout.enabled', true);
    }

    /**
     * Gibt die konfigurierte Session-TTL in Sekunden zurück
     */
    public function getSessionTtl(): int
    {
        return (int) config('tools.mcp.session_ttl_seconds', 28800);
    }

    /**
     * Gibt die Session-Warning-Schwelle in Sekunden zurück
     */
    public function getSessionWarningThreshold(): int
    {
        return (int) config('tools.mcp.session_warning_seconds', 1800);
    }
}

