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
    private ?string $sessionId = null;
    private $onToolsLoaded = null;

    public function __construct(
        private ToolRegistry $registry,
        private ?ToolPermissionService $permissionService = null
    ) {
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function onToolsLoaded(callable $callback): self
    {
        $this->onToolsLoaded = $callback;
        return $this;
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
            $sessionId = $this->sessionId ?? 'mcp_default';

            // Tools für das Modul laden (mit optionalem permissionService)
            try {
                $newTools = McpSessionToolManager::loadModuleTools(
                    $sessionId,
                    $module,
                    $this->registry,
                    $this->permissionService
                );
            } catch (\Throwable $e) {
                // Fallback: ohne Permission-Filter
                $newTools = McpSessionToolManager::loadModuleTools(
                    $sessionId,
                    $module,
                    $this->registry,
                    null
                );
            }

            // Callback aufrufen wenn neue Tools geladen wurden
            if (count($newTools) > 0 && $this->onToolsLoaded !== null) {
                try {
                    ($this->onToolsLoaded)($newTools);
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
