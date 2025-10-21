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
        // Demo mode - return mock responses with streaming simulation
        if ($this->getApiKey() === 'demo-key') {
            return $this->getDemoResponse($messages);
        }
        
        try {
            $response = Http::timeout(20) // LLM-Timeout: 20s
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->getApiKey(),
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl . '/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $options['max_tokens'] ?? 1000,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'stream' => $options['stream'] ?? false,
                    'tools' => $options['tools'] ?? null,
                    'tool_choice' => $options['tool_choice'] ?? 'auto',
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
                'tool_calls' => $data['choices'][0]['message']['tool_calls'] ?? null,
                'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    private function getDemoResponse(array $messages): array
    {
        $lastMessage = end($messages);
        $userInput = strtolower($lastMessage['content'] ?? '');
        
        $demoResponses = [
            'hallo' => 'Hallo! Wie kann ich dir helfen?',
            'moin' => 'Moin! Schön, dass du da bist!',
            'hi' => 'Hi! Was möchtest du wissen?',
            'test' => 'Test erfolgreich! Das Terminal funktioniert.',
            'hall0' => 'Hallo! Das Terminal funktioniert im Demo-Modus.',
            'projekt' => 'Ich kann dir bei Projekten helfen! Möchtest du ein neues Projekt erstellen?',
            'aufgabe' => 'Aufgaben sind wichtig! Soll ich dir zeigen, wie du eine neue Aufgabe anlegst?',
            'okr' => 'OKRs helfen bei der Zielsetzung. Welche Ziele möchtest du definieren?',
        ];
        
        $response = $demoResponses[$userInput] ?? 
            "Demo-Antwort: Du hast '$userInput' geschrieben. Das Terminal funktioniert, aber es ist kein echter OpenAI API Key konfiguriert.";
        
        return [
            'content' => $response,
            'usage' => ['total_tokens' => 50],
            'model' => 'demo-model',
            'tool_calls' => null,
            'finish_reason' => 'stop',
        ];
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
