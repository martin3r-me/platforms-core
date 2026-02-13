<?php

namespace Platform\Core\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
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
    protected string $name = 'Platform MCP Server';
    protected string $version = '1.0.0';
    protected string $instructions = 'Platform MCP Server. Bietet Zugriff auf alle registrierten Tools.';

    /**
     * Zusätzliche direkte MCP Tools (können in abgeleiteten Klassen überschrieben werden)
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $additionalTools = [];

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
    }

    /**
     * Boot-Methode - lädt Tools dynamisch beim Server-Start
     */
    public function boot(): void
    {
        try {
            parent::boot();
        } catch (\Throwable $e) {
            Log::error("[PlatformMcpServer] Fehler beim Parent-Boot", [
                'error' => $e->getMessage(),
            ]);
        }

        // Lade alle ToolContract Tools aus Registry und wrappe sie
        try {
            $registry = app(ToolRegistry::class);

            // Stelle sicher, dass Tools geladen sind
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
                            Log::warning("[PlatformMcpServer] Core-Tool Registrierung fehlgeschlagen", [
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
                                Log::warning("[PlatformMcpServer] Module-Tool Registrierung fehlgeschlagen", [
                                    'tool' => get_class($tool),
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("[PlatformMcpServer] Tool-Loading fehlgeschlagen", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Alle Tools aus Registry holen und wrappen
            $toolContractTools = $registry->all();

            foreach ($toolContractTools as $toolContract) {
                try {
                    $adapter = new ToolContractAdapter($toolContract);
                    $this->addTool($adapter);
                } catch (\Throwable $e) {
                    Log::warning("[PlatformMcpServer] Tool-Wrapping fehlgeschlagen", [
                        'tool' => $toolContract->getName(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("[PlatformMcpServer] ToolRegistry konnte nicht geladen werden", [
                'error' => $e->getMessage(),
            ]);
        }

        // Zusätzliche direkte MCP Tools hinzufügen
        foreach ($this->additionalTools as $toolClass) {
            try {
                if (class_exists($toolClass)) {
                    $this->addTool($toolClass);
                }
            } catch (\Throwable $e) {
                Log::warning("[PlatformMcpServer] MCP Tool hinzufügen fehlgeschlagen", [
                    'class' => $toolClass,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
