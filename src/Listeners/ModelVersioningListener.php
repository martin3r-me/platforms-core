<?php

namespace Platform\Core\Listeners;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Services\ToolExecutionContextService;
use Platform\Core\Services\ModelVersioningService;
use Illuminate\Support\Facades\Log;

/**
 * Listener für automatische Model-Versionierung während Tool-Ausführungen
 * 
 * Erstellt automatisch Versionen, wenn Models während einer Tool-Ausführung geändert werden
 */
class ModelVersioningListener
{
    public function __construct(
        private ToolExecutionContextService $contextService,
        private ?ModelVersioningService $versioningService = null
    ) {
        // Lazy-Load VersioningService
        if ($this->versioningService === null) {
            try {
                $this->versioningService = app(ModelVersioningService::class);
            } catch (\Throwable $e) {
                $this->versioningService = null;
            }
        }
    }

    /**
     * Handle Model Created Event (Eloquent Event)
     */
    public function handleCreated($event): void
    {
        $model = $event instanceof Model ? $event : $event->model ?? null;
        if ($model instanceof Model) {
            $this->createVersionForModel($model, 'created');
        }
    }

    /**
     * Handle Model Updated Event (Eloquent Event)
     */
    public function handleUpdated($event): void
    {
        $model = $event instanceof Model ? $event : $event->model ?? null;
        if ($model instanceof Model) {
            $this->createVersionForModel($model, 'updated');
        }
    }

    /**
     * Handle Model Deleted Event (Eloquent Event)
     */
    public function handleDeleted($event): void
    {
        $model = $event instanceof Model ? $event : $event->model ?? null;
        if ($model instanceof Model) {
            $this->createVersionForModel($model, 'deleted');
        }
    }

    /**
     * Erstellt eine Version für ein Model, wenn wir in einem Tool-Context sind
     */
    protected function createVersionForModel(Model $model, string $operation): void
    {
        // Prüfe, ob Versionierung aktiviert ist
        if (!$this->versioningService) {
            return;
        }

        // Finde aktiven Tool-Context
        $activeContext = $this->findActiveContext();
        if (!$activeContext) {
            // Nicht in einem Tool-Context - keine Versionierung
            return;
        }

        try {
            $traceId = $activeContext['trace_id'];
            $toolName = $activeContext['tool_name'];
            $context = $activeContext['context'];
            $toolExecutionId = $activeContext['tool_execution_id'];

            // Erstelle Version
            match ($operation) {
                'created' => $this->versioningService->createVersionForCreate(
                    $model,
                    $toolName,
                    $traceId,
                    $activeContext['chain_id'] ?? null,
                    $toolExecutionId,
                    $context,
                    $this->createReason($operation, $model, $toolName)
                ),
                'updated' => $this->handleModelUpdated($model, $toolName, $traceId, $activeContext, $context, $toolExecutionId),
                'deleted' => $this->versioningService->createVersionForDelete(
                    $model,
                    $toolName,
                    $traceId,
                    $activeContext['chain_id'] ?? null,
                    $toolExecutionId,
                    $context,
                    $this->createReason($operation, $model, $toolName)
                ),
            };
        } catch (\Throwable $e) {
            // Silent fail - Versionierung sollte nicht die Tool-Ausführung blockieren
            Log::warning('[ModelVersioningListener] Fehler bei Versionierung', [
                'model' => get_class($model),
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Behandelt Model-Update (braucht Snapshot vorher)
     */
    protected function handleModelUpdated(
        Model $model,
        string $toolName,
        string $traceId,
        array $activeContext,
        $context,
        ?int $toolExecutionId
    ): void {
        // Für Updates brauchen wir einen Snapshot VOR der Änderung
        // Das ist schwierig, da das Event NACH der Änderung feuert
        // Lösung: Wir speichern den aktuellen Zustand als "nachher" und
        // versuchen, die "vorher"-Version aus der letzten Version zu holen
        
        // Erstelle Version mit aktuellem Zustand als "nachher"
        $version = $this->versioningService->createVersionBeforeUpdate(
            $model,
            'updated',
            $toolName,
            $traceId,
            $activeContext['chain_id'] ?? null,
            $toolExecutionId,
            $context,
            $this->createReason('updated', $model, $toolName)
        );

        // Aktualisiere mit "nachher"-Snapshot
        $this->versioningService->updateVersionAfterUpdate($version, $model, []);
    }

    /**
     * Findet aktiven Tool-Context
     */
    protected function findActiveContext(): ?array
    {
        // Durchsuche alle aktiven Contexts (nehme den neuesten)
        $allContexts = $this->contextService->getAllContexts();
        if (empty($allContexts)) {
            return null;
        }

        // Nehme den neuesten Context (zuletzt gestartet)
        $latestContext = null;
        $latestTime = null;
        foreach ($allContexts as $traceId => $contextData) {
            $startedAt = $contextData['started_at'] ?? now();
            if ($latestTime === null || $startedAt->isAfter($latestTime)) {
                $latestTime = $startedAt;
                $latestContext = [
                    'trace_id' => $traceId,
                    'tool_name' => $contextData['tool_name'] ?? null,
                    'context' => $contextData['context'] ?? null,
                    'tool_execution_id' => $contextData['tool_execution_id'] ?? null,
                    'chain_id' => $contextData['chain_id'] ?? null,
                ];
            }
        }

        return $latestContext;
    }

    /**
     * Erstellt einen Reason-String
     */
    protected function createReason(string $operation, Model $model, string $toolName): string
    {
        $modelName = class_basename(get_class($model));
        return sprintf('LLM (%s): %s %s (ID: %d)', $toolName, ucfirst($operation), $modelName, $model->id ?? 'new');
    }
}

