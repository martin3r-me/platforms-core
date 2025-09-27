<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AgentPerformanceMonitor
{
    protected array $metrics = [];
    protected float $startTime;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    /**
     * Starte Performance Tracking
     */
    public function startTracking(string $operation): void
    {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        Log::info("🔍 PERFORMANCE START:", [
            'operation' => $operation,
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Beende Performance Tracking
     */
    public function endTracking(string $operation, array $additionalData = []): array
    {
        if (!isset($this->metrics[$operation])) {
            return [];
        }
        
        $endTime = microtime(true);
        $startTime = $this->metrics[$operation]['start_time'];
        
        $metrics = [
            'operation' => $operation,
            'execution_time_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_usage_mb' => round((memory_get_usage(true) - $this->metrics[$operation]['start_memory']) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'timestamp' => now()->toISOString(),
            'additional_data' => $additionalData
        ];
        
        // Log Performance
        Log::info("🔍 PERFORMANCE END:", $metrics);
        
        // Cache für Analytics
        $this->cacheMetrics($metrics);
        
        // Cleanup
        unset($this->metrics[$operation]);
        
        return $metrics;
    }
    
    /**
     * Tracke Tool Execution
     */
    public function trackToolExecution(string $toolName, array $parameters, array $result): array
    {
        $startTime = microtime(true);
        
        $metrics = [
            'tool_name' => $toolName,
            'parameters_count' => count($parameters),
            'result_success' => $result['ok'] ?? false,
            'result_data_count' => is_array($result['data'] ?? null) ? count($result['data']) : 0,
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'timestamp' => now()->toISOString()
        ];
        
        Log::info("🔧 TOOL EXECUTION:", $metrics);
        
        return $metrics;
    }
    
    /**
     * Tracke OpenAI API Calls
     */
    public function trackOpenAICall(string $model, int $tokensUsed, float $cost = null): array
    {
        $metrics = [
            'model' => $model,
            'tokens_used' => $tokensUsed,
            'cost_usd' => $cost,
            'timestamp' => now()->toISOString()
        ];
        
        Log::info("🤖 OPENAI API CALL:", $metrics);
        
        // Cache für Cost Tracking
        $this->cacheOpenAIMetrics($metrics);
        
        return $metrics;
    }
    
    /**
     * Tracke Agent Orchestrierung
     */
    public function trackOrchestration(string $query, array $toolsUsed, array $result): array
    {
        $metrics = [
            'query_length' => strlen($query),
            'tools_used_count' => count($toolsUsed),
            'tools_used' => $toolsUsed,
            'result_success' => $result['ok'] ?? false,
            'execution_time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
            'timestamp' => now()->toISOString()
        ];
        
        Log::info("🎯 ORCHESTRATION:", $metrics);
        
        return $metrics;
    }
    
    /**
     * Hole Performance Statistiken
     */
    public function getPerformanceStats(int $hours = 24): array
    {
        $cacheKey = "agent_performance_stats_{$hours}h";
        
        return Cache::remember($cacheKey, 300, function() use ($hours) {
            // Hier würden echte DB-Queries stehen
            // Für jetzt Mock-Daten
            return [
                'total_operations' => 150,
                'average_execution_time_ms' => 1250.5,
                'success_rate' => 0.95,
                'most_used_tools' => [
                    'plannerproject_get_all' => 45,
                    'plannertask_get_all' => 32,
                    'plannerprojectslot_get_all' => 28
                ],
                'openai_calls' => 89,
                'total_tokens_used' => 45678,
                'estimated_cost_usd' => 12.34
            ];
        });
    }
    
    /**
     * Cache Metrics für Analytics
     */
    protected function cacheMetrics(array $metrics): void
    {
        $cacheKey = 'agent_performance_metrics';
        $existing = Cache::get($cacheKey, []);
        $existing[] = $metrics;
        
        // Keep only last 100 entries
        if (count($existing) > 100) {
            $existing = array_slice($existing, -100);
        }
        
        Cache::put($cacheKey, $existing, 3600);
    }
    
    /**
     * Cache OpenAI Metrics
     */
    protected function cacheOpenAIMetrics(array $metrics): void
    {
        $cacheKey = 'agent_openai_metrics';
        $existing = Cache::get($cacheKey, []);
        $existing[] = $metrics;
        
        // Keep only last 50 entries
        if (count($existing) > 50) {
            $existing = array_slice($existing, -50);
        }
        
        Cache::put($cacheKey, $existing, 3600);
    }
    
    /**
     * Hole System Health Status
     */
    public function getSystemHealth(): array
    {
        return [
            'status' => 'healthy',
            'uptime' => $this->getUptime(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'cache_status' => $this->getCacheStatus(),
            'database_status' => $this->getDatabaseStatus(),
            'openai_status' => $this->getOpenAIStatus(),
            'timestamp' => now()->toISOString()
        ];
    }
    
    /**
     * Hole Uptime
     */
    protected function getUptime(): string
    {
        $uptime = microtime(true) - $this->startTime;
        return round($uptime, 2) . 's';
    }
    
    /**
     * Hole Cache Status
     */
    protected function getCacheStatus(): string
    {
        try {
            Cache::put('health_check', 'ok', 60);
            return Cache::get('health_check') === 'ok' ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }
    
    /**
     * Hole Database Status
     */
    protected function getDatabaseStatus(): string
    {
        try {
            \DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }
    
    /**
     * Hole OpenAI Status
     */
    protected function getOpenAIStatus(): string
    {
        $apiKey = env('OPENAI_API_KEY');
        return !empty($apiKey) ? 'configured' : 'not_configured';
    }
}
