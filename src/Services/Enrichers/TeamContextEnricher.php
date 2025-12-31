<?php

namespace Platform\Core\Services\Enrichers;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ContextEnricherContract;
use Platform\Core\Models\ToolExecution;

/**
 * Enricher: Fügt Team-Context hinzu
 */
class TeamContextEnricher implements ContextEnricherContract
{
    public function enrich(ToolContext $context): ToolContext
    {
        if (!$context->team) {
            return $context;
        }

        // Hole Team-spezifische Statistiken
        $teamStats = [
            'total_executions' => ToolExecution::forTeam($context->team->id)->count(),
            'unique_tools' => ToolExecution::forTeam($context->team->id)
                ->distinct('tool_name')
                ->count('tool_name'),
            'recent_activity' => ToolExecution::forTeam($context->team->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['tool_name', 'created_at'])
                ->map(fn($e) => [
                    'tool' => $e->tool_name,
                    'timestamp' => $e->created_at->toIso8601String(),
                ])
                ->toArray(),
        ];

        // Erweitere Context-Metadata
        $metadata = $context->metadata ?? [];
        $metadata['team_context'] = $teamStats;

        return ToolContext::create(
            user: $context->user,
            team: $context->team,
            metadata: $metadata
        );
    }

    public function getPriority(): int
    {
        return 90; // Mittlere Priorität
    }
}

