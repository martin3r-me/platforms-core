<?php

namespace Platform\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Models\ModelVersion;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Support\Facades\DB;

/**
 * Service für Model-Versionierung
 * 
 * Erstellt Snapshots von Models vor/nach Änderungen für Undo/Redo
 */
class ModelVersioningService
{
    /**
     * Erstellt eine Version vor einer Update-Operation
     */
    public function createVersionBeforeUpdate(
        Model $model,
        string $operation,
        ?string $toolName = null,
        ?string $traceId = null,
        ?string $chainId = null,
        ?int $toolExecutionId = null,
        ?ToolContext $context = null,
        ?string $reason = null
    ): ModelVersion {
        // Snapshot VOR der Änderung
        $snapshotBefore = $this->createSnapshot($model);
        
        // Bestimme nächste Versionsnummer
        $versionNumber = $this->getNextVersionNumber($model);
        
        return ModelVersion::create([
            'versionable_type' => get_class($model),
            'versionable_id' => $model->id,
            'version_number' => $versionNumber,
            'operation' => $operation,
            'snapshot_before' => $snapshotBefore,
            'snapshot_after' => null, // Wird nach Update gesetzt
            'changed_fields' => null, // Wird nach Update gesetzt
            'user_id' => $context?->user?->id,
            'team_id' => $context?->team?->id,
            'tool_name' => $toolName,
            'trace_id' => $traceId,
            'chain_id' => $chainId,
            'tool_execution_id' => $toolExecutionId,
            'reason' => $reason,
        ]);
    }

    /**
     * Aktualisiert eine Version nach einer Update-Operation
     */
    public function updateVersionAfterUpdate(
        ModelVersion $version,
        Model $model,
        array $changedFields = []
    ): ModelVersion {
        $version->update([
            'snapshot_after' => $this->createSnapshot($model),
            'changed_fields' => $changedFields,
        ]);
        
        return $version->fresh();
    }

    /**
     * Erstellt eine Version für eine Create-Operation
     */
    public function createVersionForCreate(
        Model $model,
        ?string $toolName = null,
        ?string $traceId = null,
        ?string $chainId = null,
        ?int $toolExecutionId = null,
        ?ToolContext $context = null,
        ?string $reason = null
    ): ModelVersion {
        $versionNumber = $this->getNextVersionNumber($model);
        
        return ModelVersion::create([
            'versionable_type' => get_class($model),
            'versionable_id' => $model->id,
            'version_number' => $versionNumber,
            'operation' => 'created',
            'snapshot_before' => null, // Bei Create gibt es kein "vorher"
            'snapshot_after' => $this->createSnapshot($model),
            'changed_fields' => null,
            'user_id' => $context?->user?->id,
            'team_id' => $context?->team?->id,
            'tool_name' => $toolName,
            'trace_id' => $traceId,
            'chain_id' => $chainId,
            'tool_execution_id' => $toolExecutionId,
            'reason' => $reason,
        ]);
    }

    /**
     * Erstellt eine Version für eine Delete-Operation
     */
    public function createVersionForDelete(
        Model $model,
        ?string $toolName = null,
        ?string $traceId = null,
        ?string $chainId = null,
        ?int $toolExecutionId = null,
        ?ToolContext $context = null,
        ?string $reason = null
    ): ModelVersion {
        $versionNumber = $this->getNextVersionNumber($model);
        
        return ModelVersion::create([
            'versionable_type' => get_class($model),
            'versionable_id' => $model->id,
            'version_number' => $versionNumber,
            'operation' => 'deleted',
            'snapshot_before' => $this->createSnapshot($model),
            'snapshot_after' => null, // Bei Delete gibt es kein "nachher"
            'changed_fields' => null,
            'user_id' => $context?->user?->id,
            'team_id' => $context?->team?->id,
            'tool_name' => $toolName,
            'trace_id' => $traceId,
            'chain_id' => $chainId,
            'tool_execution_id' => $toolExecutionId,
            'reason' => $reason,
        ]);
    }

    /**
     * Erstellt einen Snapshot eines Models
     */
    protected function createSnapshot(Model $model): array
    {
        // Hole alle Attribute (inklusive versteckter)
        $attributes = $model->getAttributes();
        
        // Füge Relationships hinzu (wenn geladen)
        $relationships = [];
        foreach ($model->getRelations() as $key => $relation) {
            if ($relation instanceof Model) {
                $relationships[$key] = $relation->getAttributes();
            } elseif (is_iterable($relation)) {
                $relationships[$key] = collect($relation)->map(fn($r) => $r instanceof Model ? $r->getAttributes() : $r)->toArray();
            }
        }
        
        return [
            'attributes' => $attributes,
            'relationships' => $relationships,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Bestimmt die nächste Versionsnummer
     */
    protected function getNextVersionNumber(Model $model): int
    {
        $latest = ModelVersion::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->max('version_number');
        
        return ($latest ?? 0) + 1;
    }

    /**
     * Holt alle Versionen eines Models
     */
    public function getVersions(Model $model): \Illuminate\Database\Eloquent\Collection
    {
        return ModelVersion::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->orderBy('version_number', 'desc')
            ->get();
    }

    /**
     * Holt die neueste Version eines Models
     */
    public function getLatestVersion(Model $model): ?ModelVersion
    {
        return ModelVersion::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->orderBy('version_number', 'desc')
            ->first();
    }
}

