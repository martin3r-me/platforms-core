<?php

namespace Platform\Core\Contracts;

/**
 * Contract für LLM-Provider
 * 
 * Abstrahiert verschiedene LLM-Provider (OpenAI, Anthropic, etc.)
 */
interface LLMProviderContract
{
    /**
     * Provider-Name (z.B. 'openai', 'anthropic')
     */
    public function getName(): string;

    /**
     * Führt einen Chat-Request aus (non-streaming)
     * 
     * @param array $messages Array von Messages
     * @param array $options Optionen (model, temperature, max_tokens, etc.)
     * @return array{content: string, usage: array, model: string, tool_calls: ?array}
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Führt einen Chat-Request aus (streaming)
     * 
     * @param array $messages Array von Messages
     * @param callable $onDelta Callback für jedes Delta-Event
     * @param array $options Optionen
     * @return void
     */
    public function streamChat(array $messages, callable $onDelta, array $options = []): void;

    /**
     * Gibt verfügbare Models zurück
     * 
     * @return array Array von Model-Namen
     */
    public function getAvailableModels(): array;

    /**
     * Gibt Standard-Model zurück
     */
    public function getDefaultModel(): string;

    /**
     * Prüft ob Provider verfügbar/konfiguriert ist
     */
    public function isAvailable(): bool;
}

