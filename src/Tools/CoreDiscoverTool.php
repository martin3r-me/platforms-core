<?php

namespace Platform\Core\Tools;

use Platform\Core\Services\ToolRegistry;

class CoreDiscoverTool
{
    protected ToolRegistry $toolRegistry;

    public function __construct(ToolRegistry $toolRegistry)
    {
        $this->toolRegistry = $toolRegistry;
    }

    public function discoverTools(string $module): array
    {
        try {
            $moduleTools = $this->toolRegistry->getToolsForModule($module);
            
            return [
                'ok' => true,
                'data' => [
                    'module' => $module,
                    'tools' => $moduleTools,
                    'count' => count($moduleTools),
                    'tool_names' => array_column($moduleTools, 'function.name')
                ],
                'message' => "Tools für Modul '{$module}' entdeckt"
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => "Fehler beim Entdecken der Tools für Modul '{$module}': " . $e->getMessage(),
                'data' => []
            ];
        }
    }
}
