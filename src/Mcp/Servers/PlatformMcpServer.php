<?php

namespace Platform\Core\Mcp\Servers;

use Laravel\Mcp\Server;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolLoader;
use Platform\Core\Mcp\Adapters\ToolContractAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Platform MCP Server
 * 
 * Lädt automatisch alle Tools aus dem ToolRegistry und macht sie als MCP Tools verfügbar.
 * Unterstützt sowohl ToolContract Tools (via Adapter) als auch direkte MCP Tools.
 */
class PlatformMcpServer extends Server
{
    public string $name = 'Platform MCP Server';
    public string $version = '1.0.0';
    public string $instructions = 'Platform MCP Server. Bietet Zugriff auf alle registrierten Tools.';

    /**
     * Zusätzliche direkte MCP Tools (können in abgeleiteten Klassen überschrieben werden)
     * 
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    public array $additionalTools = [];

    /**
     * Resources (können in abgeleiteten Klassen überschrieben werden)
     * 
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    public array $resources = [];

    /**
     * Prompts (können in abgeleiteten Klassen überschrieben werden)
     * 
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    public array $prompts = [];

    /**
     * Boot-Methode - lädt Tools dynamisch beim Server-Start
     */
    public function boot(): void
    {
        try {
            // Parent boot() aufrufen
            parent::boot();
        } catch (\Throwable $e) {
            Log::error("[PlatformMcpServer] Fehler beim Parent-Boot", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Weiter machen, auch wenn Parent-Boot fehlschlägt
        }
        
        // 1. Lade alle ToolContract Tools aus Registry und wrappe sie
        try {
            $registry = app(ToolRegistry::class);
            
            // WICHTIG: Stelle sicher, dass Tools geladen sind (falls Registry noch leer ist)
            // Dies berührt das interne System nicht - wir nutzen nur die vorhandenen Methoden
            if (count($registry->all()) === 0) {
                try {
                    // Core-Tools laden
                    $coreTools = ToolLoader::loadCoreTools();
                    foreach ($coreTools as $tool) {
                        try {
                            if (!$registry->has($tool->getName())) {
                                $registry->register($tool);
                            }
                        } catch (\Throwable $e) {
                            Log::warning("[PlatformMcpServer] Core-Tool konnte nicht registriert werden", [
                                'tool' => get_class($tool),
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    // Module-Tools laden
                    $modulesPath = realpath(__DIR__ . '/../../../../modules');
                    if ($modulesPath && is_dir($modulesPath)) {
                        $moduleTools = ToolLoader::loadFromAllModules($modulesPath);
                        foreach ($moduleTools as $tool) {
                            try {
                                if (!$registry->has($tool->getName())) {
                                    $registry->register($tool);
                                }
                            } catch (\Throwable $e) {
                                Log::warning("[PlatformMcpServer] Module-Tool konnte nicht registriert werden", [
                                    'tool' => get_class($tool),
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("[PlatformMcpServer] Tool-Loading fehlgeschlagen", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            // Jetzt alle Tools aus Registry holen und wrappen
            $toolContractTools = $registry->all();
            
            foreach ($toolContractTools as $toolContract) {
                try {
                    // Wrappe ToolContract als MCP Tool
                    $adapter = new ToolContractAdapter($toolContract);
                    $this->addTool($adapter);
                } catch (\Throwable $e) {
                    Log::warning("[PlatformMcpServer] Tool konnte nicht als MCP Tool gewrappt werden", [
                        'tool' => $toolContract->getName(),
                        'error' => $e->getMessage(),
                        'trace' => substr($e->getTraceAsString(), 0, 500)
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("[PlatformMcpServer] ToolRegistry konnte nicht geladen werden", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Weiter machen, auch wenn Registry nicht geladen werden kann
        }
        
        // 2. Füge zusätzliche direkte MCP Tools hinzu
        foreach ($this->additionalTools as $toolClass) {
            try {
                if (class_exists($toolClass)) {
                    $this->addTool($toolClass);
                } else {
                    Log::warning("[PlatformMcpServer] MCP Tool-Klasse nicht gefunden", [
                        'class' => $toolClass
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning("[PlatformMcpServer] Fehler beim Hinzufügen von MCP Tool", [
                    'class' => $toolClass,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
