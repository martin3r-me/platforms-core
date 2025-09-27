<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Platform\Core\Services\AgentPerformanceMonitor;

class AgentOrchestrator
{
    protected ToolExecutor $toolExecutor;
    protected AgentPerformanceMonitor $performanceMonitor;
    protected array $activities = [];
    
    public function __construct(ToolExecutor $toolExecutor, AgentPerformanceMonitor $performanceMonitor)
    {
        $this->toolExecutor = $toolExecutor;
        $this->performanceMonitor = $performanceMonitor;
    }
    
    /**
     * Führe komplexe Multi-Step Queries aus
     */
    public function executeComplexQuery(string $query, callable $activityCallback = null): array
    {
        $this->activities = [];

        // Performance Tracking starten
        $this->performanceMonitor->startTracking('complex_query_execution');

        // 1. OpenAI Agent entscheidet selbst welche Tools er nutzt
        $this->addActivity('Analysiere Anfrage mit KI...', '', [], [], 'running', 'OpenAI Agent versteht die Anfrage');
        $this->notifyActivity($activityCallback);
        
        $result = $this->executeWithOpenAI($query, $activityCallback);
        
        // Performance Tracking beenden
        $performanceMetrics = $this->performanceMonitor->endTracking('complex_query_execution', [
            'query_length' => strlen($query),
            'result_success' => $result['ok'] ?? false
        ]);
        
        // Completion Event
        Event::dispatch('agent.activity.complete');

        return $result;
    }
    
