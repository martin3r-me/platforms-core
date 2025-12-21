<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Illuminate\Support\Facades\Log;

/**
 * Zentrale Registrierung für alle Tools
 * 
 * Singleton-Service, der alle Tools verwaltet und zur Verfügung stellt
 */
class ToolRegistry
{
    /** @var array<string, ToolContract> */
    private array $tools = [];

    /**
     * Registriert ein Tool
     */
    public function register(ToolContract $tool): void
    {
        $name = $tool->getName();
        
        if (isset($this->tools[$name])) {
            Log::warning("[ToolRegistry] Tool '{$name}' wird überschrieben");
        }

        $this->tools[$name] = $tool;
        Log::debug("[ToolRegistry] Tool '{$name}' registriert");
    }

    /**
     * Gibt ein Tool zurück
     */
    public function get(string $name): ?ToolContract
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Prüft ob ein Tool registriert ist
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Gibt alle registrierten Tools zurück
     * 
     * @return array<string, ToolContract>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Gibt alle Tool-Namen zurück
     * 
     * @return array<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }
}

