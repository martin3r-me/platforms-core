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
        return 'core.modules.GET';
    }

    public function getDescription(): string
    {
        return 'GET /modules - Listet verfügbare Module auf. REST-Parameter: include_tools (optional, boolean) - wenn true, werden auch alle Tools pro Modul angezeigt.';
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
                    // IMPORTANT: Keep this payload compact to avoid huge token usage in tool loops.
                    // We only include tool names and a truncated description.
                    $toolsOut = [];
                    $limit = 40; // safety limit per module
                    foreach (array_values($moduleTools) as $idx => $tool) {
                        if ($idx >= $limit) { break; }
                        $desc = (string) $tool->getDescription();
                        if (mb_strlen($desc) > 160) { $desc = mb_substr($desc, 0, 157) . '...'; }
                        $toolsOut[] = [
                            'name' => $tool->getName(),
                            'description' => $desc,
                        ];
                    }
                    $moduleData['tools'] = $toolsOut;
                    $moduleData['tool_count'] = count($moduleTools);
                    if (count($moduleTools) > $limit) {
                        $moduleData['tools_truncated'] = true;
                        $moduleData['tools_limit'] = $limit;
                    }
                }

                $result['modules'][] = $moduleData;
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen der Module: ' . $e->getMessage());
        }
    }
}

