<?php

namespace Platform\Core\Services;

use OpenAI\Client;
use OpenAI\Factory;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatMessage;

class IntelligentAgent
{
    protected Client $client;
    protected ToolRegistry $toolRegistry;
    protected ToolExecutor $toolExecutor;
    
    public function __construct(ToolRegistry $toolRegistry, ToolExecutor $toolExecutor)
    {
        $this->toolRegistry = $toolRegistry;
        $this->toolExecutor = $toolExecutor;
        
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
        try {
            // Chat-Verlauf laden
            $messages = $this->loadChatHistory($chatId);
            $messages[] = ['role' => 'user', 'content' => $message];
            
            // Tools laden
            $tools = $this->toolRegistry->getAllTools();
            
            // OpenAI API aufrufen mit Tools
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ]);
            
            $assistantMessage = $response->choices[0]->message;
            
            // Tool-Calls verarbeiten
            if (!empty($assistantMessage->toolCalls)) {
                $toolResults = $this->executeTools($assistantMessage->toolCalls);
                
                // Tool-Ergebnisse an OpenAI senden
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
                $finalResponse = $this->client->chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'max_tokens' => 1000,
                    'temperature' => 0.7,
                ]);
                
                $finalContent = $finalResponse->choices[0]->message->content;
            } else {
                $finalContent = $assistantMessage->content;
            }
            
            // Chat speichern
            if ($chatId) {
                $this->saveMessage($chatId, 'user', $message);
                $this->saveMessage($chatId, 'assistant', $finalContent);
            }
            
            return [
                'ok' => true,
                'content' => $finalContent,
                'usage' => $response->usage->toArray(),
            ];
            
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
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