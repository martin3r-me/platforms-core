<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Services\ToolMetricsService;

/**
 * Artisan Command zum Anzeigen von Tool-Metriken
 */
class ShowToolMetricsCommand extends Command
{
    protected $signature = 'tools:metrics 
                            {--tool= : Spezifisches Tool (optional)}
                            {--days=7 : Anzahl Tage für Trends}
                            {--top=10 : Anzahl Top-Tools}';

    protected $description = 'Zeigt Tool-Metriken und Analytics an';

    public function handle(ToolMetricsService $metricsService): int
    {
        $toolName = $this->option('tool');
        $days = (int)$this->option('days');
        $top = (int)$this->option('top');

        if ($toolName) {
            $this->showToolMetrics($metricsService, $toolName, $days);
        } else {
            $this->showOverview($metricsService, $top, $days);
        }

        return 0;
    }

    private function showToolMetrics(ToolMetricsService $metricsService, string $toolName, int $days): void
    {
        $this->info("📊 Metriken für Tool: {$toolName}");
        $this->newLine();

        $metrics = $metricsService->getToolMetrics($toolName);
        
        $this->table(
            ['Metrik', 'Wert'],
            [
                ['Gesamt-Ausführungen', $metrics['total_executions']],
                ['Erfolgreich', $metrics['successful']],
                ['Fehlgeschlagen', $metrics['failed']],
                ['Success Rate', $metrics['success_rate'] . '%'],
                ['Error Rate', $metrics['error_rate'] . '%'],
                ['Ø Dauer', $metrics['avg_duration_ms'] . 'ms'],
                ['Ø Memory', $metrics['avg_memory_mb'] . 'MB'],
            ]
        );

        // Performance Trends
        $this->newLine();
        $this->info("📈 Performance-Trends (letzte {$days} Tage):");
        $trends = $metricsService->getPerformanceTrends($toolName, $days);
        
        if (empty($trends)) {
            $this->line("Keine Daten verfügbar");
        } else {
            $this->table(
                ['Datum', 'Ø Dauer (ms)', 'Ausführungen', 'Success Rate'],
                array_map(fn($t) => [
                    $t['date'],
                    $t['avg_duration_ms'],
                    $t['execution_count'],
                    $t['success_rate'] . '%',
                ], $trends)
            );
        }
    }

    private function showOverview(ToolMetricsService $metricsService, int $top, int $days): void
    {
        $this->info("📊 Tool-Metriken Übersicht");
        $this->newLine();

        // Usage Statistics
        $stats = $metricsService->getUsageStatistics();
        $this->info("📈 Allgemeine Statistiken:");
        $this->table(
            ['Metrik', 'Wert'],
            [
                ['Gesamt-Ausführungen', $stats['total_executions']],
                ['Eindeutige Tools', $stats['unique_tools']],
                ['Eindeutige User', $stats['unique_users']],
                ['Eindeutige Teams', $stats['unique_teams']],
            ]
        );

        // Top Tools
        $this->newLine();
        $this->info("🏆 Top {$top} Tools (nach Usage):");
        $topTools = $metricsService->getTopTools($top);
        
        if (empty($topTools)) {
            $this->line("Keine Daten verfügbar");
        } else {
            $this->table(
                ['Tool', 'Usage Count'],
                array_map(fn($t) => [$t['tool_name'], $t['usage_count']], $topTools)
            );
        }

        // Error Analytics
        $this->newLine();
        $this->info("❌ Error-Analytics:");
        $errors = $metricsService->getErrorAnalytics();
        
        $this->line("Gesamt-Fehler: {$errors['total_errors']}");
        
        if (!empty($errors['errors_by_code'])) {
            $this->newLine();
            $this->line("Fehler nach Code:");
            $this->table(
                ['Error Code', 'Anzahl'],
                array_map(fn($e) => [$e['error_code'], $e['count']], $errors['errors_by_code'])
            );
        }

        if (!empty($errors['errors_by_tool'])) {
            $this->newLine();
            $this->line("Fehler nach Tool:");
            $this->table(
                ['Tool', 'Anzahl'],
                array_map(fn($e) => [$e['tool_name'], $e['count']], $errors['errors_by_tool'])
            );
        }
    }
}

