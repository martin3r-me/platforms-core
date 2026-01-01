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
    
    /** @var array<string, string> Legacy-Name → Neuer Name Mapping */
    private array $legacyMapping = [];

    /**
     * Registriert ein Tool
     * 
     * Erstellt automatisch Legacy-Mappings für Backwards-Kompatibilität
     */
    public function register(ToolContract $tool): void
    {
        $name = $tool->getName();
        
        if (isset($this->tools[$name])) {
            Log::warning("[ToolRegistry] Tool '{$name}' wird überschrieben");
        }

        $this->tools[$name] = $tool;
        
        // Backwards-Kompatibilität: Erstelle Legacy-Mapping
        $legacyName = $this->createLegacyName($name);
        if ($legacyName && $legacyName !== $name) {
            $this->legacyMapping[$legacyName] = $name;
        }
    }
    
    /**
     * Erstellt Legacy-Namen für Backwards-Kompatibilität
     * 
     * Mapping: GET → list, POST → create, PUT → update, DELETE → delete
     */
    private function createLegacyName(string $name): ?string
    {
        // REST → Legacy Mapping
        $mappings = [
            '.GET' => '.list',
            '.POST' => '.create',
            '.PUT' => '.update',
            '.DELETE' => '.delete',
        ];
        
        foreach ($mappings as $rest => $legacy) {
            if (str_ends_with($name, $rest)) {
                return str_replace($rest, $legacy, $name);
            }
        }
        
        // .get → .list (für core.context.get, core.user.get)
        if (str_ends_with($name, '.get')) {
            return str_replace('.get', '.list', $name);
        }
        
        return null;
    }

    /**
     * Gibt ein Tool zurück
     * 
     * Unterstützt Backwards-Kompatibilität: Legacy-Namen werden automatisch gemappt
     */
    public function get(string $name): ?ToolContract
    {
        // Direkter Zugriff
        if (isset($this->tools[$name])) {
            return $this->tools[$name];
        }
        
        // Legacy-Mapping prüfen
        if (isset($this->legacyMapping[$name])) {
            $mappedName = $this->legacyMapping[$name];
            Log::debug("[ToolRegistry] Legacy-Name '{$name}' → '{$mappedName}' gemappt");
            return $this->tools[$mappedName] ?? null;
        }
        
        // Dynamisches Legacy-Mapping (falls noch nicht registriert)
        $legacyName = $this->createLegacyName($name);
        if ($legacyName && isset($this->tools[$legacyName])) {
            $this->legacyMapping[$name] = $legacyName;
            return $this->tools[$legacyName];
        }
        
        return null;
    }

    /**
     * Prüft ob ein Tool registriert ist
     * 
     * Unterstützt Backwards-Kompatibilität: Legacy-Namen werden automatisch gemappt
     */
    public function has(string $name): bool
    {
        // Direkter Zugriff
        if (isset($this->tools[$name])) {
            return true;
        }
        
        // Legacy-Mapping prüfen
        if (isset($this->legacyMapping[$name])) {
            return true;
        }
        
        // Dynamisches Legacy-Mapping
        $legacyName = $this->createLegacyName($name);
        if ($legacyName && isset($this->tools[$legacyName])) {
            $this->legacyMapping[$name] = $legacyName;
            return true;
        }
        
        return false;
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

