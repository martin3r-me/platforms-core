<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolDiscoveryService;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
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
    use HasStandardGetOperations;
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
        return 'Listet alle verfügbaren Tools und Module auf. Nutze dieses Tool, um gezielt Tools anzufordern, die du benötigst. BEISPIELE: "Ich brauche write-Tools für planner" → nutze filters mit module="planner" und read_only=false. "Ich brauche read-Tools für core" → nutze filters mit module="core" und read_only=true. "Ich brauche alle Tools für planner" → nutze filters mit module="planner". Du kannst Filter kombinieren: nach Modul, read_only Status, Kategorie, Tag oder Suchbegriff. WICHTIG: Wenn du nur read-only Tools siehst, nutze read_only=false, um write Tools zu sehen. Dieses Tool ermöglicht es dir, gezielt Tools anzufordern, die du für eine Aufgabe benötigst.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    // Legacy-Parameter (für Backwards-Kompatibilität und einfache Nutzung)
                    'module' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach einem bestimmten Modul (Legacy - nutze stattdessen filters mit field="module" und op="eq"). Beispiel: "planner", "okrs", "core"',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Kategorie (Legacy - nutze stattdessen filters mit field="category" und op="eq"). Mögliche Werte: "query" (Lese-Tools), "action" (Schreib-Tools), "utility" (Hilfs-Tools)',
                        'enum' => ['query', 'action', 'utility'],
                    ],
                    'tag' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Tag (Legacy - nutze stattdessen filters mit field="tags" und op="in"). Beispiel: "project", "team", "create", "list"',
                    ],
                    'read_only' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach read-only Tools (Legacy - nutze stattdessen filters mit field="read_only" und op="eq"). true = nur Lese-Tools, false = nur Schreib-Tools',
                    ],
                ]
            ]
        );
    }

    public function execute(array $arguments, \Platform\Core\Contracts\ToolContext $context): \Platform\Core\Contracts\ToolResult
    {
        try {
            // Alle Tools holen
            $allTools = array_values($this->registry->all());
            
            // Standard-Suche anwenden (auf Name und Beschreibung)
            $searchTerm = $arguments['search'] ?? null;
            if ($searchTerm) {
                $allTools = array_filter($allTools, function($tool) use ($searchTerm) {
                    $name = strtolower($tool->getName());
                    $description = strtolower($tool->getDescription());
                    $term = strtolower($searchTerm);
                    return str_contains($name, $term) || str_contains($description, $term);
                });
            }
            
            // Legacy-Filter anwenden (für Backwards-Kompatibilität)
            $hasLegacyFilters = !empty($arguments['module']) || 
                               isset($arguments['category']) || 
                               !empty($arguments['tag']) || 
                               isset($arguments['read_only']);
            
            if ($hasLegacyFilters) {
                $criteria = array_filter([
                    'module' => $arguments['module'] ?? null,
                    'category' => $arguments['category'] ?? null,
                    'tag' => $arguments['tag'] ?? null,
                    'read_only' => $arguments['read_only'] ?? null,
                ], fn($v) => $v !== null && $v !== '');
                
                $filteredTools = $this->discovery->findByCriteria($criteria);
                // Kombiniere mit Standard-Suche
                if ($searchTerm) {
                    $filteredTools = array_filter($filteredTools, function($tool) use ($searchTerm) {
                        $name = strtolower($tool->getName());
                        $description = strtolower($tool->getDescription());
                        $term = strtolower($searchTerm);
                        return str_contains($name, $term) || str_contains($description, $term);
                    });
                }
            } else {
                $filteredTools = $allTools;
            }
            
            // Standard-Filter aus filters-Array anwenden (falls vorhanden)
            if (!empty($arguments['filters']) && is_array($arguments['filters'])) {
                foreach ($arguments['filters'] as $filter) {
                    if (empty($filter['field']) || empty($filter['op'])) {
                        continue;
                    }
                    
                    $field = $filter['field'];
                    $op = $filter['op'];
                    $value = $filter['value'] ?? null;
                    
                    $filteredTools = array_filter($filteredTools, function($tool) use ($field, $op, $value) {
                        $metadata = $this->discovery->getToolMetadata($tool);
                        $toolName = $tool->getName();
                        
                        // Extrahiere Feld-Wert aus Tool
                        $fieldValue = match($field) {
                            'module' => $this->extractModuleFromToolName($toolName),
                            'category' => $metadata['category'] ?? null,
                            'read_only' => $metadata['read_only'] ?? false,
                            'tags' => $metadata['tags'] ?? [],
                            default => null
                        };
                        
                        // Wende Filter-Operator an
                        return match($op) {
                            'eq' => $fieldValue === $value,
                            'ne' => $fieldValue !== $value,
                            'in' => is_array($fieldValue) && in_array($value, $fieldValue),
                            'like' => is_string($fieldValue) && str_contains(strtolower($fieldValue), strtolower($value)),
                            default => true
                        };
                    });
                }
            }
            
            // Sortierung anwenden
            $sortField = !empty($arguments['sort']) && is_array($arguments['sort']) 
                ? ($arguments['sort'][0]['field'] ?? 'name')
                : 'name';
            $sortDir = !empty($arguments['sort']) && is_array($arguments['sort'])
                ? ($arguments['sort'][0]['dir'] ?? 'asc')
                : 'asc';
            
            usort($filteredTools, function($a, $b) use ($sortField, $sortDir) {
                $aValue = match($sortField) {
                    'name' => $a->getName(),
                    'module' => $this->extractModuleFromToolName($a->getName()),
                    default => $a->getName()
                };
                $bValue = match($sortField) {
                    'name' => $b->getName(),
                    'module' => $this->extractModuleFromToolName($b->getName()),
                    default => $b->getName()
                };
                
                $result = strcmp($aValue, $bValue);
                return $sortDir === 'desc' ? -$result : $result;
            });
            
            // Pagination anwenden
            $limit = min($arguments['limit'] ?? 50, 1000);
            $offset = max($arguments['offset'] ?? 0, 0);
            $filteredTools = array_slice($filteredTools, $offset, $limit);
            
            // Prüfe, ob Filter angewendet wurden
            $hasFilters = $hasLegacyFilters || 
                        !empty($arguments['filters']) || 
                        !empty($searchTerm);
            
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

