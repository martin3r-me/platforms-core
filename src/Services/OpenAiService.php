<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    private string $baseUrl = 'https://api.openai.com/v1';

    private function getApiKey(): string
    {
        $apiKey = env('OPENAI_API_KEY');
        
        // Check if API key is valid (starts with 'sk-' and has reasonable length)
        if ($apiKey && str_starts_with($apiKey, 'sk-') && strlen($apiKey) > 20) {
            return $apiKey;
        }
        
        return 'demo-key';
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
        $userInput = strtolower(trim($lastMessage['content'] ?? ''));
        
        // Erweiterte Demo-Antworten mit intelligenten Keywords
        $demoResponses = [
            // Begrüßungen
            'hallo' => 'Hallo! Wie kann ich dir helfen?',
            'moin' => 'Moin! Schön, dass du da bist!',
            'hi' => 'Hi! Was möchtest du wissen?',
            'hey' => 'Hey! Bereit zu arbeiten?',
            'guten tag' => 'Guten Tag! Wie kann ich dir heute helfen?',
            'guten morgen' => 'Guten Morgen! Ein produktiver Tag beginnt!',
            'guten abend' => 'Guten Abend! Zeit für eine Zusammenfassung?',
            
            // Dank & Feedback
            'danke' => 'Gerne! Freut mich, dass ich helfen konnte!',
            'danke freue mich auch' => 'Das freut mich sehr! Zusammen schaffen wir mehr!',
            'danke schön' => 'Bitte sehr! Immer gerne!',
            'vielen dank' => 'Sehr gerne! Was können wir als nächstes angehen?',
            'perfekt' => 'Super! Freut mich, dass es funktioniert!',
            'toll' => 'Das ist großartig! Weiter so!',
            'super' => 'Fantastisch! Lass uns weitermachen!',
            
            // Test & Status
            'test' => 'Test erfolgreich! Das Terminal funktioniert einwandfrei.',
            'hall0' => 'Hallo! Das Terminal funktioniert im Demo-Modus.',
            'funktioniert' => 'Ja, alles läuft perfekt!',
            'status' => 'Alles im grünen Bereich! System läuft stabil.',
            
            // Arbeit & Projekte
            'projekt' => 'Ich kann dir bei Projekten helfen! Möchtest du ein neues Projekt erstellen?',
            'aufgabe' => 'Aufgaben sind wichtig! Soll ich dir zeigen, wie du eine neue Aufgabe anlegst?',
            'okr' => 'OKRs helfen bei der Zielsetzung. Welche Ziele möchtest du definieren?',
            'arbeit' => 'Arbeit ist wichtig! Lass uns produktiv werden!',
            'meeting' => 'Meetings sind wichtig für die Zusammenarbeit. Soll ich dir bei der Planung helfen?',
            
            // Hilfe & Support
            'hilfe' => 'Gerne helfe ich dir! Du kannst mich nach Projekten, Aufgaben, OKRs oder anderen Themen fragen.',
            'help' => 'I can help you with projects, tasks, OKRs, and more. Just ask!',
            'was kann ich' => 'Du kannst mich nach Projekten, Aufgaben, OKRs, Meetings und vielem mehr fragen!',
            'befehle' => 'Verfügbare Befehle: Projekte, Aufgaben, OKRs, Meetings, Status, Hilfe',
            
            // Allgemeine Gespräche
            'wie gehts' => 'Mir geht es gut, danke! Und dir?',
            'wie geht es dir' => 'Sehr gut, danke der Nachfrage! Bereit zu helfen!',
            'alles klar' => 'Alles klar! Was können wir angehen?',
            'ok' => 'Perfekt! Lass uns weitermachen!',
        ];
        
        // Intelligente Suche nach Keywords
        $response = $demoResponses[$userInput] ?? null;
        
        if (!$response) {
            // Suche nach Keywords in der Eingabe
            foreach ($demoResponses as $keyword => $reply) {
                if (str_contains($userInput, $keyword)) {
                    $response = $reply;
                    break;
                }
            }
        }
        
        // Fallback für unbekannte Eingaben
        if (!$response) {
            $response = "Interessant! Du hast '$userInput' geschrieben. Das Terminal funktioniert im Demo-Modus. Du kannst mich nach Projekten, Aufgaben, OKRs oder anderen Themen fragen!";
        }
        
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