    /**
     * OpenAI Orchestrierung mit Live Updates
     */
    protected function executeWithOpenAI(string $query, callable $activityCallback = null): array
    {
        try {
            // Input Validation
            if (empty($query)) {
                throw new \InvalidArgumentException('Query cannot be empty');
            }
            
            // Lade contextual Tools basierend auf Query
            $tools = app(\Platform\Core\Services\ToolRegistry::class)->getContextualTools($query);
            
            if (empty($tools)) {
                throw new \RuntimeException('No tools available for query');
            }
            
            // OpenAI API Key Validation
            $apiKey = env('OPENAI_API_KEY');
            if (empty($apiKey)) {
                throw new \RuntimeException('OpenAI API key not configured');
            }
            
            // OpenAI Client
            $factory = (new \OpenAI\Factory())->withApiKey($apiKey);
            if (env('OPENAI_ORGANIZATION')) {
                $factory->withOrganization(env('OPENAI_ORGANIZATION'));
            }
            $client = $factory->make();
            
        } catch (\Exception $e) {
            \Log::error("🤖 ORCHESTRATOR SETUP ERROR:", [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            
            return [
                'ok' => false,
                'error' => 'Orchestrator setup failed: ' . $e->getMessage(),
                'data' => []
            ];
        }
        
        // System Prompt für intelligente Orchestrierung
        $systemPrompt = "Du bist ein intelligenter Agent für ein Projektmanagement-System. 
        
        WICHTIGE REGELN:
        1. Verwende IMMER mehrere Tools in der richtigen Reihenfolge
        2. Für Projekt-Anfragen: Erst Projekte abrufen, dann Project Slots, dann Tasks
        3. Für Slot-Anfragen: Erst Project Slots abrufen, dann Tasks in den Slots
        4. Für Task-Anfragen: Erst Tasks abrufen, dann Relations (project, projectslot, etc.)
        5. Kombiniere die Ergebnisse zu einer vollständigen Antwort
        
        BEISPIEL für 'welche slots und aufgaben haben wir dort verplant?':
        1. plannerproject_get_all - Alle Projekte abrufen
        2. plannerprojectslot_get_all - Alle Project Slots abrufen  
        3. plannertask_get_all - Alle Tasks abrufen
        4. Kombiniere die Ergebnisse zu einer strukturierten Antwort
        
        BEISPIEL für 'welche project slots gibt es denn in dem projekt?':
        1. plannerproject_get_all - Alle Projekte abrufen
        2. plannerprojectslot_get_all - Alle Project Slots abrufen
        3. Für jedes Projekt: plannerproject_projectslots mit project_id
        4. Kombiniere die Ergebnisse zu einer strukturierten Antwort
        
        Verwende IMMER mehrere Tools für komplexe Anfragen!";
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $query]
        ];
        
        // OpenAI API mit Tools
        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',  // OpenAI entscheidet!
            'max_tokens' => 2000,
            'temperature' => 0.7,
        ]);
        
        $assistantMessage = $response->choices[0]->message;
        
        // Tool-Calls mit Live Updates verarbeiten
        if (!empty($assistantMessage->toolCalls)) {
            $this->addActivity('OpenAI orchestriert Tools...', '', [], [], 'running', 'OpenAI plant die Ausführung');
            $this->notifyActivity($activityCallback);
            
            $toolResults = $this->executeToolCallsWithLiveUpdates($assistantMessage->toolCalls, $activityCallback);
            
            // Tool-Ergebnisse an OpenAI für finale Antwort
            $messages[] = [
                'role' => 'assistant',
                'content' => $assistantMessage->content,
                'tool_calls' => $assistantMessage->toolCalls
            ];
            
            foreach ($toolResults as $result) {
                // Tool-Result als lesbaren Text formatieren
                $formattedContent = $this->formatToolResultForLLM($result);
                
                $messages[] = [
                    'role' => 'tool',
                    'content' => $formattedContent,
                    'tool_call_id' => $result['tool_call_id']
                ];
            }
            
            // Finale Antwort generieren
            $this->addActivity('Generiere finale Antwort...', '', [], [], 'running', 'OpenAI erstellt Antwort');
            $this->notifyActivity($activityCallback);
            
            // System Prompt für finale Formatierung hinzufügen
            $messages[] = [
                'role' => 'system',
                'content' => 'Du bist ein hilfreicher Assistent. Formatiere die Tool-Ergebnisse zu einer natürlichen, lesbaren Antwort. Verwende die Daten um eine strukturierte, benutzerfreundliche Antwort zu erstellen. Antworte auf Deutsch.'
            ];
            
            $finalResponse = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);
            
            $finalContent = $finalResponse->choices[0]->message->content;
            $this->updateLastActivity('success', 'Antwort erstellt');
            $this->notifyActivity($activityCallback);
        } else {
            $finalContent = $assistantMessage->content;
        }
        
        return [
            'ok' => true,
            'data' => $finalContent,
            'activities' => $this->activities,
            'usage' => $response->usage->toArray()
        ];
    }
    
    /**
     * Formatiere Tool-Result für das Sprachmodell
     */
    private function formatToolResultForLLM(array $result): string
    {
        if (isset($result['data']) && is_array($result['data'])) {
            $data = $result['data'];
            
            if (isset($data['items']) && is_array($data['items'])) {
                // Liste von Items formatieren
                $formatted = "Gefunden: " . ($data['count'] ?? count($data['items'])) . " Einträge\n\n";
                
                foreach ($data['items'] as $index => $item) {
                    if ($index >= 5) { // Limit für bessere Lesbarkeit
                        $remaining = count($data['items']) - 5;
                        $formatted .= "... und {$remaining} weitere Einträge\n";
                        break;
                    }
                    
                    $formatted .= ($index + 1) . ". ";
                    
                    // Priorisiere wichtige Felder
                    $priorityFields = ['name', 'title', 'id', 'uuid', 'description'];
                    $displayFields = [];
                    
                    foreach ($priorityFields as $field) {
                        if (isset($item[$field])) {
                            $displayFields[] = $field . ': ' . $item[$field];
                        }
                    }
                    
                    if (!empty($displayFields)) {
                        $formatted .= implode(', ', $displayFields);
                    } else {
                        // Fallback: Erste paar Felder
                        $fields = array_slice($item, 0, 3);
                        $formatted .= implode(', ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($fields), $fields));
                    }
                    
                    $formatted .= "\n";
                }
                
                return $formatted;
            }
        }
        
        // Fallback: JSON-String
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Erstelle intelligenten Plan basierend auf Query
     */
    protected function createIntelligentPlan(string $query): array
    {
        $plan = [];
        $query = strtolower($query);
        
        // Projekte + Aufgaben + Story Points
        if (str_contains($query, 'projekt') && str_contains($query, 'aufgabe')) {
            $plan[] = [
                'tool' => 'plannerproject_get_all',
                'parameters' => [],
                'description' => 'Alle Projekte abrufen'
            ];
            $plan[] = [
                'tool' => 'plannertask_get_all',
                'parameters' => [],
                'description' => 'Alle Aufgaben abrufen'
            ];
        }
        
        // Nur Projekte
        elseif (str_contains($query, 'projekt')) {
            $plan[] = [
                'tool' => 'plannerproject_get_all',
                'parameters' => [],
                'description' => 'Alle Projekte abrufen'
            ];
        }
        
        // Nur Aufgaben
        elseif (str_contains($query, 'aufgabe') || str_contains($query, 'task')) {
            $plan[] = [
                'tool' => 'plannertask_get_all',
                'parameters' => [],
                'description' => 'Alle Aufgaben abrufen'
            ];
        }
        
        // Fallback: Kontext abrufen
        else {
            $plan[] = [
                'tool' => 'get_context',
                'parameters' => [],
                'description' => 'Aktuellen Kontext abrufen'
            ];
        }
        
        return $plan;
    }
    
    /**
     * Führe Tool-Calls mit Live Updates aus
     */
    protected function executeToolCallsWithLiveUpdates(array $toolCalls, callable $activityCallback = null): array
    {
        $results = [];
        
        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall->function->name;
            $parameters = json_decode($toolCall->function->arguments, true);
            
            $this->addActivity(
                "Führe {$toolName} aus...",
                $toolName,
                $parameters,
                [],
                'running',
                "OpenAI orchestriert {$toolName}"
            );
            $this->notifyActivity($activityCallback);
            
            $startTime = microtime(true);
            $result = app(\Platform\Core\Services\ToolExecutor::class)->executeTool($toolName, $parameters);
            $duration = (microtime(true) - $startTime) * 1000;
            
            if ($result['ok']) {
                $this->updateLastActivity('success', 'Erfolgreich ausgeführt', $duration);
                $result['tool_call_id'] = $toolCall->id;
                $results[] = $result;
            } else {
                $this->updateLastActivity('error', 'Fehler: ' . $result['error'], $duration);
            }
            
            $this->notifyActivity($activityCallback);
        }
        
        return $results;
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
        
        // Erweiterte Intent-Erkennung
        if (str_contains($query, 'zeige') || str_contains($query, 'liste') || 
            str_contains($query, 'aufgaben') || str_contains($query, 'tasks') ||
            str_contains($query, 'projekte') || str_contains($query, 'projects') ||
            str_contains($query, 'alle') || str_contains($query, 'all')) {
            $intent = ['action' => 'list', 'entity' => $this->extractEntity($query)];
            \Log::info('🎯 INTENT DETECTED:', $intent);
            return $intent;
        }
        
        if (str_contains($query, 'erstelle') || str_contains($query, 'neue') ||
            str_contains($query, 'create') || str_contains($query, 'new')) {
            $intent = ['action' => 'create', 'entity' => $this->extractEntity($query)];
            \Log::info('🎯 INTENT DETECTED:', $intent);
            return $intent;
        }
        
        if (str_contains($query, 'aktualisiere') || str_contains($query, 'update') ||
            str_contains($query, 'bearbeite') || str_contains($query, 'edit')) {
            $intent = ['action' => 'update', 'entity' => $this->extractEntity($query)];
            \Log::info('🎯 INTENT DETECTED:', $intent);
            return $intent;
        }
        
        // Fallback: Wenn "aufgaben" oder "tasks" erwähnt wird, aber kein klarer Action
        if (str_contains($query, 'aufgaben') || str_contains($query, 'tasks')) {
            $intent = ['action' => 'list', 'entity' => 'task'];
            \Log::info('🎯 FALLBACK INTENT (tasks):', $intent);
            return $intent;
        }
        
        if (str_contains($query, 'projekte') || str_contains($query, 'projects')) {
            $intent = ['action' => 'list', 'entity' => 'project'];
            \Log::info('🎯 FALLBACK INTENT (projects):', $intent);
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
