<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ModelVersion;
use Platform\Core\Models\UndoOperation;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service für Undo/Redo-Operationen
 * 
 * Macht Änderungen rückgängig oder wiederholt sie
 */
class UndoService
{
    public function __construct(
        private ModelVersioningService $versioningService
    ) {}

    /**
     * Macht eine Änderung rückgängig (Undo)
     */
    public function undo(
        ModelVersion $version,
        ?ToolContext $context = null,
        ?string $traceId = null
    ): UndoOperation {
        $operation = UndoOperation::create([
            'operation_type' => 'undo',
            'status' => 'pending',
            'model_version_id' => $version->id,
            'user_id' => $context?->user?->id,
            'team_id' => $context?->team?->id,
            'trace_id' => $traceId,
            'success' => false,
        ]);

        try {
            DB::beginTransaction();

            $result = match ($version->operation) {
                'created' => $this->undoCreate($version),
                'updated' => $this->undoUpdate($version),
                'deleted' => $this->undoDelete($version),
                default => throw new \Exception("Unsupported operation: {$version->operation}"),
            };

            $operation->update([
                'status' => 'completed',
                'success' => true,
                'result_data' => $result,
            ]);

            DB::commit();

            Log::info('[UndoService] Undo erfolgreich', [
                'version_id' => $version->id,
                'operation' => $version->operation,
                'model' => $version->versionable_type,
            ]);

            return $operation->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            $operation->update([
                'status' => 'failed',
                'success' => false,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('[UndoService] Undo fehlgeschlagen', [
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Wiederholt eine Änderung (Redo)
     */
    public function redo(
        ModelVersion $version,
        ?ToolContext $context = null,
        ?string $traceId = null
    ): UndoOperation {
        $operation = UndoOperation::create([
            'operation_type' => 'redo',
            'status' => 'pending',
            'model_version_id' => $version->id,
            'user_id' => $context?->user?->id,
            'team_id' => $context?->team?->id,
            'trace_id' => $traceId,
            'success' => false,
        ]);

        try {
            DB::beginTransaction();

            $result = match ($version->operation) {
                'created' => $this->redoCreate($version),
                'updated' => $this->redoUpdate($version),
                'deleted' => $this->redoDelete($version),
                default => throw new \Exception("Unsupported operation: {$version->operation}"),
            };

            $operation->update([
                'status' => 'completed',
                'success' => true,
                'result_data' => $result,
            ]);

            DB::commit();

            Log::info('[UndoService] Redo erfolgreich', [
                'version_id' => $version->id,
                'operation' => $version->operation,
            ]);

            return $operation->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            $operation->update([
                'status' => 'failed',
                'success' => false,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('[UndoService] Redo fehlgeschlagen', [
                'version_id' => $version->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Undo: Create rückgängig machen
     */
    protected function undoCreate(ModelVersion $version): array
    {
        $model = $version->versionable;
        if (!$model) {
            throw new \Exception("Model not found for version {$version->id}");
        }

        $modelId = $model->id;
        $model->delete();

        return [
            'action' => 'deleted',
            'model_id' => $modelId,
            'model_type' => $version->versionable_type,
        ];
    }

    /**
     * Undo: Update rückgängig machen
     */
    protected function undoUpdate(ModelVersion $version): array
    {
        $model = $version->versionable;
        if (!$model) {
            throw new \Exception("Model not found for version {$version->id}");
        }

        $snapshotBefore = $version->snapshot_before;
        if (!$snapshotBefore || !isset($snapshotBefore['attributes'])) {
            throw new \Exception("No snapshot_before found for version {$version->id}");
        }

        // Stelle vorherigen Zustand wieder her
        $model->fill($snapshotBefore['attributes']);
        $model->save();

        return [
            'action' => 'restored',
            'model_id' => $model->id,
            'model_type' => $version->versionable_type,
        ];
    }

    /**
     * Undo: Delete rückgängig machen
     */
    protected function undoDelete(ModelVersion $version): array
    {
        $snapshotBefore = $version->snapshot_before;
        if (!$snapshotBefore || !isset($snapshotBefore['attributes'])) {
            throw new \Exception("No snapshot_before found for version {$version->id}");
        }

        // Erstelle Model neu
        $modelClass = $version->versionable_type;
        $model = new $modelClass();
        $model->fill($snapshotBefore['attributes']);
        $model->save();

        return [
            'action' => 'restored',
            'model_id' => $model->id,
            'model_type' => $version->versionable_type,
        ];
    }

    /**
     * Redo: Create wiederholen
     */
    protected function redoCreate(ModelVersion $version): array
    {
        $snapshotAfter = $version->snapshot_after;
        if (!$snapshotAfter || !isset($snapshotAfter['attributes'])) {
            throw new \Exception("No snapshot_after found for version {$version->id}");
        }

        $modelClass = $version->versionable_type;
        $model = new $modelClass();
        $model->fill($snapshotAfter['attributes']);
        $model->save();

        return [
            'action' => 'created',
            'model_id' => $model->id,
            'model_type' => $version->versionable_type,
        ];
    }

    /**
     * Redo: Update wiederholen
     */
    protected function redoUpdate(ModelVersion $version): array
    {
        $model = $version->versionable;
        if (!$model) {
            throw new \Exception("Model not found for version {$version->id}");
        }

        $snapshotAfter = $version->snapshot_after;
        if (!$snapshotAfter || !isset($snapshotAfter['attributes'])) {
            throw new \Exception("No snapshot_after found for version {$version->id}");
        }

        // Stelle nachherigen Zustand wieder her
        $model->fill($snapshotAfter['attributes']);
        $model->save();

        return [
            'action' => 'updated',
            'model_id' => $model->id,
            'model_type' => $version->versionable_type,
        ];
    }

    /**
     * Redo: Delete wiederholen
     */
    protected function redoDelete(ModelVersion $version): array
    {
        $model = $version->versionable;
        if (!$model) {
            throw new \Exception("Model not found for version {$version->id}");
        }

        $modelId = $model->id;
        $model->delete();

        return [
            'action' => 'deleted',
            'model_id' => $modelId,
            'model_type' => $version->versionable_type,
        ];
    }
}

