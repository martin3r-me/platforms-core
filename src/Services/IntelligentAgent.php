<?php

namespace Platform\Core\Services;

use OpenAI\Client;
use OpenAI\Factory;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatMessage;
use Platform\Core\Services\AgentOrchestrator;
use Platform\Core\Services\AgentFallbackService;
use Illuminate\Support\Facades\Event;

class IntelligentAgent
{
    protected Client $client;
    protected ToolRegistry $toolRegistry;
    protected ToolExecutor $toolExecutor;
    protected AgentOrchestrator $orchestrator;
    protected AgentFallbackService $fallbackService;
    
    public function __construct(ToolRegistry $toolRegistry, ToolExecutor $toolExecutor, AgentOrchestrator $orchestrator, AgentFallbackService $fallbackService)
    {
        $this->toolRegistry = $toolRegistry;
        $this->toolExecutor = $toolExecutor;
        $this->orchestrator = $orchestrator;
        $this->fallbackService = $fallbackService;
        
        $factory = (new Factory())->withApiKey(env('OPENAI_API_KEY'));
        
        // Organization nur hinzufügen wenn gesetzt
        if (env('OPENAI_ORGANIZATION')) {
            $factory->withOrganization(env('OPENAI_ORGANIZATION'));
        }
        
        $this->client = $factory->make();
    }
    
    /**
     * Verarbeite eine Nachricht und generiere eine Antwort
     */
    public function processMessage(string $message, ?int $chatId = null): array
    {
        \Log::info("🚀 IntelligentAgent::processMessage START", [
            'message' => $message,
            'chatId' => $chatId
        ]);
        
        try {
            // Event: Start der Verarbeitung
            Event::dispatch('agent.step', [
                'message' => '🔄 Analysiere Anfrage...',
                'step' => 1,
                'total' => 5
            ]);
            
            // Prüfe ob OpenAI verfügbar ist
            if (!$this->fallbackService->isOpenAIAvailable()) {
                \Log::warning("🤖 OPENAI NOT AVAILABLE, USING FALLBACK");
                return $this->fallbackService->executeFallback($message);
            }
            
            // Event: OpenAI verfügbar
            Event::dispatch('agent.step', [
                'message' => '✅ OpenAI verfügbar',
                'step' => 2,
                'total' => 5
            ]);
            
            // Prüfe ob es eine komplexe Query ist
            $isComplex = $this->isComplexQuery($message);
            \Log::info("🔍 Query Type Detection", [
                'isComplex' => $isComplex,
                'message' => $message
            ]);
            
            if ($isComplex) {
                \Log::info("🎯 Using COMPLEX Query Processing");
                Event::dispatch('agent.step', [
                    'message' => '🎭 Verwende Orchestrierung...',
                    'step' => 3,
                    'total' => 5
                ]);
                return $this->processComplexQuery($message, $chatId);
            }
            
            \Log::info("💬 Using SIMPLE Query Processing");
            Event::dispatch('agent.step', [
                'message' => '💬 Verwende einfache Verarbeitung...',
                'step' => 3,
                'total' => 5
            ]);
            return $this->processSimpleQuery($message, $chatId);
            
        } catch (\Exception $e) {
            \Log::error('IntelligentAgent Fehler:', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            
            // Versuche Fallback bei kritischen Fehlern
            try {
                return $this->fallbackService->executeFallback($message);
            } catch (\Exception $fallbackError) {
                return [
                    'ok' => false,
                    'error' => 'Fehler beim Verarbeiten: ' . $e->getMessage(),
                    'fallback_error' => $fallbackError->getMessage()
                ];
            }
        }
    }
    
    /**
     * Prüfe ob es eine komplexe Query ist
     */
    protected function isComplexQuery(string $message): bool
    {
        $complexKeywords = [
            'zeige', 'liste', 'alle', 'übersicht', 'summe', 'berechnen',
            'tasks', 'projekte', 'okrs', 'sprints', 'story points',
            'aufgaben', 'projekt', 'zusammenfassung', 'statistik',
            'kannst', 'gib', 'geben', 'slots', 'aufgabe'
        ];
        $message = strtolower($message);

        foreach ($complexKeywords as $keyword) {
            if (str_contains($message, $keyword)) {
                \Log::info("🎯 COMPLEX QUERY DETECTED:", ['keyword' => $keyword, 'message' => $message]);
                return true;
            }
        }

        \Log::info("❓ SIMPLE QUERY:", ['message' => $message]);
        return false;
    }
    
    /**
     * Verarbeite komplexe Queries mit Orchestrierung
     */
    protected function processComplexQuery(string $message, ?int $chatId = null): array
    {
        $result = $this->orchestrator->executeComplexQuery($message, function($activity) use ($chatId) {
            // Live Activity Updates an Chat senden
            if ($chatId) {
                $this->saveMessage($chatId, 'assistant', $activity->getFormattedMessage());
            }
        });
        
        // Chat wird von CursorSidebar gespeichert - NICHT hier!
        
        return $result;
    }
    
    /**
     * Verarbeite einfache Queries mit ChatGPT
     */
    protected function processSimpleQuery(string $message, ?int $chatId = null): array
    {
        \Log::info("💬 processSimpleQuery START", [
            'message' => $message,
            'chatId' => $chatId
        ]);
        
        // Chat-Verlauf laden
        $messages = $this->loadChatHistory($chatId);
        $messages[] = ['role' => 'user', 'content' => $message];
        
        // Tools laden (IMMER nur Core Tools für 2-Step System)
        $tools = $this->toolRegistry->getCoreToolsOnly();
        
        // DEBUG: Prüfe ob wirklich nur Core Tools geladen werden
        if (count($tools) > 5) {
            \Log::error("🚨 FEHLER: Agent bekommt zu viele Tools!", [
                'count' => count($tools),
                'expected' => 5,
                'tools' => array_column($tools, 'function.name')
            ]);
        }
        \Log::info("🔧 Core Tools loaded", [
            'count' => count($tools), 
            'type' => 'core-only',
            'tool_names' => array_column($tools, 'function.name')
        ]);
        
        // OpenAI API aufrufen mit Tools
        \Log::info("🤖 OPENAI API REQUEST DEBUG:", [
            'model' => 'gpt-4o-mini',
            'messages_count' => count($messages),
            'tools_count' => count($tools),
            'tool_choice' => 'auto',
            'max_tokens' => 2000,
            'temperature' => 0.7
        ]);
        
        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_tokens' => 2000,
            'temperature' => 0.7,
        ]);
        
