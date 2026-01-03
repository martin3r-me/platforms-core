<?php

namespace Platform\Core\Services;

use Platform\Core\Models\LlmActionSummary;
use Platform\Core\Models\ToolExecution;
use Platform\Core\Models\ModelVersion;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Support\Collection;

/**
 * Service für LLM-Action-Zusammenfassungen
 * 
 * Erstellt eine Zusammenfassung aller Aktionen, die die LLM in einem Request ausgeführt hat
 */
class ActionSummaryService
{
    /**
     * Erstellt eine Zusammenfassung für einen Trace-ID
     */
    public function createSummary(
        string $traceId,
        ?string $chainId = null,
        ?string $userMessage = null,
        ?ToolContext $context = null
    ): LlmActionSummary {
        // Hole alle Tool-Executions
        $toolExecutions = ToolExecution::where('trace_id', $traceId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Hole alle Model-Versionen
        $modelVersions = ModelVersion::where('trace_id', $traceId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Baue detaillierte Aktionen-Liste
        $actions = $this->buildActionsList($toolExecutions, $modelVersions);

        // Gruppiere Models nach Operation
        $createdModels = $this->groupModelsByOperation($modelVersions, 'created');
        $updatedModels = $this->groupModelsByOperation($modelVersions, 'updated');
        $deletedModels = $this->groupModelsByOperation($modelVersions, 'deleted');

        // Generiere Zusammenfassung
        $summary = $this->generateSummaryText($actions, $createdModels, $updatedModels, $deletedModels);

        // trace_id ist unique → bei mehrfachen Calls (Recovery/Retry) updaten statt Duplicate-Key zu werfen
        return LlmActionSummary::updateOrCreate([
            'trace_id' => $traceId,
        ], [
            'user_id' => $context?->user?->id,
            'team_id' => $context?->team?->id,
            'chain_id' => $chainId,
            'user_message' => $userMessage,
            'summary' => $summary,
            'actions' => $actions,
            'created_models' => $createdModels,
            'updated_models' => $updatedModels,
            'deleted_models' => $deletedModels,
            'tools_executed' => $toolExecutions->count(),
            'models_created' => $createdModels->count(),
            'models_updated' => $updatedModels->count(),
            'models_deleted' => $deletedModels->count(),
        ]);
    }

    /**
     * Baut die detaillierte Aktionen-Liste
     */
    protected function buildActionsList(Collection $toolExecutions, Collection $modelVersions): array
    {
        $actions = [];

        foreach ($toolExecutions as $execution) {
            $action = [
                'tool_name' => $execution->tool_name,
                'timestamp' => $execution->created_at->toIso8601String(),
                'success' => $execution->success,
                'result_message' => $execution->result_message,
            ];

            // Füge zugehörige Model-Änderungen hinzu
            $relatedVersions = $modelVersions->where('tool_execution_id', $execution->id);
            if ($relatedVersions->isNotEmpty()) {
                $action['model_changes'] = $relatedVersions->map(function ($version) {
                    return [
                        'operation' => $version->operation,
                        'model_type' => class_basename($version->versionable_type),
                        'model_id' => $version->versionable_id,
                        'reason' => $version->reason,
                    ];
                })->toArray();
            }

            $actions[] = $action;
        }

        return $actions;
    }

    /**
     * Gruppiert Models nach Operation
     */
    protected function groupModelsByOperation(Collection $modelVersions, string $operation): Collection
    {
        return $modelVersions
            ->where('operation', $operation)
            ->map(function ($version) {
                return [
                    'model_type' => class_basename($version->versionable_type),
                    'model_id' => $version->versionable_id,
                    'version_number' => $version->version_number,
                    'reason' => $version->reason,
                    'timestamp' => $version->created_at->toIso8601String(),
                ];
            })
            ->unique('model_id')
            ->values();
    }

    /**
     * Generiert eine lesbare Zusammenfassung
     */
    protected function generateSummaryText(
        array $actions,
        Collection $createdModels,
        Collection $updatedModels,
        Collection $deletedModels
    ): string {
        $parts = [];

        if ($createdModels->isNotEmpty()) {
            $parts[] = sprintf(
                '%d %s erstellt',
                $createdModels->count(),
                $createdModels->count() === 1 ? 'Element' : 'Elemente'
            );
        }

        if ($updatedModels->isNotEmpty()) {
            $parts[] = sprintf(
                '%d %s aktualisiert',
                $updatedModels->count(),
                $updatedModels->count() === 1 ? 'Element' : 'Elemente'
            );
        }

        if ($deletedModels->isNotEmpty()) {
            $parts[] = sprintf(
                '%d %s gelöscht',
                $deletedModels->count(),
                $deletedModels->count() === 1 ? 'Element' : 'Elemente'
            );
        }

        if (empty($parts)) {
            return 'Keine Änderungen durchgeführt.';
        }

        return implode(', ', $parts) . '.';
    }

    /**
     * Holt eine Zusammenfassung für einen Trace-ID
     */
    public function getSummary(string $traceId): ?LlmActionSummary
    {
        return LlmActionSummary::where('trace_id', $traceId)->first();
    }
}

