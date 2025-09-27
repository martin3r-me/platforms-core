<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AgentFallbackService
{
    protected ToolExecutor $toolExecutor;
    protected array $fallbackStrategies = [];
    
    public function __construct(ToolExecutor $toolExecutor)
    {
        $this->toolExecutor = $toolExecutor;
        $this->initializeFallbackStrategies();
    }
    
    /**
     * Initialisiere Fallback-Strategien
     */
    protected function initializeFallbackStrategies(): void
    {
        $this->fallbackStrategies = [
            'simple_query' => [$this, 'handleSimpleQuery'],
            'project_query' => [$this, 'handleProjectQuery'],
            'task_query' => [$this, 'handleTaskQuery'],
            'slot_query' => [$this, 'handleSlotQuery'],
            'default' => [$this, 'handleDefaultFallback']
        ];
    }
    
    /**
     * Führe Fallback aus wenn OpenAI nicht verfügbar ist
     */
    public function executeFallback(string $query): array
    {
        Log::warning("🔄 FALLBACK ACTIVATED:", ['query' => $query]);
        
        try {
            // Bestimme Fallback-Strategie basierend auf Query
            $strategy = $this->determineFallbackStrategy($query);
            
            // Führe Fallback aus
            $result = call_user_func($strategy, $query);
            
            Log::info("🔄 FALLBACK SUCCESS:", [
                'strategy' => $strategy[1],
                'result_success' => $result['ok'] ?? false
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("🔄 FALLBACK ERROR:", [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            
            return $this->handleCriticalFallback($query, $e);
        }
    }
    
    /**
     * Bestimme Fallback-Strategie basierend auf Query
     */
    protected function determineFallbackStrategy(string $query): callable
    {
        $query = strtolower($query);
        
        // Projekt-spezifische Queries
        if (str_contains($query, 'projekt') || str_contains($query, 'project')) {
            return $this->fallbackStrategies['project_query'];
        }
        
        // Task-spezifische Queries
        if (str_contains($query, 'aufgabe') || str_contains($query, 'task')) {
            return $this->fallbackStrategies['task_query'];
        }
        
        // Slot-spezifische Queries
        if (str_contains($query, 'slot')) {
            return $this->fallbackStrategies['slot_query'];
        }
        
        // Einfache Queries
        if (strlen($query) < 50) {
            return $this->fallbackStrategies['simple_query'];
        }
        
        // Default Fallback
        return $this->fallbackStrategies['default'];
    }
    
    /**
     * Handle einfache Queries
     */
    protected function handleSimpleQuery(string $query): array
    {
        // Versuche direkte Tool-Ausführung
        $tools = ['get_current_time', 'get_context'];
        
        foreach ($tools as $tool) {
            $result = $this->toolExecutor->executeTool($tool, []);
            if ($result['ok']) {
                return [
                    'ok' => true,
                    'data' => $result['data'],
                    'message' => 'Fallback: Einfache Query verarbeitet',
                    'fallback' => true
                ];
            }
        }
        
        return $this->handleDefaultFallback($query);
    }
    
    /**
     * Handle Projekt-Queries
     */
    protected function handleProjectQuery(string $query): array
    {
        $result = $this->toolExecutor->executeTool('plannerproject_get_all', []);
        
        if ($result['ok']) {
            $projects = $result['data'] ?? [];
            
            return [
                'ok' => true,
                'data' => $projects,
                'message' => 'Fallback: Projekte abgerufen (OpenAI nicht verfügbar)',
                'fallback' => true,
                'count' => count($projects)
            ];
        }
        
        return $this->handleDefaultFallback($query);
    }
    
    /**
     * Handle Task-Queries
     */
    protected function handleTaskQuery(string $query): array
    {
        $result = $this->toolExecutor->executeTool('plannertask_get_all', []);
        
        if ($result['ok']) {
            $tasks = $result['data'] ?? [];
            
            return [
                'ok' => true,
                'data' => $tasks,
                'message' => 'Fallback: Tasks abgerufen (OpenAI nicht verfügbar)',
                'fallback' => true,
                'count' => count($tasks)
            ];
        }
        
        return $this->handleDefaultFallback($query);
    }
    
    /**
     * Handle Slot-Queries
     */
    protected function handleSlotQuery(string $query): array
    {
        $result = $this->toolExecutor->executeTool('plannerprojectslot_get_all', []);
        
        if ($result['ok']) {
            $slots = $result['data'] ?? [];
            
            return [
                'ok' => true,
                'data' => $slots,
                'message' => 'Fallback: Project Slots abgerufen (OpenAI nicht verfügbar)',
                'fallback' => true,
                'count' => count($slots)
            ];
        }
        
        return $this->handleDefaultFallback($query);
    }
    
    /**
     * Handle Default Fallback
     */
    protected function handleDefaultFallback(string $query): array
    {
        return [
            'ok' => false,
            'error' => 'OpenAI Service nicht verfügbar. Fallback-Modus aktiviert.',
            'message' => 'Entschuldigung, der KI-Service ist derzeit nicht verfügbar. Bitte versuchen Sie es später erneut.',
            'fallback' => true,
            'suggestions' => [
                'Versuchen Sie eine einfachere Anfrage',
                'Kontaktieren Sie den Administrator',
                'Prüfen Sie Ihre Internetverbindung'
            ]
        ];
    }
    
    /**
     * Handle Critical Fallback (wenn alles fehlschlägt)
     */
    protected function handleCriticalFallback(string $query, \Exception $e): array
    {
        Log::critical("🔄 CRITICAL FALLBACK:", [
            'query' => $query,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'ok' => false,
            'error' => 'Kritischer Systemfehler',
            'message' => 'Entschuldigung, es ist ein kritischer Systemfehler aufgetreten. Der Administrator wurde benachrichtigt.',
            'fallback' => true,
            'critical' => true,
            'support_contact' => 'admin@platform.com'
        ];
    }
    
    /**
     * Prüfe ob OpenAI verfügbar ist
     */
    public function isOpenAIAvailable(): bool
    {
        try {
            // Prüfe API Key
            $apiKey = env('OPENAI_API_KEY');
            if (empty($apiKey)) {
                return false;
            }
            
            // API Key ist vorhanden, verwende IntelligentAgent
            Log::info("🤖 OpenAI API Key gefunden, verwende IntelligentAgent");
            return true;
            
        } catch (\Exception $e) {
            Log::warning("🔄 OPENAI AVAILABILITY CHECK FAILED:", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Hole Fallback-Statistiken
     */
    public function getFallbackStats(): array
    {
        $cacheKey = 'agent_fallback_stats';
        $stats = Cache::get($cacheKey, [
            'total_fallbacks' => 0,
            'successful_fallbacks' => 0,
            'failed_fallbacks' => 0,
            'last_fallback' => null
        ]);
        
        return $stats;
    }
    
    /**
     * Tracke Fallback-Usage
     */
    protected function trackFallbackUsage(bool $success): void
    {
        $cacheKey = 'agent_fallback_stats';
        $stats = Cache::get($cacheKey, [
            'total_fallbacks' => 0,
            'successful_fallbacks' => 0,
            'failed_fallbacks' => 0,
            'last_fallback' => null
        ]);
        
        $stats['total_fallbacks']++;
        if ($success) {
            $stats['successful_fallbacks']++;
        } else {
            $stats['failed_fallbacks']++;
        }
        $stats['last_fallback'] = now()->toISOString();
        
        Cache::put($cacheKey, $stats, 86400); // 24 Stunden
    }
}
