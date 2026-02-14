<?php

namespace Platform\Core\Mcp\Tools;

use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolResult;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolDiscoveryService;
use Platform\Core\Tools\ListToolsTool;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Mcp\McpSessionToolManager;
use Platform\Core\Services\ToolPermissionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * MCP-spezifisches Tool Discovery Tool
 *
 * Wrappt das interne tools.GET und aktiviert zusätzlich das dynamische
 * Tool-Loading für die MCP Session. Nach dem Aufruf werden die angeforderten
 * Module-Tools zur Session hinzugefügt und sind bei der nächsten tools/list
 * Anfrage verfügbar.
 */
class McpToolDiscoveryTool extends Tool
{
    private ?string $sessionId = null;

    /**
     * Callback um neue Tools zur MCP Server Instanz hinzuzufügen
     * @var callable|null
     */
    private $onToolsLoaded = null;

    public function __construct(
        private ToolRegistry $registry,
        private ToolDiscoveryService $discovery,
        private ?ToolPermissionService $permissionService = null
    ) {
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Setzt einen Callback der aufgerufen wird wenn neue Tools geladen werden
     *
     * @param callable $callback function(array $newTools): void
     */
    public function onToolsLoaded(callable $callback): self
    {
        $this->onToolsLoaded = $callback;
        return $this;
    }

    public function name(): string
    {
        return 'tools__GET';
    }

    public function description(): string
    {
        return 'GET /tools - Listet und lädt Tools eines Moduls. WICHTIG: module ist required. ' .
            'Nach dem Aufruf werden die Tools des Moduls aktiviert und können verwendet werden. ' .
            'Nutze zuerst core__modules__GET, dann tools__GET(module="...").';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()
                ->description('Required: Modul-Key. Beispiel: "planner" → aktiviert alle planner.* Tools. Nutze core__modules__GET, um gültige Module zu sehen.'),
            'read_only' => $schema->boolean()
                ->description('Optional: Wenn true, werden nur Lese-Tools (GET) gelistet.')
                ->nullable(),
            'write_only' => $schema->boolean()
                ->description('Optional: Wenn true, werden nur Schreib-Tools (POST, PUT, DELETE) gelistet.')
                ->nullable(),
            'search' => $schema->string()
                ->description('Optional: Suche in Tool-Namen und Beschreibungen.')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Pagination (optional): Maximale Anzahl Tools. Standard: 50.')
                ->nullable(),
            'offset' => $schema->integer()
                ->description('Pagination (optional): Anzahl zu überspringender Tools. Standard: 0.')
                ->nullable(),
        ];
    }

    public function handle(array $arguments): ToolResult
    {
        try {
            $module = $arguments['module'] ?? null;

            if (!is_string($module) || trim($module) === '') {
                return ToolResult::error(
                    'Parameter "module" ist erforderlich. Nutze zuerst core__modules__GET, dann tools__GET(module="...").'
                );
            }

            $module = trim($module);

            // Session ID ermitteln (falls nicht gesetzt, generiere eine)
            $sessionId = $this->sessionId ?? $this->generateSessionId();

            // Tools für das Modul in die Session laden
            $newTools = McpSessionToolManager::loadModuleTools(
                $sessionId,
                $module,
                $this->registry,
                $this->permissionService
            );

            // Callback aufrufen wenn neue Tools geladen wurden
            if (count($newTools) > 0 && $this->onToolsLoaded !== null) {
                ($this->onToolsLoaded)($newTools);
            }

            // Internes ListToolsTool für die eigentliche Suche verwenden
            $listToolsTool = new ListToolsTool($this->registry, $this->discovery);
            $context = $this->createToolContext();
            $result = $listToolsTool->execute($arguments, $context);

            if (!$result->success) {
                return ToolResult::error($result->error ?? 'Unbekannter Fehler');
            }

            // Erweitere Antwort mit MCP-spezifischen Hinweisen
            $data = $result->data;
            $data['_mcp'] = [
                'tools_loaded' => count($newTools),
                'tool_names' => array_map(fn($t) => $t->getName(), $newTools),
                'session_total_tools' => McpSessionToolManager::getLoadedToolCount($sessionId) + 5, // +5 Discovery
                'hint' => count($newTools) > 0
                    ? 'Die Tools wurden aktiviert und können jetzt verwendet werden.'
                    : 'Keine neuen Tools geladen (bereits aktiviert oder nicht vorhanden).',
            ];

            Log::info('[MCP Discovery] tools.GET ausgeführt', [
                'module' => $module,
                'new_tools' => count($newTools),
                'session_id' => substr($sessionId, 0, 8) . '...',
            ]);

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return ToolResult::text($json);

        } catch (\Throwable $e) {
            Log::error('[MCP Discovery] Fehler', [
                'error' => $e->getMessage(),
            ]);
            return ToolResult::error('Fehler beim Auflisten der Tools: ' . $e->getMessage());
        }
    }

    private function createToolContext(): ToolContext
    {
        $user = Auth::user();
        $team = null;

        if ($user && method_exists($user, 'currentTeam')) {
            $team = $user->currentTeam;
        }

        return ToolContext::create($user, $team);
    }

    private function generateSessionId(): string
    {
        return 'mcp_' . bin2hex(random_bytes(8));
    }
}
