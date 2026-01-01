<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolExecution;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Service zum Tracken von Tool-Ausführungen
 * 
 * Speichert alle Tool-Ausführungen in der Datenbank für Observability
 */
class ToolExecutionTracker
{
    /**
     * Trackt eine Tool-Ausführung
     */
    public function track(
        string $toolName,
        array $arguments,
        ToolContext $context,
        ToolResult $result,
        float $duration,
        int $memoryUsage,
        ?string $traceId = null,
        ?string $chainId = null,
        ?int $chainPosition = null,
        ?int $tokenUsageInput = null,
        ?int $tokenUsageOutput = null,
        int $retries = 0,
        ?string $errorType = null,
        ?array $metadata = null
    ): void {
        try {
            ToolExecution::create([
                'tool_name' => $toolName,
                'user_id' => $context->user?->id,
                'team_id' => $context->team?->id,
                'arguments' => $arguments,
                'success' => $result->success,
                'error_code' => $result->errorCode ?? null,
                'error_message' => $result->error ?? null,
                'error_trace' => $result->metadata['error_trace'] ?? null,
                'error_type' => $errorType,
                'duration_ms' => (int)($duration * 1000),
                'memory_usage_bytes' => $memoryUsage,
                'token_usage_input' => $tokenUsageInput,
                'token_usage_output' => $tokenUsageOutput,
                'result_data' => $result->data,
                'result_message' => $result->metadata['message'] ?? null,
                'trace_id' => $traceId,
                'chain_id' => $chainId,
                'chain_position' => $chainPosition,
                'retries' => $retries,
                'metadata' => $metadata ?? $result->metadata,
                'idempotency_key' => $metadata['idempotency_key'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Silent fail - Tracking sollte nicht die Tool-Ausführung blockieren
            \Log::warning('[ToolExecutionTracker] Fehler beim Tracken', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Trackt einen Fehler (ohne Result)
     */
    public function trackError(
        string $toolName,
        array $arguments,
        ToolContext $context,
        string $errorMessage,
        string $errorCode,
        ?\Throwable $exception = null,
        float $duration = 0,
        int $memoryUsage = 0,
        ?string $traceId = null,
        ?string $chainId = null,
        ?int $chainPosition = null,
        int $retries = 0,
        ?string $errorType = null,
        ?array $metadata = null
    ): void {
        try {
            // Kategorisiere Fehler-Typ wenn nicht angegeben
            if ($errorType === null && $exception !== null) {
                $errorType = $this->categorizeError($exception);
            }
            
            ToolExecution::create([
                'tool_name' => $toolName,
                'user_id' => $context->user?->id,
                'team_id' => $context->team?->id,
                'arguments' => $arguments,
                'success' => false,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'error_trace' => $exception ? $exception->getTraceAsString() : null,
                'error_type' => $errorType,
                'duration_ms' => (int)($duration * 1000),
                'memory_usage_bytes' => $memoryUsage,
                'trace_id' => $traceId,
                'chain_id' => $chainId,
                'chain_position' => $chainPosition,
                'retries' => $retries,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            // Silent fail
            \Log::warning('[ToolExecutionTracker] Fehler beim Tracken', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Kategorisiert Fehler-Typ für Observability
     */
    private function categorizeError(\Throwable $exception): string
    {
        // Authorization-Fehler
        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return 'authorization';
        }
        
        // Timeout-Fehler
        if (str_contains($exception->getMessage(), 'timeout') ||
            str_contains($exception->getMessage(), 'Timeout')) {
            return 'timeout';
        }
        
        // Rate Limit-Fehler
        if (str_contains($exception->getMessage(), 'rate limit') ||
            str_contains($exception->getMessage(), 'Rate Limit') ||
            str_contains($exception->getMessage(), 'RATE_LIMITED')) {
            return 'rate_limit';
        }
        
        // Circuit Breaker-Fehler
        if (str_contains($exception->getMessage(), 'circuit breaker') ||
            str_contains($exception->getMessage(), 'Circuit Breaker')) {
            return 'circuit_breaker';
        }
        
        // Validation-Fehler
        if (str_contains($exception->getMessage(), 'validation') ||
            str_contains($exception->getMessage(), 'Validation') ||
            str_contains($exception->getMessage(), 'VALIDATION')) {
            return 'validation';
        }
        
        // Standard: Execution-Fehler
        return 'execution';
    }
}

