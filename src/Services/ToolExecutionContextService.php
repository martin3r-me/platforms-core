<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\ToolContext;
use Illuminate\Support\Facades\Log;

/**
 * Service für Tool-Execution-Context
 * 
 * Verwaltet den Context während einer Tool-Ausführung für automatische Versionierung
 */
class ToolExecutionContextService
{
    /**
     * Aktive Contexts: trace_id => context_data
     */
    private array $activeContexts = [];

    /**
     * Startet einen Tool-Execution-Context
     */
    public function startContext(
        string $traceId,
        string $toolName,
        ToolContext $context
    ): void {
        $this->activeContexts[$traceId] = [
            'tool_name' => $toolName,
            'context' => $context,
            'tool_execution_id' => null,
            'started_at' => now(),
        ];
    }

    /**
     * Setzt die ToolExecution-ID für einen Context
     */
    public function setToolExecutionId(string $traceId, ?int $toolExecutionId): void
    {
        if (isset($this->activeContexts[$traceId])) {
            $this->activeContexts[$traceId]['tool_execution_id'] = $toolExecutionId;
        }
    }

    /**
     * Holt die ToolExecution-ID für einen Context
     */
    public function getToolExecutionId(string $traceId): ?int
    {
        return $this->activeContexts[$traceId]['tool_execution_id'] ?? null;
    }

    /**
     * Prüft, ob ein Context aktiv ist
     */
    public function hasContext(string $traceId): bool
    {
        return isset($this->activeContexts[$traceId]);
    }

    /**
     * Holt den Context für eine Trace-ID
     */
    public function getContext(string $traceId): ?array
    {
        return $this->activeContexts[$traceId] ?? null;
    }

    /**
     * Beendet einen Tool-Execution-Context
     */
    public function endContext(string $traceId, ?int $toolExecutionId = null): void
    {
        if (isset($this->activeContexts[$traceId])) {
            if ($toolExecutionId !== null) {
                $this->activeContexts[$traceId]['tool_execution_id'] = $toolExecutionId;
            }
            // Context bleibt kurz erhalten, damit Event-Listener noch darauf zugreifen können
            // Wird später automatisch aufgeräumt (z.B. nach 5 Minuten)
            unset($this->activeContexts[$traceId]);
        }
    }

    /**
     * Holt alle aktiven Contexts
     */
    public function getAllContexts(): array
    {
        return $this->activeContexts;
    }

    /**
     * Räumt alte Contexts auf
     */
    public function cleanup(): void
    {
        $now = now();
        foreach ($this->activeContexts as $traceId => $context) {
            if ($context['started_at']->diffInMinutes($now) > 5) {
                unset($this->activeContexts[$traceId]);
            }
        }
    }
}

