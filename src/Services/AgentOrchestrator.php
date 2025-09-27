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
        1. FÜHRE ALLE TOOLS DIREKT AUS - KEINE ZWISCHENFRAGEN!
        2. Verwende IMMER mehrere Tools in der richtigen Reihenfolge
        3. Für Projekt-Anfragen: Erst Projekte abrufen, dann Project Slots, dann Tasks
        4. Für Slot-Anfragen: Erst Project Slots abrufen, dann Tasks in den Slots
        5. Für Task-Anfragen: Erst Tasks abrufen, dann Relations (project, projectslot, etc.)
        6. Kombiniere die Ergebnisse zu einer vollständigen Antwort
        
        WICHTIGE PRINZIPIEN:
        - Du bist ein intelligenter Agent, der selbst entscheidet welche Tools zu verwenden
        - Analysiere die Anfrage und wähle die passenden Tools aus
        - Führe alle notwendigen Tools aus um die Anfrage zu beantworten
        - Verwende die verfügbaren Tools intelligent und in der richtigen Reihenfolge
        
        VERFÜGBARE TOOLS:
        - Du hast Zugriff auf alle verfügbaren Tools für Datenbankoperationen
        - Verwende die Tools die für die Anfrage relevant sind
        - Führe alle notwendigen Schritte aus um die Anfrage zu beantworten
        
        WICHTIG: 
        - Arbeite direkt und effizient!
        - Gib erst am Ende eine vollständige Antwort!
        - Verwende die Tools intelligent basierend auf der Anfrage!";
        
        // Chat-Historie laden für besseren Kontext (Token-basiert)
        $chatHistory = $this->loadChatHistory();
        
        // System Prompt Token schätzen
        $systemTokens = $this->estimateTokens($systemPrompt);
        $availableTokens = 12000 - $systemTokens; // 16k - 4k für Tools = 12k verfügbar
        
        // Messages für OpenAI
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        
        $usedTokens = $systemTokens;
        
        // Chat-Historie hinzufügen (Token-basiert)
        foreach ($chatHistory as $message) {
            $messageTokens = $message['tokens'] ?? $this->estimateTokens($message['content']);
            
            if ($usedTokens + $messageTokens > $availableTokens) {
                \Log::info("📚 Token-Limit erreicht, stoppe bei Nachricht:", [
                    'used_tokens' => $usedTokens,
                    'available_tokens' => $availableTokens,
                    'message_tokens' => $messageTokens
                ]);
                break;
            }
            
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content']
            ];
            
            $usedTokens += $messageTokens;
        }
        
        // Aktuelle Query hinzufügen
        $messages[] = ['role' => 'user', 'content' => $query];
        
        \Log::info("📚 Finale Token-Verwendung:", [
            'system_tokens' => $systemTokens,
            'history_tokens' => $usedTokens - $systemTokens,
            'total_tokens' => $usedTokens,
            'available_tokens' => $availableTokens
        ]);
        
        // DEBUG: Logge was OpenAI bekommt
        \Log::info("🤖 SENDING TO OPENAI:", [
            'tools_count' => count($tools),
            'tools_names' => array_map(fn($t) => $t['function']['name'] ?? 'unknown', $tools),
            'query' => $query,
            'planner_tools' => array_filter(array_map(fn($t) => $t['function']['name'] ?? 'unknown', $tools), fn($name) => str_contains($name, 'planner')),
            'create_tools' => array_filter(array_map(fn($t) => $t['function']['name'] ?? 'unknown', $tools), fn($name) => str_contains($name, '_create'))
        ]);
        
        // OpenAI API mit Tools
        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',  // OpenAI entscheidet intelligent!
            'max_tokens' => 2000,
            'temperature' => 0.7,
        ]);
        
        $assistantMessage = $response->choices[0]->message;
        
        // DEBUG: Logge OpenAI's Response
        \Log::info("🤖 OPENAI RESPONSE:", [
            'has_tool_calls' => !empty($assistantMessage->toolCalls),
            'tool_calls_count' => count($assistantMessage->toolCalls ?? []),
            'tool_calls' => array_map(fn($tc) => [
                'name' => $tc->function->name ?? 'unknown',
                'arguments' => $tc->function->arguments ?? '{}'
            ], $assistantMessage->toolCalls ?? []),
            'content' => $assistantMessage->content,
            'why_only_2_tools' => 'DEBUG: Warum nur 2 Tools?'
        ]);
        
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
        // Debug: Logge die Result-Struktur
        \Log::info("🔍 Tool Result Structure:", ['result' => $result]);
        
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
                
                \Log::info("📝 Formatted Result:", ['formatted' => $formatted]);
                return $formatted;
            }
        }
        
        // Fallback: JSON-String
        $fallback = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        \Log::info("🔄 Using Fallback:", ['fallback' => $fallback]);
        return $fallback;
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
    
    /**
     * Lade Chat-Historie für besseren Kontext (Token-basiert)
     */
    private function loadChatHistory(): array
    {
        try {
            // Token-Limits für verschiedene Modelle
            $maxTokens = 4000; // GPT-4o-mini hat ~16k context, wir reservieren Platz für Tools
            $currentTokens = 0;
            $messages = [];
            
            // Lade Nachrichten rückwärts bis Token-Limit erreicht
            $chatMessages = \DB::table('core_chat_messages')
                ->orderBy('created_at', 'desc')
                ->get();
                
            foreach ($chatMessages as $message) {
                $messageTokens = $this->estimateTokens($message->content);
                
                // Prüfe ob wir noch Platz haben
                if ($currentTokens + $messageTokens > $maxTokens) {
                    break;
                }
                
                $messages[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at,
                    'tokens' => $messageTokens
                ];
                
                $currentTokens += $messageTokens;
            }
            
            // Nachrichten in richtiger Reihenfolge (älteste zuerst)
            $messages = array_reverse($messages);
            
            \Log::info("📚 Chat-Kontext geladen:", [
                'messages_count' => count($messages),
                'total_tokens' => $currentTokens,
                'max_tokens' => $maxTokens
            ]);
            
            return $messages;
            
        } catch (\Exception $e) {
            \Log::warning("Chat-Historie konnte nicht geladen werden: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Schätze Token-Anzahl für Text (grobe Schätzung)
     */
    private function estimateTokens(string $text): int
    {
        // Grobe Schätzung: 1 Token ≈ 4 Zeichen für Deutsch
        // Besser wäre tiktoken, aber für jetzt reicht das
        return ceil(strlen($text) / 4);
    }
}
