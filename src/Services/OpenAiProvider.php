<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\LLMProviderContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Provider Implementation
 * 
 * Wrapper um OpenAiService für LLMProviderContract
 */
class OpenAiProvider implements LLMProviderContract
{
    public const DEFAULT_MODEL = 'gpt-4o-mini-2024-07-18';

    public function __construct(
        private OpenAiService $openAiService
    ) {}

    public function getName(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? self::DEFAULT_MODEL;
        $result = $this->openAiService->chat($messages, $model, $options);
        
        return [
            'content' => $result['content'] ?? '',
            'usage' => $result['usage'] ?? [],
            'model' => $result['model'] ?? $model,
            'tool_calls' => $result['tool_calls'] ?? null,
        ];
    }

    public function streamChat(array $messages, callable $onDelta, array $options = []): void
    {
        $model = $options['model'] ?? self::DEFAULT_MODEL;
        $this->openAiService->streamChat($messages, $onDelta, $model, $options);
    }

    public function getAvailableModels(): array
    {
        return [
            'gpt-4o-mini-2024-07-18',
            'gpt-4o-2024-08-06',
            'gpt-4-turbo',
            'gpt-3.5-turbo',
        ];
    }

    public function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    public function isAvailable(): bool
    {
        // Prüfe ob API-Key konfiguriert ist
        $apiKey = config('services.openai.api_key');
        return !empty($apiKey);
    }
}

