<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolDiscoveryService;
use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Tool zum Auflisten aller verfügbaren Tools
 * 
 * Gibt dem Sprachmodell eine Übersicht über alle verfügbaren Tools und Module.
 * Das LLM kann selbst entscheiden, welche Tools es sehen möchte, indem es Filter verwendet.
 */
class ListToolsTool implements ToolContract
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolDiscoveryService $discovery
    ) {}

    public function getName(): string
    {
        return 'tools.list';
    }

    public function getDescription(): string
    {
        return 'Listet alle verfügbaren Tools und Module auf. Nutze dieses Tool, um zu erfahren, welche Funktionen dir zur Verfügung stehen. Du kannst Filter verwenden, um nur relevante Tools zu sehen (z.B. nach Modul, Suchbegriff, Kategorie oder Tag filtern).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suche nach Tools, deren Name oder Beschreibung diesen Begriff enthält (z.B. "projekt", "team", "erstellen")',
                ],
                'module' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach einem bestimmten Modul (z.B. "planner", "okrs", "core")',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Kategorie (z.B. "query" für Lese-Tools, "action" für Schreib-Tools, "utility" für Hilfs-Tools)',
                    'enum' => ['query', 'action', 'utility'],
                ],
                'tag' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Tag (z.B. "project", "team", "create", "list")',
                ],
                'read_only' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Filter nach read-only Tools (true = nur Lese-Tools, false = nur Schreib-Tools)',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, \Platform\Core\Contracts\ToolContext $context): \Platform\Core\Contracts\ToolResult
    {
        try {
            // Prüfe ob Filter gesetzt sind
            $hasFilters = !empty($arguments['search']) || 
                         !empty($arguments['module']) || 
                         isset($arguments['category']) || 
                         !empty($arguments['tag']) || 
                         isset($arguments['read_only']);
            
            // Wenn Filter gesetzt sind, nutze ToolDiscoveryService
            if ($hasFilters) {
                $criteria = array_filter([
                    'search' => $arguments['search'] ?? null,
                    'module' => $arguments['module'] ?? null,
                    'category' => $arguments['category'] ?? null,
                    'tag' => $arguments['tag'] ?? null,
                    'read_only' => $arguments['read_only'] ?? null,
                ], fn($v) => $v !== null && $v !== '');
                
                $filteredTools = $this->discovery->findByCriteria($criteria);
            } else {
                // Keine Filter: Alle Tools
                $filteredTools = array_values($this->registry->all());
            }
            
            $allTools = $this->registry->all();
            $modules = ModuleRegistry::all();
            
            $result = [
                'tools' => [],
                'summary' => [
                    'total_tools' => count($allTools),
                    'filtered_tools' => count($filteredTools),
                    'filters_applied' => $hasFilters,
                ],
            ];
            
            // Wenn Filter angewendet wurden, zeige auch die verwendeten Filter
            if ($hasFilters) {
                $result['filters'] = array_filter([
                    'search' => $arguments['search'] ?? null,
                    'module' => $arguments['module'] ?? null,
                    'category' => $arguments['category'] ?? null,
                    'tag' => $arguments['tag'] ?? null,
                    'read_only' => $arguments['read_only'] ?? null,
                ], fn($v) => $v !== null && $v !== '');
            }
            
            // Tools-Informationen sammeln
            foreach ($filteredTools as $tool) {
                $toolName = $tool->getName();
                $metadata = $this->discovery->getToolMetadata($tool);
                
                $toolData = [
                    'name' => $toolName,
                    'description' => $tool->getDescription(),
                    'module' => $this->extractModuleFromToolName($toolName),
                ];
                
                // Füge Metadaten hinzu, falls verfügbar
                if (!empty($metadata)) {
                    $toolData['metadata'] = [
                        'category' => $metadata['category'] ?? null,
                        'tags' => $metadata['tags'] ?? [],
                        'read_only' => $metadata['read_only'] ?? false,
                    ];
                }
                
                $result['tools'][] = $toolData;
            }
            
            return \Platform\Core\Contracts\ToolResult::success($result);
        } catch (\Throwable $e) {
            return \Platform\Core\Contracts\ToolResult::error(
                'EXECUTION_ERROR',
                'Fehler beim Auflisten der Tools: ' . $e->getMessage()
            );
        }
    }
    
    private function extractModuleFromToolName(string $toolName): ?string
    {
        if (str_contains($toolName, '.')) {
            return explode('.', $toolName)[0];
        }
        return 'core';
    }
}

