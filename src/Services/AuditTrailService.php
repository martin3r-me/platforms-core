<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolExecution;
use Platform\Core\Models\ModelVersion;
use Illuminate\Support\Collection;

/**
 * Service für Step-by-Step Audit Trail
 * 
 * Erstellt einen detaillierten Audit Trail aller Schritte einer LLM-Aktion
 */
class AuditTrailService
{
    /**
     * Erstellt einen Audit Trail für einen Trace-ID
     */
    public function getAuditTrail(string $traceId): array
    {
        // Hole alle Tool-Executions für diesen Trace
        $toolExecutions = ToolExecution::where('trace_id', $traceId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Hole alle Model-Versionen für diesen Trace
        $modelVersions = ModelVersion::where('trace_id', $traceId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Baue Schritt-für-Schritt Timeline
        $steps = [];
        $stepNumber = 1;

        foreach ($toolExecutions as $execution) {
            $steps[] = [
                'step' => $stepNumber++,
                'type' => 'tool_execution',
                'timestamp' => $execution->created_at->toIso8601String(),
                'tool_name' => $execution->tool_name,
                'arguments' => $execution->arguments,
                'success' => $execution->success,
                'error_code' => $execution->error_code,
                'error_message' => $execution->error_message,
                'result_data' => $execution->result_data,
                'result_message' => $execution->result_message,
                'duration_ms' => $execution->duration_ms,
                'chain_position' => $execution->chain_position,
            ];

            // Füge zugehörige Model-Versionen hinzu
            $relatedVersions = $modelVersions->where('tool_execution_id', $execution->id);
            foreach ($relatedVersions as $version) {
                $steps[] = [
                    'step' => $stepNumber++,
                    'type' => 'model_change',
                    'timestamp' => $version->created_at->toIso8601String(),
                    'operation' => $version->operation,
                    'model_type' => $version->versionable_type,
                    'model_id' => $version->versionable_id,
                    'version_number' => $version->version_number,
                    'changed_fields' => $version->changed_fields,
                    'reason' => $version->reason,
                    'snapshot_before' => $version->snapshot_before,
                    'snapshot_after' => $version->snapshot_after,
                ];
            }
        }

        return [
            'trace_id' => $traceId,
            'total_steps' => count($steps),
            'steps' => $steps,
            'summary' => $this->generateSummary($toolExecutions, $modelVersions),
        ];
    }

    /**
     * Generiert eine Zusammenfassung
     */
    protected function generateSummary(Collection $toolExecutions, Collection $modelVersions): array
    {
        $toolsExecuted = $toolExecutions->count();
        $toolsSuccessful = $toolExecutions->where('success', true)->count();
        $toolsFailed = $toolExecutions->where('success', false)->count();

        $modelsCreated = $modelVersions->where('operation', 'created')->count();
        $modelsUpdated = $modelVersions->where('operation', 'updated')->count();
        $modelsDeleted = $modelVersions->where('operation', 'deleted')->count();

        return [
            'tools_executed' => $toolsExecuted,
            'tools_successful' => $toolsSuccessful,
            'tools_failed' => $toolsFailed,
            'models_created' => $modelsCreated,
            'models_updated' => $modelsUpdated,
            'models_deleted' => $modelsDeleted,
            'total_changes' => $modelVersions->count(),
        ];
    }

    /**
     * Erstellt einen Audit Trail für eine Chain-ID
     */
    public function getAuditTrailForChain(string $chainId): array
    {
        $toolExecutions = ToolExecution::where('chain_id', $chainId)
            ->orderBy('chain_position', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $modelVersions = ModelVersion::where('chain_id', $chainId)
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->getAuditTrail($toolExecutions->first()?->trace_id ?? '');
    }
}