        \Log::info("🤖 OPENAI API RESPONSE DEBUG:", [
            'has_choices' => !empty($response->choices),
            'choices_count' => count($response->choices ?? []),
            'has_usage' => !empty($response->usage),
            'usage_tokens' => $response->usage?->toArray() ?? 'N/A'
        ]);
        
        $assistantMessage = $response->choices[0]->message;
        \Log::info("🤖 OpenAI Response", [
            'hasToolCalls' => !empty($assistantMessage->toolCalls),
            'toolCallsCount' => count($assistantMessage->toolCalls ?? []),
            'content' => $assistantMessage->content
        ]);
        
        // Tool-Calls verarbeiten
        if (!empty($assistantMessage->toolCalls)) {
            \Log::info("🔧 Executing Tool Calls", [
                'count' => count($assistantMessage->toolCalls)
            ]);
            $toolResults = $this->executeTools($assistantMessage->toolCalls);
            \Log::info("✅ Tool Results", [
                'count' => count($toolResults),
                'results' => $toolResults
            ]);
            
            // Tool-Ergebnisse an OpenAI senden
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
            \Log::info("🔄 Generating Final Response", [
                'messagesCount' => count($messages)
            ]);
            
            $finalResponse = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);
            
            $finalContent = $finalResponse->choices[0]->message->content;
            \Log::info("✅ Final Response Generated", [
                'content' => $finalContent
            ]);
        } else {
            $finalContent = $assistantMessage->content;
        }
        
            // Chat wird von CursorSidebar gespeichert - NICHT hier!
        
        return [
            'ok' => true,
            'data' => $finalContent,
            'usage' => $response->usage->toArray(),
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
     * Führe Tool-Calls aus
     */
    protected function executeTools(array $toolCalls): array
    {
        $results = [];
        
        foreach ($toolCalls as $toolCall) {
            $toolName = $toolCall->function->name;
            $parameters = json_decode($toolCall->function->arguments, true);
            
            $result = $this->toolExecutor->executeTool($toolName, $parameters);
            $result['tool_call_id'] = $toolCall->id;
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Lade Chat-Verlauf
     */
    protected function loadChatHistory(?int $chatId): array
    {
        if (!$chatId) {
            return [];
        }
        
        $messages = CoreChatMessage::where('core_chat_id', $chatId)
            ->orderBy('id')
            ->get();
            
        $history = [];
        foreach ($messages as $message) {
            $history[] = [
                'role' => $message->role,
                'content' => $message->content
            ];
        }
        
        return $history;
    }
    
    /**
     * Bestimme welche Tools für diesen Chat geladen werden sollen
     */
    protected function getToolsForChat(int $chatId): array
    {
        // Prüfe ob bereits Module-spezifische Tools geladen wurden
        $chat = \Platform\Core\Models\CoreChat::find($chatId);
        if (!$chat) {
            return $this->toolRegistry->getCoreToolsOnly();
        }
        
        // Prüfe Chat-Historie auf discover_tools Calls
        $messages = \Platform\Core\Models\CoreChatMessage::where('chat_id', $chatId)
            ->where('role', 'assistant')
            ->where('content', 'like', '%discover_tools%')
            ->latest()
            ->first();
            
        if ($messages) {
            // Extrahiere das Modul aus dem discover_tools Call
            $content = $messages->content;
            if (preg_match('/discover_tools\(["\']([^"\']+)["\']\)/', $content, $matches)) {
                $module = $matches[1];
                $moduleTools = $this->toolRegistry->getToolsForModule($module);
                if (!empty($moduleTools)) {
                    \Log::info("🔧 Using discovered tools for module", ['module' => $module, 'count' => count($moduleTools)]);
                    return $moduleTools;
                }
            }
        }
        
        // Fallback: Nur Core Tools
        return $this->toolRegistry->getCoreToolsOnly();
    }
    
    /**
     * Speichere Nachricht
     */
    protected function saveMessage(int $chatId, string $role, string $content): void
    {
        CoreChatMessage::create([
            'core_chat_id' => $chatId,
            'role' => $role,
            'content' => $content,
            'meta' => [],
            'tokens_in' => 0,
            'tokens_out' => 0,
        ]);
    }
}