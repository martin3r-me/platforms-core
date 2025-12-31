<?php

namespace Platform\Core\Listeners;

use Platform\Core\Events\ToolExecuted;
use Platform\Core\Events\ToolFailed;
use Platform\Core\Services\ToolMetricsService;
use Illuminate\Support\Facades\Log;

/**
 * Listener: Trackt Metriken für Tool-Ausführungen
 */
class TrackToolMetrics
{
    public function __construct()
    {
        // Lazy-Loading: Service wird nur verwendet, wenn verfügbar
    }
    
    private function getMetricsService(): ?ToolMetricsService
    {
        try {
            return app(ToolMetricsService::class);
        } catch (\Throwable $e) {
            // Service noch nicht verfügbar - wird später implementiert
            return null;
        }
    }

    /**
     * Handle ToolExecuted event
     */
    public function handleToolExecuted(ToolExecuted $event): void
    {
        $service = $this->getMetricsService();
        if ($service) {
            try {
                $service->recordExecution(
                    $event->toolName,
                    true,
                    $event->duration,
                    $event->memoryUsage,
                    $event->context->user?->id,
                    $event->context->team?->id
                );
            } catch (\Throwable $e) {
                // Silent fail - Metrics sollten nicht die Tool-Ausführung blockieren
                Log::warning('[TrackToolMetrics] Fehler beim Aufzeichnen', [
                    'tool' => $event->toolName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle ToolFailed event
     */
    public function handleToolFailed(ToolFailed $event): void
    {
        $service = $this->getMetricsService();
        if ($service) {
            try {
                $service->recordExecution(
                    $event->toolName,
                    false,
                    $event->duration,
                    $event->memoryUsage,
                    $event->context->user?->id,
                    $event->context->team?->id,
                    $event->errorCode
                );
            } catch (\Throwable $e) {
                // Silent fail
                Log::warning('[TrackToolMetrics] Fehler beim Aufzeichnen', [
                    'tool' => $event->toolName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

