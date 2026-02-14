<?php

namespace Platform\Core\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolDiscoveryService;
use Platform\Core\Services\ToolPermissionService;
use Platform\Core\Mcp\McpSessionToolManager;
use Platform\Core\Mcp\Tools\McpToolDiscoveryTool;
use Platform\Core\Mcp\Adapters\ToolContractAdapter;
use Illuminate\Support\Facades\Log;

/**
 * Discovery-basierter MCP Server
 *
 * Implementiert das Discovery-Layer Pattern für MCP:
 * - Initial nur 5 Discovery-Tools (user, teams, context, modules, tools.GET)
 * - LLM ruft tools.GET(module="...") auf → Tools werden zur Session hinzugefügt
 * - Bei nächster tools/list Anfrage sind die neuen Tools verfügbar
 *
 * Diese Architektur ist skalierbar und überlastet nicht das Context Window.
 */
class DiscoveryMcpServer extends Server
{
    protected string $name = 'Platform MCP Server';
    protected string $version = '1.0.0';
    protected string $instructions = <<<'MARKDOWN'
Platform MCP Server. Bietet Zugriff auf alle registrierten Tools der Platform.

## Tool Discovery

Dieser Server verwendet ein Discovery-System für skalierbare Tool-Verwaltung:

1. **Initial verfügbare Tools:**
   - `core__user__GET` - Wer bin ich? (User-Info)
   - `core__teams__GET` - Welche Teams habe ich?
   - `core__context__GET` - Aktueller Kontext
   - `core__modules__GET` - Welche Module gibt es?
   - `tools__GET` - Tool Discovery (WICHTIG!)

2. **Weitere Tools aktivieren:**
   Rufe `tools__GET(module="modulname")` auf, um die Tools eines Moduls zu aktivieren.
   Beispiel: `tools__GET(module="planner")` aktiviert alle Planner-Tools.

3. **Workflow:**
   - Zuerst `core__modules__GET` aufrufen um verfügbare Module zu sehen
   - Dann `tools__GET(module="...")` für benötigte Module
   - Die Tools sind danach verfügbar und können verwendet werden

MARKDOWN;

    /**
     * listChanged aktivieren für dynamisches Tool-Loading
     */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => true,
        ],
        self::CAPABILITY_RESOURCES => [
            'listChanged' => false,
        ],
        self::CAPABILITY_PROMPTS => [
            'listChanged' => false,
        ],
    ];

    private ?string $sessionId = null;

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        // Session ID basierend auf User-ID (stabil über HTTP Requests)
        // Transport sessionId() ist oft null bei HTTP, daher User-basiert
        $this->sessionId = $this->resolveSessionId();
    }

    /**
     * Ermittelt eine stabile Session-ID
     *
     * Verwendet User-ID wenn authentifiziert, sonst Transport Session ID,
     * sonst eine generierte ID.
     */
    private function resolveSessionId(): string
    {
        // 1. Versuche User-basierte Session (stabil über Requests)
        try {
            $user = auth()->user();
            if ($user && $user->id) {
                return 'mcp_user_' . $user->id;
            }
        } catch (\Throwable $e) {
            // Auth nicht verfügbar
        }

        // 2. Transport Session ID
        $transportSessionId = $this->transport->sessionId();
        if ($transportSessionId) {
            return 'mcp_transport_' . $transportSessionId;
        }

        // 3. Fallback: Request-basierte ID (nicht ideal, aber besser als nichts)
        return 'mcp_' . bin2hex(random_bytes(8));
    }

    /**
     * Boot-Methode - lädt nur Discovery-Tools initial
     */
    public function boot(): void
    {
        try {
            parent::boot();
        } catch (\Throwable $e) {
            Log::error("[DiscoveryMcpServer] Fehler beim Parent-Boot", [
                'error' => $e->getMessage(),
            ]);
        }

        // Session ID wurde bereits im Constructor ermittelt

        try {
            $registry = app(ToolRegistry::class);
            $discovery = app(ToolDiscoveryService::class);
            $permissionService = app(ToolPermissionService::class);

            // 1. Discovery-Tools laden (5 Basis-Tools)
            $discoveryTools = McpSessionToolManager::getDiscoveryTools($registry);
            $discoveryToolNames = McpSessionToolManager::getDiscoveryToolNames();

            foreach ($discoveryTools as $tool) {
                // tools.GET bekommt Sonderbehandlung mit McpToolDiscoveryTool
                if ($tool->getName() === 'tools.GET') {
                    $mcpDiscoveryTool = new McpToolDiscoveryTool(
                        $registry,
                        $discovery,
                        $permissionService
                    );
                    $mcpDiscoveryTool->setSessionId($this->sessionId);

                    // Callback für dynamisches Tool-Loading
                    $mcpDiscoveryTool->onToolsLoaded(function(array $newTools) {
                        $this->addToolsToSession($newTools);
                    });

                    $this->tools[] = $mcpDiscoveryTool;
                } else {
                    // Normale Discovery-Tools via Adapter
                    try {
                        $adapter = new ToolContractAdapter($tool);
                        $this->tools[] = $adapter;
                    } catch (\Throwable $e) {
                        Log::warning("[DiscoveryMcpServer] Discovery-Tool Wrapping fehlgeschlagen", [
                            'tool' => $tool->getName(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // 2. Session-Tools laden (falls bereits Tools in der Session sind)
            $sessionTools = McpSessionToolManager::getSessionTools($this->sessionId, $registry);
            foreach ($sessionTools as $tool) {
                // Überspringe Discovery-Tools (sind bereits oben hinzugefügt)
                if (in_array($tool->getName(), $discoveryToolNames)) {
                    continue;
                }

                try {
                    $adapter = new ToolContractAdapter($tool);
                    $this->tools[] = $adapter;
                } catch (\Throwable $e) {
                    Log::warning("[DiscoveryMcpServer] Session-Tool Wrapping fehlgeschlagen", [
                        'tool' => $tool->getName(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $loadedCount = McpSessionToolManager::getLoadedToolCount($this->sessionId);

            Log::info('[MCP Discovery] Server boot', [
                'session_id' => substr($this->sessionId, 0, 8) . '...',
                'discovery_tools' => count($discoveryTools),
                'session_tools' => $loadedCount,
                'total_tools' => count($this->tools),
            ]);

        } catch (\Throwable $e) {
            Log::error("[DiscoveryMcpServer] Boot fehlgeschlagen", [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
        }
    }

    /**
     * Fügt Tools zur aktuellen Session hinzu
     *
     * Wird vom McpToolDiscoveryTool aufgerufen wenn neue Tools geladen werden.
     * Die Tools werden in das $tools Array gepusht und sind dann bei der
     * nächsten tools/list Anfrage verfügbar.
     *
     * @param array<\Platform\Core\Contracts\ToolContract> $newTools
     */
    private function addToolsToSession(array $newTools): void
    {
        foreach ($newTools as $tool) {
            try {
                $adapter = new ToolContractAdapter($tool);
                $this->tools[] = $adapter;

                Log::debug("[DiscoveryMcpServer] Tool zur Session hinzugefügt", [
                    'tool' => $tool->getName(),
                ]);
            } catch (\Throwable $e) {
                Log::warning("[DiscoveryMcpServer] Tool hinzufügen fehlgeschlagen", [
                    'tool' => $tool->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

}
