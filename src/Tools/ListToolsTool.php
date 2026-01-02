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
        return 'tools.GET';
    }

    public function getDescription(): string
    {
        return 'Listet alle verfügbaren Tools auf. Nutze dieses Tool, um Tools eines bestimmten Moduls zu sehen. BEISPIELE: "Ich brauche alle Tools für planner" → nutze module="planner". "Ich brauche nur Lese-Tools für planner" → nutze module="planner" und read_only=true. "Ich brauche Schreib-Tools für planner" → nutze module="planner" und read_only=false. Tools folgen REST-Pattern: module.entity.GET (Lesen), module.entity.POST (Erstellen), module.entity.PUT (Aktualisieren), module.entity.DELETE (Löschen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur Tools eines bestimmten Moduls anzeigen. Beispiel: "planner" → zeigt alle Planner-Tools (planner.projects.GET, planner.projects.POST, planner.projects.PUT, planner.projects.DELETE, etc.). Wenn nicht angegeben, werden alle Tools aller Module angezeigt.',
                ],
                'read_only' => [
                    'type' => 'boolean',
                    'description' => 'Optional: true = nur Lese-Tools (GET), false = nur Schreib-Tools (POST, PUT, DELETE). Wenn nicht angegeben, werden alle Tools angezeigt (sowohl GET als auch POST/PUT/DELETE).',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suche in Tool-Namen und Beschreibungen. Beispiel: "project" findet alle Tools mit "project" im Namen oder in der Beschreibung.',
                ],
            ],
            'required' => []
        ];
    }

    public function execute(array $arguments, \Platform\Core\Contracts\ToolContext $context): \Platform\Core\Contracts\ToolResult
    {
        try {
            // Alle Tools holen
            $allTools = array_values($this->registry->all());
            $filteredTools = $allTools;
            
            // 1. Modul-Filter (einfach: nur Tools, die mit "module." beginnen)
            $module = $arguments['module'] ?? null;
            if ($module) {
                $filteredTools = array_filter($filteredTools, function($tool) use ($module) {
                    $toolName = $tool->getName();
                    return str_starts_with($toolName, $module . '.');
                });
            }
            
            // 2. Read-Only Filter (GET = read-only, POST/PUT/DELETE = write)
            if (isset($arguments['read_only'])) {
                $readOnly = (bool)$arguments['read_only'];
                $filteredTools = array_filter($filteredTools, function($tool) use ($readOnly) {
                    $toolName = $tool->getName();
                    $isReadOnly = str_ends_with($toolName, '.GET');
                    return $readOnly ? $isReadOnly : !$isReadOnly;
                });
            }
            
            // 3. Such-Filter (auf Name und Beschreibung)
            $searchTerm = $arguments['search'] ?? null;
            if ($searchTerm) {
                $term = strtolower($searchTerm);
                $filteredTools = array_filter($filteredTools, function($tool) use ($term) {
                    $name = strtolower($tool->getName());
                    $description = strtolower($tool->getDescription());
                    return str_contains($name, $term) || str_contains($description, $term);
                });
            }
            
            // Sortiere nach Name (alphabetisch)
            usort($filteredTools, function($a, $b) {
                return strcmp($a->getName(), $b->getName());
            });
            
            // Ergebnis zusammenstellen
            $allToolsCount = count($this->registry->all());
            $hasFilters = !empty($module) || isset($arguments['read_only']) || !empty($searchTerm);
            
            $result = [
                'tools' => [],
                'summary' => [
                    'total_tools' => $allToolsCount,
                    'filtered_tools' => count($filteredTools),
                    'filters_applied' => $hasFilters,
                ],
            ];
            
            // Wenn Filter angewendet wurden, zeige sie an
            if ($hasFilters) {
                $result['filters'] = array_filter([
                    'module' => $module,
                    'read_only' => $arguments['read_only'] ?? null,
                    'search' => $searchTerm,
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

