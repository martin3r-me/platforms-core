<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolCircuitBreakerState;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker Pattern für externe Services
 * 
 * Verhindert, dass fehlerhafte Services das System überlasten
 */
class ToolCircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    /**
     * Führt eine Operation mit Circuit Breaker aus
     * 
     * @param string $serviceName Name des Services (z.B. 'openai')
     * @param callable $operation Callback, der die Operation ausführt
     * @return mixed Ergebnis der Operation oder null bei geöffnetem Circuit
     */
    public function execute(string $serviceName, callable $operation)
    {
        $state = $this->getState($serviceName);

        // Prüfe Circuit State
        if ($state->isOpen()) {
            // Prüfe ob Timeout abgelaufen ist (Half-Open testen)
            if ($this->shouldAttemptHalfOpen($state)) {
                $state = $this->transitionToHalfOpen($serviceName);
            } else {
                // Circuit ist offen, gebe Fehler zurück
                Log::warning("[CircuitBreaker] Circuit ist offen für Service", [
                    'service' => $serviceName,
                    'state' => $state->state,
                    'opened_at' => $state->opened_at,
                ]);
                throw new \RuntimeException("Circuit Breaker ist offen für Service: {$serviceName}");
            }
        }

        // Führe Operation aus
        try {
            $result = $operation();

            // Erfolg: Reset Failure-Count, prüfe ob Half-Open → Closed
            $this->recordSuccess($serviceName, $state);

            return $result;
        } catch (\Throwable $e) {
            // Fehler: Erhöhe Failure-Count, prüfe ob Closed → Open
            $this->recordFailure($serviceName, $state);

            throw $e;
        }
    }

    /**
     * Holt oder erstellt Circuit Breaker State
     */
    private function getState(string $serviceName): ToolCircuitBreakerState
    {
        return ToolCircuitBreakerState::firstOrCreate(
            ['service_name' => $serviceName],
            [
                'state' => self::STATE_CLOSED,
                'failure_count' => 0,
                'success_count' => 0,
            ]
        );
    }

    /**
     * Prüft ob Half-Open versucht werden sollte
     */
    private function shouldAttemptHalfOpen(ToolCircuitBreakerState $state): bool
    {
        if (!$state->opened_at) {
            return false;
        }

        $timeout = config('tools.circuit_breaker.timeout_seconds', 60);
        $elapsed = now()->diffInSeconds($state->opened_at);

        return $elapsed >= $timeout;
    }

    /**
     * Wechselt zu Half-Open State
     */
    private function transitionToHalfOpen(string $serviceName): ToolCircuitBreakerState
    {
        $state = $this->getState($serviceName);
        $state->update([
            'state' => self::STATE_HALF_OPEN,
            'success_count' => 0, // Reset für Half-Open Test
        ]);

        Log::info("[CircuitBreaker] Transition zu Half-Open", [
            'service' => $serviceName,
        ]);

        return $state->fresh();
    }

    /**
     * Zeichnet Erfolg auf
     */
    private function recordSuccess(string $serviceName, ToolCircuitBreakerState $state): void
    {
        $state->increment('success_count');
        $state->update(['last_success_at' => now()]);

        // Wenn Half-Open und genug Erfolge → Closed
        if ($state->isHalfOpen()) {
            $successThreshold = config('tools.circuit_breaker.success_threshold', 2);
            if ($state->success_count >= $successThreshold) {
                $state->update([
                    'state' => self::STATE_CLOSED,
                    'failure_count' => 0,
                    'opened_at' => null,
                ]);

                Log::info("[CircuitBreaker] Circuit geschlossen nach Erfolgen", [
                    'service' => $serviceName,
                    'success_count' => $state->success_count,
                ]);
            }
        } else {
            // Wenn Closed: Reset Failure-Count nach mehreren Erfolgen
            if ($state->failure_count > 0 && $state->success_count >= 5) {
                $state->update(['failure_count' => 0]);
            }
        }
    }

    /**
     * Zeichnet Fehler auf
     */
    private function recordFailure(string $serviceName, ToolCircuitBreakerState $state): void
    {
        $state->increment('failure_count');
        $state->update(['last_failure_at' => now()]);

        // Prüfe ob Circuit geöffnet werden sollte
        $failureThreshold = config('tools.circuit_breaker.failure_threshold', 5);
        
        if ($state->failure_count >= $failureThreshold && $state->isClosed()) {
            // Öffne Circuit
            $state->update([
                'state' => self::STATE_OPEN,
                'opened_at' => now(),
            ]);

            Log::warning("[CircuitBreaker] Circuit geöffnet wegen Fehlern", [
                'service' => $serviceName,
                'failure_count' => $state->failure_count,
            ]);
        } elseif ($state->isHalfOpen()) {
            // Half-Open: Bei Fehler direkt wieder öffnen
            $state->update([
                'state' => self::STATE_OPEN,
                'opened_at' => now(),
                'success_count' => 0,
            ]);

            Log::warning("[CircuitBreaker] Circuit wieder geöffnet (Half-Open Test fehlgeschlagen)", [
                'service' => $serviceName,
            ]);
        }
    }

    /**
     * Prüft ob Circuit für Service offen ist
     */
    public function isOpen(string $serviceName): bool
    {
        $state = $this->getState($serviceName);
        
        if ($state->isOpen() && !$this->shouldAttemptHalfOpen($state)) {
            return true;
        }

        return false;
    }

    /**
     * Manuell Circuit zurücksetzen (für Admin)
     */
    public function reset(string $serviceName): void
    {
        $state = $this->getState($serviceName);
        $state->update([
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'success_count' => 0,
            'opened_at' => null,
        ]);

        Log::info("[CircuitBreaker] Circuit manuell zurückgesetzt", [
            'service' => $serviceName,
        ]);
    }
}

