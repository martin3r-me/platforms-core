<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * Service für Retry-Mechanismus mit Exponential Backoff
 * 
 * Wiederholt fehlgeschlagene Tool-Ausführungen mit konfigurierbarer Retry-Policy
 */
class ToolRetryService
{
    /**
     * Führt ein Tool mit Retry-Mechanismus aus
     * 
     * @param callable $executor Callback, der das Tool ausführt: fn() => ToolResult
     * @param string $toolName Name des Tools (für Logging)
     * @param array $options Retry-Optionen (max_attempts, backoff_strategy, etc.)
     * @return ToolResult Ergebnis der Ausführung
     */
    public function executeWithRetry(
        callable $executor,
        string $toolName,
        array $options = []
    ): ToolResult {
        $maxAttempts = $options['max_attempts'] ?? config('tools.retry.max_attempts', 3);
        $backoffStrategy = $options['backoff_strategy'] ?? config('tools.retry.backoff_strategy', 'exponential');
        $initialDelay = $options['initial_delay_ms'] ?? config('tools.retry.initial_delay_ms', 100);
        $maxDelay = $options['max_delay_ms'] ?? config('tools.retry.max_delay_ms', 5000);

        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $result = $executor();

                // Wenn erfolgreich, direkt zurückgeben
                if ($result->success) {
                    if ($attempt > 1) {
                        Log::info("[ToolRetry] Tool erfolgreich nach {$attempt} Versuchen", [
                            'tool' => $toolName,
                            'attempts' => $attempt,
                        ]);
                    }
                    return $result;
                }

                // Prüfe ob Fehler retryable ist
                if (!$this->isRetryableError($result->errorCode ?? 'UNKNOWN')) {
                    Log::info("[ToolRetry] Fehler nicht retryable, gebe auf", [
                        'tool' => $toolName,
                        'error_code' => $result->errorCode,
                        'attempt' => $attempt,
                    ]);
                    return $result;
                }

                $lastError = $result;

                // Wenn letzter Versuch, gebe Fehler zurück
                if ($attempt >= $maxAttempts) {
                    Log::warning("[ToolRetry] Max Versuche erreicht", [
                        'tool' => $toolName,
                        'attempts' => $attempt,
                        'error_code' => $result->errorCode,
                    ]);
                    return $result;
                }

                // Berechne Delay für nächsten Versuch
                $delay = $this->calculateDelay($attempt, $backoffStrategy, $initialDelay, $maxDelay);

                Log::info("[ToolRetry] Retry nach {$delay}ms", [
                    'tool' => $toolName,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'delay_ms' => $delay,
                ]);

                // Warte vor nächstem Versuch
                usleep($delay * 1000); // usleep erwartet Mikrosekunden

            } catch (\Throwable $e) {
                // Exception während Ausführung
                if (!$this->isRetryableException($e)) {
                    // Nicht retryable, gebe Fehler zurück
                    return ToolResult::error(
                        'Tool-Ausführung fehlgeschlagen: ' . $e->getMessage(),
                        'EXECUTION_ERROR'
                    );
                }

                $lastError = ToolResult::error(
                    'Tool-Ausführung fehlgeschlagen: ' . $e->getMessage(),
                    'EXECUTION_ERROR'
                );

                // Wenn letzter Versuch, gebe Fehler zurück
                if ($attempt >= $maxAttempts) {
                    return $lastError;
                }

                // Berechne Delay
                $delay = $this->calculateDelay($attempt, $backoffStrategy, $initialDelay, $maxDelay);
                usleep($delay * 1000);
            }
        }

        // Sollte nie hier ankommen, aber Fallback
        return $lastError ?? ToolResult::error('Unbekannter Fehler', 'UNKNOWN_ERROR');
    }

    /**
     * Prüft ob ein Fehler retryable ist
     */
    private function isRetryableError(string $errorCode): bool
    {
        // Retryable: Network-Fehler, Timeouts, Rate-Limits, Server-Fehler
        $retryableCodes = [
            'NETWORK_ERROR',
            'TIMEOUT',
            'RATE_LIMITED',
            'SERVER_ERROR',
            'DEPENDENCY_FAILED',
            'INTERNAL_ERROR', // Kann transient sein
        ];

        return in_array($errorCode, $retryableCodes);
    }

    /**
     * Prüft ob eine Exception retryable ist
     */
    private function isRetryableException(\Throwable $e): bool
    {
        // Network-Exceptions sind retryable
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        // Timeout-Exceptions sind retryable
        if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'Timeout')) {
            return true;
        }

        // Rate-Limit-Exceptions sind retryable
        if (str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'Rate limit')) {
            return true;
        }

        // Validation-Errors sind NICHT retryable
        if (str_contains($e->getMessage(), 'validation') || str_contains($e->getMessage(), 'Validation')) {
            return false;
        }

        // Auth-Errors sind NICHT retryable
        if (str_contains($e->getMessage(), 'auth') || str_contains($e->getMessage(), 'unauthorized')) {
            return false;
        }

        // Default: Nicht retryable (sicherer)
        return false;
    }

    /**
     * Berechnet Delay für nächsten Retry
     */
    private function calculateDelay(
        int $attempt,
        string $strategy,
        int $initialDelay,
        int $maxDelay
    ): int {
        return match ($strategy) {
            'exponential' => min($initialDelay * (2 ** ($attempt - 1)), $maxDelay),
            'linear' => min($initialDelay * $attempt, $maxDelay),
            'fixed' => $initialDelay,
            default => min($initialDelay * (2 ** ($attempt - 1)), $maxDelay), // Default: exponential
        };
    }
}

