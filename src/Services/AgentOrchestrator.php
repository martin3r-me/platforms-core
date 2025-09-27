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

        // 1. OpenAI Agent entscheidet selbst welche Tools er nutzt
        $this->addActivity('Analysiere Anfrage mit KI...', '', [], [], 'running', 'OpenAI Agent versteht die Anfrage');
        $this->notifyActivity($activityCallback);
        
        $result = $this->executeWithOpenAI($query, $activityCallback);
        
        // Completion Event
        Event::dispatch('agent.activity.complete');

        return $result;
    }
    
    /**
     * OpenAI Orchestrierung mit Live Updates
     */
    protected function executeWithOpenAI(string $query, callable $activityCallback = null): array
    {
        // Lade alle verfügbaren Tools
        $tools = app(\Platform\Core\Services\ToolRegistry::class)->getAllTools();
        
        // OpenAI Client
        $factory = (new \OpenAI\Factory())->withApiKey(env('OPENAI_API_KEY'));
        if (env('OPENAI_ORGANIZATION')) {
            $factory->withOrganization(env('OPENAI_ORGANIZATION'));
        }
        $client = $factory->make();
        
        // System Prompt für intelligente Orchestrierung
        $systemPrompt = "Du bist ein intelligenter Agent mit Zugriff auf verschiedene Tools. 
        Analysiere die User-Anfrage und führe die notwendigen Tools in der richtigen Reihenfolge aus.
        Du kannst mehrere Tools nacheinander verwenden um komplexe Anfragen zu beantworten.
        Führe Tools sequenziell aus und verwende die Ergebnisse für weitere Schritte.";
        
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
                $messages[] = [
                    'role' => 'tool',
                    'content' => json_encode($result),
                    'tool_call_id' => $result['tool_call_id']
                ];
            }
            
            // Finale Antwort generieren
            $this->addActivity('Generiere finale Antwort...', '', [], [], 'running', 'OpenAI erstellt Antwort');
            $this->notifyActivity($activityCallback);
            
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
            'content' => $finalContent,
            'activities' => $this->activities,
            'usage' => $response->usage->toArray()
        ];
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
