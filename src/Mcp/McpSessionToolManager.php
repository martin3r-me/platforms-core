<?php

namespace Platform\Core\Mcp;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Mcp\McpSessionTeamManager;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolLoader;
use Platform\Core\Services\ToolPermissionService;
use Illuminate\Support\Facades\Log;

/**
 * Verwaltet dynamisch geladene Tools pro MCP Session
 *
 * Ermöglicht das Discovery-Layer Pattern für MCP:
 * - Initial nur 5 Discovery-Tools
 * - LLM ruft tools.GET auf → Tools werden zur Session hinzugefügt
 * - Server sendet listChanged Notification
 */
class McpSessionToolManager
{
    /**
     * Discovery Tools die initial geladen werden
     */
    private const DISCOVERY_TOOLS = [
        'core.user.GET',
        'core.teams.GET',
        'core.context.GET',
        'core.modules.GET',
        'core.team.switch',
        'tools.GET',
    ];

    /**
     * Geladene Tools pro Session
     * @var array<string, array<string, ToolContract>>
     */
    private static array $sessionTools = [];

    /**
     * Gibt die initialen Discovery-Tools zurück
     *
     * @return array<ToolContract>
     */
    public static function getDiscoveryTools(ToolRegistry $registry): array
    {
        $tools = [];

        // Stelle sicher, dass Tools geladen sind
        if (count($registry->all()) === 0) {
            self::loadToolsIntoRegistry($registry);
        }

        foreach (self::DISCOVERY_TOOLS as $toolName) {
            if ($registry->has($toolName)) {
                $tools[] = $registry->get($toolName);
            }
        }

        Log::debug('[MCP Session] Discovery Tools geladen', [
            'count' => count($tools),
            'tools' => array_map(fn($t) => $t->getName(), $tools),
        ]);

        return $tools;
    }

    /**
     * Lädt Tools für ein Modul in die Session
     *
     * @return array<ToolContract> Die neu geladenen Tools
     */
    public static function loadModuleTools(
        string $sessionId,
        string $module,
        ToolRegistry $registry,
        ?ToolPermissionService $permissionService = null
    ): array {
        // Stelle sicher, dass Tools geladen sind
        if (count($registry->all()) === 0) {
            self::loadToolsIntoRegistry($registry);
        }

        $allTools = $registry->all();

        // Filter nach Berechtigung
        if ($permissionService) {
            $allTools = $permissionService->filterToolsByPermission($allTools);
        }

        // Filter nach Modul
        $moduleTools = array_filter($allTools, function($tool) use ($module) {
            $toolName = $tool->getName();

            // Sonderbehandlung: "communication" zeigt auch core.comms.*
            if ($module === 'communication') {
                return str_starts_with($toolName, 'communication.')
                    || str_starts_with($toolName, 'core.comms.');
            }

            return str_starts_with($toolName, $module . '.');
        });

        // Zur Session hinzufügen
        if (!isset(self::$sessionTools[$sessionId])) {
            self::$sessionTools[$sessionId] = [];
        }

        $newTools = [];
        foreach ($moduleTools as $tool) {
            $toolName = $tool->getName();

            // Überspringe Discovery-Tools (sind bereits geladen)
            if (in_array($toolName, self::DISCOVERY_TOOLS)) {
                continue;
            }

            // Nur hinzufügen wenn noch nicht in Session
            if (!isset(self::$sessionTools[$sessionId][$toolName])) {
                self::$sessionTools[$sessionId][$toolName] = $tool;
                $newTools[] = $tool;
            }
        }

        Log::info('[MCP Session] Module Tools geladen', [
            'session_id' => substr($sessionId, 0, 8) . '...',
            'module' => $module,
            'new_tools' => count($newTools),
            'total_session_tools' => count(self::$sessionTools[$sessionId]),
        ]);

        return $newTools;
    }

    /**
     * Lädt spezifische Tools nach Namen in die Session
     *
     * @param array<string> $toolNames
     * @return array<ToolContract> Die neu geladenen Tools
     */
    public static function loadToolsByName(
        string $sessionId,
        array $toolNames,
        ToolRegistry $registry,
        ?ToolPermissionService $permissionService = null
    ): array {
        // Stelle sicher, dass Tools geladen sind
        if (count($registry->all()) === 0) {
            self::loadToolsIntoRegistry($registry);
        }

        $allTools = $registry->all();

        // Filter nach Berechtigung
        if ($permissionService) {
            $allTools = $permissionService->filterToolsByPermission($allTools);
        }

        // Session initialisieren
        if (!isset(self::$sessionTools[$sessionId])) {
            self::$sessionTools[$sessionId] = [];
        }

        $newTools = [];
        foreach ($toolNames as $toolName) {
            // Überspringe wenn bereits geladen
            if (isset(self::$sessionTools[$sessionId][$toolName])) {
                continue;
            }

            // Überspringe Discovery-Tools
            if (in_array($toolName, self::DISCOVERY_TOOLS)) {
                continue;
            }

            // Tool finden
            if ($registry->has($toolName)) {
                $tool = $registry->get($toolName);
                self::$sessionTools[$sessionId][$toolName] = $tool;
                $newTools[] = $tool;
            }
        }

        return $newTools;
    }

    /**
     * Gibt alle Tools für eine Session zurück (Discovery + dynamisch geladen)
     *
     * @return array<ToolContract>
     */
    public static function getSessionTools(string $sessionId, ToolRegistry $registry): array
    {
        $tools = self::getDiscoveryTools($registry);

        if (isset(self::$sessionTools[$sessionId])) {
            foreach (self::$sessionTools[$sessionId] as $tool) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * Gibt die Anzahl der dynamisch geladenen Tools zurück
     */
    public static function getLoadedToolCount(string $sessionId): int
    {
        return count(self::$sessionTools[$sessionId] ?? []);
    }

    /**
     * Löscht Session-Daten (z.B. bei Disconnect)
     */
    public static function clearSession(string $sessionId): void
    {
        unset(self::$sessionTools[$sessionId]);

        // Team-Override für diese Session ebenfalls aufräumen
        McpSessionTeamManager::clearSession($sessionId);
    }

    /**
     * Prüft ob ein Modul bereits geladen wurde
     */
    public static function isModuleLoaded(string $sessionId, string $module): bool
    {
        if (!isset(self::$sessionTools[$sessionId])) {
            return false;
        }

        foreach (self::$sessionTools[$sessionId] as $toolName => $tool) {
            if (str_starts_with($toolName, $module . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lädt Tools in die Registry (Helper)
     */
    private static function loadToolsIntoRegistry(ToolRegistry $registry): void
    {
        try {
            // Core-Tools laden
            $coreTools = ToolLoader::loadCoreTools();
            foreach ($coreTools as $tool) {
                if (!$registry->has($tool->getName())) {
                    $registry->register($tool);
                }
            }

            // Module-Tools laden
            $modulesPath = realpath(__DIR__ . '/../../../../modules');
            if ($modulesPath && is_dir($modulesPath)) {
                $moduleTools = ToolLoader::loadFromAllModules($modulesPath);
                foreach ($moduleTools as $tool) {
                    if (!$registry->has($tool->getName())) {
                        $registry->register($tool);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[MCP Session] Tool-Loading fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gibt die Namen der Discovery-Tools zurück
     *
     * @return array<string>
     */
    public static function getDiscoveryToolNames(): array
    {
        return self::DISCOVERY_TOOLS;
    }
}
