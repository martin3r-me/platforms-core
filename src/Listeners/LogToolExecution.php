<?php

namespace Platform\Core\Listeners;

use Platform\Core\Events\ToolExecuted;
use Platform\Core\Events\ToolFailed;
use Platform\Core\Services\ToolExecutionTracker;
use Illuminate\Support\Facades\Log;

/**
 * Listener: Loggt alle Tool-Ausführungen und tracked sie in der DB
 */
class LogToolExecution
{
    public function __construct(
        private ToolExecutionTracker $tracker
    ) {}

    /**
     * Handle ToolExecuted event
     */
    public function handleToolExecuted(ToolExecuted $event): void
    {
        // Logge in Laravel Log
        Log::info('[Tool Execution] Tool erfolgreich ausgeführt', [
            'tool' => $event->toolName,
            'duration_ms' => (int)($event->duration * 1000),
            'memory_mb' => round($event->memoryUsage / 1024 / 1024, 2),
            'success' => $event->result->success,
            'trace_id' => $event->traceId,
            'user_id' => $event->context->user?->id,
            'team_id' => $event->context->team?->id,
        ]);

        // Tracke in Datenbank
        $execution = $this->tracker->track(
            $event->toolName,
            $event->arguments,
            $event->context,
            $event->result,
            $event->duration,
            $event->memoryUsage,
            $event->traceId,
            null, // chain_id (wird später von ToolOrchestrator gesetzt)
            null, // chain_position
            $event->result->metadata['token_usage_input'] ?? null,
            $event->result->metadata['token_usage_output'] ?? null,
            $event->retries,
            null, // error_type (nur bei Fehlern)
            array_merge($event->result->metadata ?? [], [
                'cache_hit' => $event->cacheHit,
                'idempotency_key' => $event->idempotencyKey,
            ])
        );
        
        // Speichere Idempotency-Key in IdempotencyService (wenn verfügbar)
        if ($event->idempotencyKey && $execution) {
            try {
                $idempotencyService = app(\Platform\Core\Services\ToolIdempotencyService::class);
                $idempotencyService->storeKey($event->idempotencyKey, $execution->id);
            } catch (\Throwable $e) {
                // Silent fail
            }
        }
    }

    /**
     * Handle ToolFailed event
     */
    public function handleToolFailed(ToolFailed $event): void
    {
        // Logge in Laravel Log
        Log::error('[Tool Execution] Tool fehlgeschlagen', [
            'tool' => $event->toolName,
            'error' => $event->errorMessage,
            'error_code' => $event->errorCode,
            'duration_ms' => (int)($event->duration * 1000),
            'memory_mb' => round($event->memoryUsage / 1024 / 1024, 2),
            'trace_id' => $event->traceId,
            'user_id' => $event->context->user?->id,
            'team_id' => $event->context->team?->id,
            'exception' => $event->exception ? [
                'message' => $event->exception->getMessage(),
                'file' => $event->exception->getFile(),
                'line' => $event->exception->getLine(),
            ] : null,
        ]);

        // Tracke in Datenbank
        $this->tracker->trackError(
            $event->toolName,
            $event->arguments,
            $event->context,
            $event->errorMessage,
            $event->errorCode,
            $event->exception,
            $event->duration,
            $event->memoryUsage,
            $event->traceId,
            null, // chain_id
            null, // chain_position
            $event->retries,
            $event->errorType
        );
    }
}

