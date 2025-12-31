<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service für Tool-Metriken & Analytics
 * 
 * Aggregiert Metriken aus Tool-Executions für Analytics
 */
class ToolMetricsService
{
    /**
     * Zeichnet eine Tool-Ausführung auf
     */
    public function recordExecution(
        string $toolName,
        bool $success,
        float $duration,
        int $memoryUsage,
        ?int $userId = null,
        ?int $teamId = null,
        ?string $errorCode = null
    ): void {
        // Wird bereits von ToolExecutionTracker gemacht
        // Diese Methode ist für zukünftige Erweiterungen
    }

    /**
     * Gibt aggregierte Metriken für ein Tool zurück
     */
    public function getToolMetrics(
        string $toolName,
        ?\DateTime $startDate = null,
        ?\DateTime $endDate = null
    ): array {
        $query = ToolExecution::forTool($toolName);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $successful = $query->clone()->successful()->count();
        $failed = $query->clone()->failed()->count();

        $avgDuration = $query->clone()->avg('duration_ms');
        $avgMemory = $query->clone()->avg('memory_usage_bytes');

        return [
            'tool_name' => $toolName,
            'total_executions' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'error_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
            'avg_duration_ms' => round($avgDuration ?? 0, 2),
            'avg_memory_bytes' => round($avgMemory ?? 0, 2),
            'avg_memory_mb' => round(($avgMemory ?? 0) / 1024 / 1024, 2),
        ];
    }

    /**
     * Gibt Top-Tools zurück (nach Usage)
     */
    public function getTopTools(int $limit = 10, ?\DateTime $startDate = null): array
    {
        $query = ToolExecution::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return $query
            ->select('tool_name', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('tool_name')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'tool_name' => $row->tool_name,
                'usage_count' => $row->usage_count,
            ])
            ->toArray();
    }

    /**
     * Gibt Least-Used-Tools zurück
     */
    public function getLeastUsedTools(int $limit = 10, ?\DateTime $startDate = null): array
    {
        $query = ToolExecution::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return $query
            ->select('tool_name', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('tool_name')
            ->orderBy('usage_count')
            ->limit($limit)
            ->get()
            ->map(fn($row) => [
                'tool_name' => $row->tool_name,
                'usage_count' => $row->usage_count,
            ])
            ->toArray();
    }

    /**
     * Gibt Error-Analytics zurück
     */
    public function getErrorAnalytics(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = ToolExecution::failed();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totalErrors = $query->count();

        $errorsByCode = $query->clone()
            ->select('error_code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('error_code')
            ->groupBy('error_code')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'error_code' => $row->error_code,
                'count' => $row->count,
            ])
            ->toArray();

        $errorsByTool = $query->clone()
            ->select('tool_name', DB::raw('COUNT(*) as count'))
            ->groupBy('tool_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'tool_name' => $row->tool_name,
                'count' => $row->count,
            ])
            ->toArray();

        return [
            'total_errors' => $totalErrors,
            'errors_by_code' => $errorsByCode,
            'errors_by_tool' => $errorsByTool,
        ];
    }

    /**
     * Gibt Performance-Trends zurück
     */
    public function getPerformanceTrends(
        string $toolName,
        int $days = 7
    ): array {
        $startDate = now()->subDays($days);

        return ToolExecution::forTool($toolName)
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('AVG(duration_ms) as avg_duration'),
                DB::raw('COUNT(*) as execution_count'),
                DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'avg_duration_ms' => round($row->avg_duration, 2),
                'execution_count' => $row->execution_count,
                'success_rate' => $row->execution_count > 0 
                    ? round(($row->successful_count / $row->execution_count) * 100, 2) 
                    : 0,
            ])
            ->toArray();
    }

    /**
     * Gibt Tool-Usage-Statistiken zurück
     */
    public function getUsageStatistics(?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = ToolExecution::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $total = $query->count();
        $uniqueTools = $query->clone()->distinct('tool_name')->count('tool_name');
        $uniqueUsers = $query->clone()->distinct('user_id')->whereNotNull('user_id')->count('user_id');
        $uniqueTeams = $query->clone()->distinct('team_id')->whereNotNull('team_id')->count('team_id');

        return [
            'total_executions' => $total,
            'unique_tools' => $uniqueTools,
            'unique_users' => $uniqueUsers,
            'unique_teams' => $uniqueTeams,
        ];
    }
}

