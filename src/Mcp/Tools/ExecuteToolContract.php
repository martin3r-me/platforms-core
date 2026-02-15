<?php

namespace Platform\Core\Mcp\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Services\ToolPermissionService;
use Illuminate\Support\Facades\Log;

/**
 * Universelles Execute Tool
 *
 * Ermöglicht das Ausführen beliebiger Tools über ein einziges MCP-Tool.
 * Löst das Problem, dass Claude.ai keine dynamisch nachgeladenen Tools nutzen kann.
 *
 * Beispiel: execute(tool="planner.projects.GET", arguments={"limit": 10})
 */
class ExecuteToolContract implements ToolContract
{
    public function getName(): string
    {
        return 'execute';
    }

    public function getDescription(): string
    {
        return 'Führt ein beliebiges Tool aus. Nutze tools__GET(module="...") um verfügbare Tools zu sehen, ' .
            'dann execute(tool="toolname", arguments={...}) um es auszuführen. ' .
            'Beispiel: execute(tool="planner.projects.GET", arguments={"limit": 10})';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tool' => [
                    'type' => 'string',
                    'description' => 'Name des Tools (mit Punkten). Beispiel: "planner.projects.GET", "helpdesk.tickets.POST"',
                ],
                'arguments' => [
                    'type' => 'object',
                    'description' => 'Argumente für das Tool als JSON-Objekt. Siehe Tool-Schema via tools__GET.',
                ],
            ],
            'required' => ['tool'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $toolName = $arguments['tool'] ?? null;
            $toolArguments = $arguments['arguments'] ?? [];

            if (!is_string($toolName) || trim($toolName) === '') {
                return ToolResult::failure(
                    'Parameter "tool" ist erforderlich. Beispiel: execute(tool="planner.projects.GET")',
                    'MISSING_PARAMETER'
                );
            }

            $toolName = trim($toolName);

            // Tool in Registry finden
            $registry = app(ToolRegistry::class);

            // Stelle sicher, dass Tools geladen sind
            if (count($registry->all()) === 0) {
                $this->loadToolsIntoRegistry($registry);
            }

            // Tool finden (mit oder ohne Punkte)
            if (!$registry->has($toolName)) {
                // Versuche mit Underscores statt Punkte
                $altName = str_replace('__', '.', $toolName);
                if ($registry->has($altName)) {
                    $toolName = $altName;
                } else {
                    return ToolResult::failure(
                        "Tool '{$toolName}' nicht gefunden. Nutze tools__GET(module=\"...\") um verfügbare Tools zu sehen.",
                        'TOOL_NOT_FOUND'
                    );
                }
            }

            $tool = $registry->get($toolName);

            // Berechtigungsprüfung: Hat der User Zugriff auf das Modul?
            try {
                $permissionService = app(ToolPermissionService::class);
                if (!$permissionService->hasAccess($toolName)) {
                    return ToolResult::failure(
                        "Kein Zugriff auf Tool '{$toolName}'. Das Modul ist für dein Team nicht freigeschaltet.",
                        'ACCESS_DENIED'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[MCP Execute] Permission-Check fehlgeschlagen, erlaube Zugriff', [
                    'tool' => $toolName,
                    'error' => $e->getMessage(),
                ]);
            }

            // Tool ausführen
            Log::info('[MCP Execute] Tool wird ausgeführt', [
                'tool' => $toolName,
                'arguments' => array_keys($toolArguments),
            ]);

            $result = $tool->execute($toolArguments, $context);

            return $result;

        } catch (\Throwable $e) {
            Log::error('[MCP Execute] Fehler', [
                'tool' => $toolName ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return ToolResult::failure('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Lädt Tools in die Registry
     */
    private function loadToolsIntoRegistry(ToolRegistry $registry): void
    {
        try {
            // Core-Tools laden
            $coreTools = \Platform\Core\Tools\ToolLoader::loadCoreTools();
            foreach ($coreTools as $tool) {
                if (!$registry->has($tool->getName())) {
                    $registry->register($tool);
                }
            }

            // Module-Tools laden
            $modulesPath = realpath(__DIR__ . '/../../../../modules');
            if ($modulesPath && is_dir($modulesPath)) {
                $moduleTools = \Platform\Core\Tools\ToolLoader::loadFromAllModules($modulesPath);
                foreach ($moduleTools as $tool) {
                    if (!$registry->has($tool->getName())) {
                        $registry->register($tool);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[MCP Execute] Tool-Loading fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
