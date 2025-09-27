<?php

namespace Platform\Core\Services;

use OpenAI\Client;
use OpenAI\Factory;
use Platform\Core\Models\CoreChat;
use Platform\Core\Models\CoreChatMessage;

class IntelligentAgent
{
    protected Client $client;
    
    public function __construct()
    {
        $this->client = (new Factory())
            ->withApiKey(config('app.openai_api_key'))
            ->withOrganization(config('app.openai_organization'))
            ->make();
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
            
            // OpenAI API aufrufen - nur Chat, keine Tools
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 2000,
                'temperature' => 0.7,
            ]);
            
            $content = $response->choices[0]->message->content;
            
            // Chat speichern
            if ($chatId) {
                $this->saveMessage($chatId, 'user', $message);
                $this->saveMessage($chatId, 'assistant', $content);
            }
            
            return [
                'ok' => true,
                'content' => $content,
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