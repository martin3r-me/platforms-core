<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    private string $baseUrl = 'https://api.openai.com/v1';

    private function getApiKey(): string
    {
        return env('OPENAI_API_KEY') ?: 'demo-key';
    }

    public function chat(array $messages, string $model = 'gpt-3.5-turbo', array $options = []): array
    {
        // Demo mode - return mock responses
        if ($this->getApiKey() === 'demo-key') {
            $lastMessage = end($messages);
            $userInput = $lastMessage['content'] ?? '';
            
            $demoResponses = [
                'hallo' => 'Hallo! Wie kann ich dir helfen?',
                'moin' => 'Moin! Schön, dass du da bist!',
                'hi' => 'Hi! Was möchtest du wissen?',
                'test' => 'Test erfolgreich! Das Terminal funktioniert.',
                'hall0' => 'Hallo! Das Terminal funktioniert im Demo-Modus.',
            ];
            
            $response = $demoResponses[strtolower($userInput)] ?? 
                "Demo-Antwort: Du hast '$userInput' geschrieben. Das Terminal funktioniert, aber es ist kein echter OpenAI API Key konfiguriert.";
            
            return [
                'content' => $response,
                'usage' => ['total_tokens' => 50],
                'model' => 'demo-model',
            ];
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $options['max_tokens'] ?? 1000,
                'temperature' => $options['temperature'] ?? 0.7,
                'stream' => false,
            ]);

            if ($response->failed()) {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $data = $response->json();
            
            return [
                'content' => $data['choices'][0]['message']['content'] ?? '',
                'usage' => $data['usage'] ?? [],
                'model' => $data['model'] ?? $model,
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    public function getModels(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getApiKey(),
            ])->get($this->baseUrl . '/models');

            if ($response->failed()) {
                throw new \Exception('Failed to fetch OpenAI models');
            }

            return $response->json()['data'] ?? [];

        } catch (\Exception $e) {
            Log::error('OpenAI Models Error', [
                'message' => $e->getMessage(),
            ]);
            
            return [];
        }
    }
}
