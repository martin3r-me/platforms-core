<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\Tools\ToolContext;
use Platform\Core\Tools\ToolResult;

/**
 * Tool zum Auflisten aller verfügbaren Tools
 * 
 * Gibt dem Sprachmodell eine Übersicht über alle verfügbaren Tools und Module
 */
class ListToolsTool implements ToolContract
{
    public function __construct(
        private ToolRegistry $registry
    ) {}

    public function getName(): string
    {
        return 'tools.list';
    }

    public function getDescription(): string
    {
        return 'Listet alle verfügbaren Tools und Module auf. Nutze dieses Tool, um zu erfahren, welche Funktionen dir zur Verfügung stehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach einem bestimmten Modul (z.B. "planner", "okrs")',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, \Platform\Core\Tools\ToolContext $context): \Platform\Core\Tools\ToolResult
    {
        $moduleFilter = $arguments['module'] ?? null;
        
        $allTools = $this->registry->all();
        $modules = ModuleRegistry::all();
        
        $result = [
            'modules' => [],
            'tools' => [],
            'summary' => [
                'total_modules' => count($modules),
                'total_tools' => count($allTools),
            ],
        ];
        
        // Module-Informationen sammeln
        foreach ($modules as $moduleKey => $moduleConfig) {
            if ($moduleFilter && $moduleKey !== $moduleFilter) {
                continue;
            }
            
            $moduleTools = array_filter($allTools, function($tool) use ($moduleKey) {
                $toolName = $tool->getName();
                return str_starts_with($toolName, $moduleKey . '.');
            });
            
            $result['modules'][] = [
                'key' => $moduleKey,
                'title' => $moduleConfig['title'] ?? ucfirst($moduleKey),
                'description' => $moduleConfig['description'] ?? null,
                'tools_count' => count($moduleTools),
            ];
        }
        
        // Tools-Informationen sammeln
        foreach ($allTools as $tool) {
            $toolName = $tool->getName();
            
            // Filter nach Modul
            if ($moduleFilter && !str_starts_with($toolName, $moduleFilter . '.')) {
                continue;
            }
            
            $result['tools'][] = [
                'name' => $toolName,
                'description' => $tool->getDescription(),
                'module' => $this->extractModuleFromToolName($toolName),
            ];
        }
        
        return \Platform\Core\Tools\ToolResult::success($result);
    }
    
    private function extractModuleFromToolName(string $toolName): ?string
    {
        if (str_contains($toolName, '.')) {
            return explode('.', $toolName)[0];
        }
        return 'core';
    }
}

