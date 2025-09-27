<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class AgentOrchestrator
{
    protected ToolExecutor $toolExecutor;
    protected array $activities = [];
    
    public function __construct(ToolExecutor $toolExecutor)
    {
        $this->toolExecutor = $toolExecutor;
    }
    
    /**
     * Führe komplexe Multi-Step Queries aus
     */
    public function executeComplexQuery(string $query, callable $activityCallback = null): array
    {
        $this->activities = [];
        
        // 1. Query analysieren
        $this->addActivity('Analysiere Anfrage...', '', [], [], 'running', 'Verstehe was der User möchte');
        $this->notifyActivity($activityCallback);
        $intent = $this->analyzeIntent($query);
        $this->updateLastActivity('success', 'Anfrage verstanden: ' . $intent['action']);
        $this->notifyActivity($activityCallback);
        
        // 2. Execution Plan erstellen
        $this->addActivity('Erstelle Ausführungsplan...', '', [], [], 'running', 'Plane die notwendigen Schritte');
        $this->notifyActivity($activityCallback);
        $plan = $this->createExecutionPlan($intent);
        $this->updateLastActivity('success', 'Plan erstellt: ' . count($plan) . ' Schritte');
        $this->notifyActivity($activityCallback);
        
        // 3. Tools sequenziell ausführen
        $results = [];
        foreach ($plan as $index => $step) {
            \Log::info("🔧 EXECUTING STEP {$index}:", $step);
            
            $this->addActivity(
                "Führe {$step['tool']} aus...",
                $step['tool'],
                $step['parameters'],
                [],
                'running',
                $step['description']
            );
            $this->notifyActivity($activityCallback);

            $startTime = microtime(true);
            $result = $this->toolExecutor->executeTool($step['tool'], $step['parameters']);
            $duration = (microtime(true) - $startTime) * 1000;

            \Log::info("🔧 TOOL RESULT:", [
                'tool' => $step['tool'],
                'ok' => $result['ok'],
                'duration' => $duration,
                'data' => $result['data'] ?? 'no data'
            ]);

            if ($result['ok']) {
                $this->updateLastActivity('success', 'Erfolgreich ausgeführt', $duration);
                $results[] = $result;
            } else {
                $this->updateLastActivity('error', 'Fehler: ' . $result['error'], $duration);
                \Log::error("❌ TOOL FAILED:", $result);
                break;
            }

            $this->notifyActivity($activityCallback);
        }
        
        // 4. Ergebnisse intelligent zusammenfassen
        $this->addActivity('Fasse Ergebnisse zusammen...', '', [], [], 'running', 'Erstelle finale Antwort');
        $this->notifyActivity($activityCallback);
        $finalResult = $this->synthesizeResults($results, $intent);
        $this->updateLastActivity('success', 'Antwort erstellt');
        $this->notifyActivity($activityCallback);
        
        // Completion Event
        Event::dispatch('agent.activity.complete');
        
        return [
            'ok' => true,
            'content' => $finalResult,
            'activities' => $this->activities,
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0]
        ];
    }
    
    /**
     * Benachrichtige über Activity
     */
    protected function notifyActivity(callable $activityCallback = null): void
    {
        if ($activityCallback && !empty($this->activities)) {
            $activityCallback($this->getLastActivity());
        }
        
        // Laravel Event für Real-time Updates
        if (!empty($this->activities)) {
            $lastActivity = $this->getLastActivity();
            Event::dispatch('agent.activity.update', [
                'step' => $lastActivity->step,
                'tool' => $lastActivity->tool,
                'status' => $lastActivity->status,
                'message' => $lastActivity->message,
                'duration' => $lastActivity->duration,
                'icon' => $lastActivity->getStatusIcon(),
                'timestamp' => now()->format('H:i:s')
            ]);
        }
    }
    
    /**
     * Analysiere User Intent
     */
    protected function analyzeIntent(string $query): array
    {
        // DEBUG: Logge die Query
        \Log::info('🔍 ANALYZE INTENT:', ['query' => $query]);
        
        $query = strtolower($query);
        
        // Einfache Intent-Erkennung
        if (str_contains($query, 'zeige') || str_contains($query, 'liste')) {
            $intent = ['action' => 'list', 'entity' => $this->extractEntity($query)];
            \Log::info('🎯 INTENT DETECTED:', $intent);
            return $intent;
        }
        
        if (str_contains($query, 'erstelle') || str_contains($query, 'neue')) {
            $intent = ['action' => 'create', 'entity' => $this->extractEntity($query)];
            \Log::info('🎯 INTENT DETECTED:', $intent);
            return $intent;
        }
        
        if (str_contains($query, 'aktualisiere') || str_contains($query, 'update')) {
            $intent = ['action' => 'update', 'entity' => $this->extractEntity($query)];
            \Log::info('🎯 INTENT DETECTED:', $intent);
            return $intent;
        }
        
        $intent = ['action' => 'unknown', 'entity' => 'unknown'];
        \Log::info('❓ UNKNOWN INTENT:', $intent);
        return $intent;
    }
    
    /**
     * Extrahiere Entity aus Query
     */
    protected function extractEntity(string $query): string
    {
        if (str_contains($query, 'task') || str_contains($query, 'aufgabe')) return 'task';
        if (str_contains($query, 'projekt')) return 'project';
        if (str_contains($query, 'okr')) return 'okr';
        if (str_contains($query, 'sprint')) return 'sprint';
        return 'unknown';
    }
    
    /**
     * Erstelle Execution Plan
     */
    protected function createExecutionPlan(array $intent): array
    {
        // DEBUG: Logge Intent
        \Log::info('📋 CREATE EXECUTION PLAN:', $intent);
        
        $plan = [];
        
        // Immer zuerst Kontext abrufen
        $plan[] = [
            'tool' => 'get_context',
            'parameters' => [],
            'description' => 'Aktuellen Kontext abrufen'
        ];
        \Log::info('✅ ADDED TO PLAN: get_context');
        
        // Je nach Intent weitere Schritte
        switch ($intent['action']) {
            case 'list':
                if ($intent['entity'] === 'task') {
                    $plan[] = [
                        'tool' => 'plannertask_get_all',
                        'parameters' => [],
                        'description' => 'Alle Tasks abrufen'
                    ];
                    \Log::info('✅ ADDED TO PLAN: plannertask_get_all');
                } elseif ($intent['entity'] === 'project') {
                    $plan[] = [
                        'tool' => 'plannerproject_get_all',
                        'parameters' => [],
                        'description' => 'Alle Projekte abrufen'
                    ];
                    \Log::info('✅ ADDED TO PLAN: plannerproject_get_all');
                }
                break;
                
            case 'create':
                if ($intent['entity'] === 'task') {
                    $plan[] = [
                        'tool' => 'plannertask_create',
                        'parameters' => ['name' => 'Neue Task', 'description' => 'Erstellt vom Agent'],
                        'description' => 'Neue Task erstellen'
                    ];
                    \Log::info('✅ ADDED TO PLAN: plannertask_create');
                }
                break;
        }
        
        \Log::info('📋 FINAL PLAN:', $plan);
        return $plan;
    }
    
    /**
     * Synthesisiere Ergebnisse
     */
    protected function synthesizeResults(array $results, array $intent): string
    {
        $response = "Hier sind die Ergebnisse:\n\n";
        
        foreach ($results as $index => $result) {
            if ($result['ok'] && isset($result['data'])) {
                $data = $result['data'];
                
                if (isset($data['items'])) {
                    $items = $data['items'];
                    $filteredItems = $this->filterTestTasks($items);
                    
                    $response .= "**Gefunden: " . count($filteredItems) . " relevante Einträge** (von " . count($items) . " total)\n";
                    foreach ($filteredItems as $item) {
                        $response .= "- " . ($item['name'] ?? $item['title'] ?? 'Unbekannt') . "\n";
                    }
                    
                    if (count($items) > count($filteredItems)) {
                        $testCount = count($items) - count($filteredItems);
                        $response .= "\n*" . $testCount . " Test-Aufgaben wurden ausgeblendet*\n";
                    }
                } elseif (isset($data['item'])) {
                    $response .= "**Erstellt:** " . ($data['item']['name'] ?? $data['item']['title'] ?? 'Unbekannt') . "\n";
                }
            }
        }
        
        return $response;
    }
    
    /**
     * Filtere Test-Aufgaben heraus
     */
    protected function filterTestTasks(array $items): array
    {
        $testKeywords = [
            'test', 'TEST', 'Test',
            'neue aufgabe', 'Neue Aufgabe',
            'einkaufen', 'Einkaufen',
            'dummy', 'Dummy',
            'beispiel', 'Beispiel',
            'sample', 'Sample'
        ];
        
        return array_filter($items, function($item) use ($testKeywords) {
            $name = strtolower($item['name'] ?? $item['title'] ?? '');
            
            // Prüfe auf Test-Keywords
            foreach ($testKeywords as $keyword) {
                if (str_contains($name, strtolower($keyword))) {
                    return false; // Filtere Test-Aufgaben heraus
                }
            }
            
            // Prüfe auf sehr generische Namen
            if (strlen($name) < 5 || $name === 'aufgabe' || $name === 'task') {
                return false;
            }
            
            return true; // Behalte relevante Aufgaben
        });
    }
    
    /**
     * Füge Activity hinzu
     */
    protected function addActivity(string $step, string $tool, array $parameters, array $result, string $status, string $message): void
    {
        $this->activities[] = new AgentActivity($step, $tool, $parameters, $result, $status, $message);
    }
    
    /**
     * Aktualisiere letzte Activity
     */
    protected function updateLastActivity(string $status, string $message, float $duration = 0.0): void
    {
        if (!empty($this->activities)) {
            $lastIndex = count($this->activities) - 1;
            $this->activities[$lastIndex]->status = $status;
            $this->activities[$lastIndex]->message = $message;
            $this->activities[$lastIndex]->duration = $duration;
        }
    }
    
    /**
     * Hole letzte Activity
     */
    protected function getLastActivity(): AgentActivity
    {
        return end($this->activities) ?: new AgentActivity('Keine Aktivität');
    }
    
    /**
     * Hole alle Activities
     */
    public function getActivities(): array
    {
        return $this->activities;
    }
}
