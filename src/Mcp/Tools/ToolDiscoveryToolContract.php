<?php

namespace Platform\Core\Mcp\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Mcp\McpSessionToolManager;
use Platform\Core\Services\ToolPermissionService;

/**
 * Tool Discovery als ToolContract Implementation
 *
 * Lädt Tools eines Moduls in die MCP Session.
 */
class ToolDiscoveryToolContract implements ToolContract
{
    /**
     * Static storage for session-specific callbacks
     * @var array<string, callable>
     */
    private static array $callbacks = [];

    /**
     * Static session ID (set before tool execution)
     */
    private static ?string $currentSessionId = null;

    /**
     * Static registry reference
     */
    private static ?ToolRegistry $staticRegistry = null;

    /**
     * Static permission service reference
     */
    private static ?ToolPermissionService $staticPermissionService = null;

    /**
     * Configure the tool (call before registering)
     */
    public static function configure(
        string $sessionId,
        ToolRegistry $registry,
        ?ToolPermissionService $permissionService = null,
        ?callable $onToolsLoaded = null
    ): void {
        self::$currentSessionId = $sessionId;
        self::$staticRegistry = $registry;
        self::$staticPermissionService = $permissionService;

        if ($onToolsLoaded) {
            self::$callbacks[$sessionId] = $onToolsLoaded;
        }
    }

    public function getName(): string
    {
        return 'tools.GET';
    }

    public function getDescription(): string
    {
        return 'GET /tools - Listet und lädt Tools eines Moduls. WICHTIG: module ist required. ' .
            'Nach dem Aufruf werden die Tools des Moduls aktiviert und können verwendet werden. ' .
            'Nutze zuerst core.modules.GET, dann tools.GET(module="...").';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'Required: Modul-Key. Beispiel: "planner" → aktiviert alle planner.* Tools.',
                ],
            ],
            'required' => ['module'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $module = $arguments['module'] ?? null;

            if (!is_string($module) || trim($module) === '') {
                return ToolResult::failure(
                    'Parameter "module" ist erforderlich.',
                    'MISSING_PARAMETER'
                );
            }

            $module = trim($module);

            // Use static configuration or resolve from container
            $sessionId = self::$currentSessionId ?? 'mcp_default';
            $registry = self::$staticRegistry ?? app(ToolRegistry::class);
            $permissionService = self::$staticPermissionService;

            // Tools für das Modul laden (mit optionalem permissionService)
            try {
                $newTools = McpSessionToolManager::loadModuleTools(
                    $sessionId,
                    $module,
                    $registry,
                    $permissionService
                );
            } catch (\Throwable $e) {
                // Fallback: ohne Permission-Filter
                $newTools = McpSessionToolManager::loadModuleTools(
                    $sessionId,
                    $module,
                    $registry,
                    null
                );
            }

            // Callback aufrufen wenn neue Tools geladen wurden
            if (count($newTools) > 0 && isset(self::$callbacks[$sessionId])) {
                try {
                    (self::$callbacks[$sessionId])($newTools);
                } catch (\Throwable $e) {
                    // Ignore callback errors
                }
            }

            $toolNames = array_map(fn($t) => $t->getName(), $newTools);

            return ToolResult::success([
                'module' => $module,
                'tools_loaded' => count($newTools),
                'tool_names' => $toolNames,
                'message' => count($newTools) > 0
                    ? 'Tools wurden aktiviert und können jetzt verwendet werden.'
                    : 'Keine neuen Tools gefunden für dieses Modul.',
            ]);

        } catch (\Throwable $e) {
            return ToolResult::failure('Fehler: ' . $e->getMessage(), 'INTERNAL_ERROR');
        }
    }
}
