<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Registry\ModuleRegistry;

/**
 * Tool zum Abrufen verfügbarer Module
 * 
 * MCP-Pattern: Das Sprachmodell kann diesen Tool nutzen, um zu erfahren,
 * welche Module verfügbar sind und welche Tools sie anbieten.
 */
class GetModulesTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.modules.list';
    }

    public function getDescription(): string
    {
        return 'Gibt eine Liste aller verfügbaren Module zurück. Nutze dieses Tool, wenn du wissen musst, welche Module verfügbar sind oder welche Funktionen ein Modul anbietet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_tools' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Soll auch eine Liste der Tools pro Modul enthalten sein? Standard: false'
                ]
            ],
            'required' => []
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $includeTools = $arguments['include_tools'] ?? false;
            $modules = ModuleRegistry::all();
            
            $result = [
                'modules' => [],
                'count' => count($modules)
            ];

            $registry = app(\Platform\Core\Tools\ToolRegistry::class);
            $allTools = $registry->all();

            foreach ($modules as $moduleKey => $moduleConfig) {
                $moduleData = [
                    'key' => $moduleKey,
                    'title' => $moduleConfig['title'] ?? ucfirst($moduleKey),
                    'description' => $moduleConfig['description'] ?? '',
                ];

                if ($includeTools) {
                    $moduleTools = array_filter($allTools, function($tool) use ($moduleKey) {
                        return str_starts_with($tool->getName(), $moduleKey . '.');
                    });
                    $moduleData['tools'] = array_map(function($tool) {
                        return [
                            'name' => $tool->getName(),
                            'description' => $tool->getDescription(),
                        ];
                    }, $moduleTools);
                    $moduleData['tool_count'] = count($moduleTools);
                }

                $result['modules'][] = $moduleData;
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen der Module: ' . $e->getMessage());
        }
    }
}

