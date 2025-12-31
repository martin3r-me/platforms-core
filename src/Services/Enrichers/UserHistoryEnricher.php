<?php

namespace Platform\Core\Services\Enrichers;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ContextEnricherContract;
use Platform\Core\Models\ToolExecution;

/**
 * Enricher: Fügt User-History zum Context hinzu
 */
class UserHistoryEnricher implements ContextEnricherContract
{
    public function enrich(ToolContext $context): ToolContext
    {
        if (!$context->user) {
            return $context;
        }

        // Hole letzte Tool-Ausführungen des Users
        $recentExecutions = ToolExecution::forUser($context->user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['tool_name', 'success', 'created_at'])
            ->map(fn($e) => [
                'tool' => $e->tool_name,
                'success' => $e->success,
                'timestamp' => $e->created_at->toIso8601String(),
            ])
            ->toArray();

        // Erweitere Context-Metadata
        $metadata = $context->metadata ?? [];
        $metadata['user_history'] = [
            'recent_tools' => $recentExecutions,
            'total_executions' => ToolExecution::forUser($context->user->id)->count(),
        ];

        return ToolContext::create(
            user: $context->user,
            team: $context->team,
            metadata: $metadata
        );
    }

    public function getPriority(): int
    {
        return 100; // Hohe Priorität
    }
}

