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
        ?int $tokenUsageOutput = null
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
                'duration_ms' => (int)($duration * 1000),
                'memory_usage_bytes' => $memoryUsage,
                'token_usage_input' => $tokenUsageInput,
                'token_usage_output' => $tokenUsageOutput,
                'result_data' => $result->data,
                'result_message' => $result->metadata['message'] ?? null,
                'trace_id' => $traceId,
                'chain_id' => $chainId,
                'chain_position' => $chainPosition,
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
        ?int $chainPosition = null
    ): void {
        try {
            ToolExecution::create([
                'tool_name' => $toolName,
                'user_id' => $context->user?->id,
                'team_id' => $context->team?->id,
                'arguments' => $arguments,
                'success' => false,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'error_trace' => $exception ? $exception->getTraceAsString() : null,
                'duration_ms' => (int)($duration * 1000),
                'memory_usage_bytes' => $memoryUsage,
                'trace_id' => $traceId,
                'chain_id' => $chainId,
                'chain_position' => $chainPosition,
            ]);
        } catch (\Throwable $e) {
            // Silent fail
            \Log::warning('[ToolExecutionTracker] Fehler beim Tracken', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

