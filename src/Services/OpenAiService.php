<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        
        if (!$this->apiKey) {
            throw new \Exception('OpenAI API Key not found. Please set OPENAI_API_KEY in your .env file.');
        }
    }

    public function chat(array $messages, string $model = 'gpt-3.5-turbo', array $options = []): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
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
                'Authorization' => 'Bearer ' . $this->apiKey,
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
