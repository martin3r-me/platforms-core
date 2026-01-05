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
        return 'GET /tools - Listet verfügbare Tools auf. WICHTIG: Standardmäßig werden ALLE Tools angezeigt (GET, POST, PUT, DELETE). Tools folgen REST-Pattern: module.entity.GET (Lesen), module.entity.POST (Erstellen), module.entity.PUT (Aktualisieren), module.entity.DELETE (Löschen). REST-Parameter: module (optional, string) - Filter nach Modul (z.B. "planner" zeigt alle planner.* Tools). read_only (optional, boolean) - Nur für Exploration: true = nur GET-Tools, false = nur POST/PUT/DELETE. Wenn nicht angegeben, werden ALLE Tools angezeigt. search (optional, string) - Suchbegriff.';
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
                    'description' => 'Optional: Nur für Exploration. true = nur Lese-Tools (GET), false = nur Schreib-Tools (POST, PUT, DELETE). WICHTIG: Wenn nicht angegeben, werden standardmäßig ALLE Tools angezeigt (GET, POST, PUT, DELETE). Nutze diesen Filter nur, wenn du explizit nur lesen oder nur schreiben willst.',
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
            
            // WICHTIG: Filtere Tools nach Berechtigung (Modul-Zugriff)
            $permissionService = app(\Platform\Core\Services\ToolPermissionService::class);
            $allTools = $permissionService->filterToolsByPermission($allTools);
            
            $filteredTools = $allTools;
            
            // 1. Modul-Filter (einfach: nur Tools, die mit "module." beginnen)
            $module = $arguments['module'] ?? null;
            $moduleFilterApplied = false;
            if ($module) {
                $beforeModuleFilter = count($filteredTools);
                $filteredTools = array_filter($filteredTools, function($tool) use ($module) {
                    $toolName = $tool->getName();
                    return str_starts_with($toolName, $module . '.');
                });
                $moduleFilterApplied = true;
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
            
            // 3. Such-Filter (auf Name und Beschreibung) - normalisiert (Punkte/Unterstriche ignorieren)
            $searchTerm = $arguments['search'] ?? null;
            if ($searchTerm) {
                $term = strtolower($searchTerm);
                // Normalisiere Suchbegriff: Punkte und Unterstriche entfernen
                $normalizedTerm = str_replace(['.', '_', '-'], '', $term);
                
                $filteredTools = array_filter($filteredTools, function($tool) use ($term, $normalizedTerm) {
                    $name = strtolower($tool->getName());
                    $description = strtolower($tool->getDescription());
                    
                    // Standard-Suche (exakt)
                    if (str_contains($name, $term) || str_contains($description, $term)) {
                        return true;
                    }
                    
                    // Normalisierte Suche (Punkte/Unterstriche ignorieren)
                    $normalizedName = str_replace(['.', '_', '-'], '', $name);
                    if (str_contains($normalizedName, $normalizedTerm)) {
                        return true;
                    }
                    
                    return false;
                });
            }
            
            // Sortiere nach Name (alphabetisch)
            usort($filteredTools, function($a, $b) {
                return strcmp($a->getName(), $b->getName());
            });
            
            // Ergebnis zusammenstellen
            $allToolsCount = count($this->registry->all());
            $hasFilters = !empty($module) || isset($arguments['read_only']) || !empty($searchTerm);
            
            // --- Modul/Entitäten-Übersicht (kompakt) ---
            // Ziel: Das LLM versteht schnell "welche Entitäten gibt es in Modul X und welche Operationen",
            // ohne immer die komplette Tool-Liste zu brauchen.
            $modulesIndex = [];
            foreach ($allTools as $tool) {
                $toolName = $tool->getName();
                if (!str_contains($toolName, '.')) { continue; }
                $parts = explode('.', $toolName);
                if (count($parts) < 2) { continue; }
                $mod = $parts[0] ?? null;
                if (!is_string($mod) || $mod === '') { continue; }
                $method = $parts[count($parts) - 1] ?? null; // GET/POST/PUT/DELETE
                $entity = implode('.', array_slice($parts, 1, -1));
                if ($entity === '') { $entity = null; }
                if (!isset($modulesIndex[$mod])) {
                    $modulesIndex[$mod] = [
                        'module' => $mod,
                        'tool_count' => 0,
                        'overview_tool' => null,
                        'entities' => [],
                    ];
                }
                $modulesIndex[$mod]['tool_count']++;
                if ($toolName === ($mod . '.overview.GET')) {
                    $modulesIndex[$mod]['overview_tool'] = $toolName;
                }
                if (is_string($entity) && $entity !== '' && is_string($method) && $method !== '') {
                    if (!isset($modulesIndex[$mod]['entities'][$entity])) {
                        $modulesIndex[$mod]['entities'][$entity] = [
                            'operations' => [],
                        ];
                    }
                    if (!in_array($method, $modulesIndex[$mod]['entities'][$entity]['operations'], true)) {
                        $modulesIndex[$mod]['entities'][$entity]['operations'][] = $method;
                    }
                }
            }
            ksort($modulesIndex);
            foreach ($modulesIndex as &$m) {
                // Sortiere Entitäten und Operationen deterministisch
                ksort($m['entities']);
                foreach ($m['entities'] as &$e) {
                    sort($e['operations']);
                }
            }
            unset($m, $e);

            $result = [
                'summary' => [
                    'total_tools' => $allToolsCount,
                    'filtered_tools' => count($filteredTools),
                    'filters_applied' => $hasFilters,
                ],
                'modules' => array_values($modulesIndex),
                'tools' => [],
            ];
            
            // 4. Fuzzy Fallback: Wenn Modul-Filter keine Ergebnisse liefert, aber Suche vorhanden ist
            // → Versuche auch ohne Modul-Filter zu suchen (für Fälle wie "project.get" → "planner.project.GET")
            if ($moduleFilterApplied && count($filteredTools) === 0 && !empty($searchTerm)) {
                $allToolsForFuzzy = array_values($this->registry->all());
                $permissionService = app(\Platform\Core\Services\ToolPermissionService::class);
                $allToolsForFuzzy = $permissionService->filterToolsByPermission($allToolsForFuzzy);
                
                $term = strtolower($searchTerm);
                $normalizedTerm = str_replace(['.', '_', '-'], '', $term);
                
                $fuzzyMatches = array_filter($allToolsForFuzzy, function($tool) use ($term, $normalizedTerm, $arguments) {
                    $name = strtolower($tool->getName());
                    $description = strtolower($tool->getDescription());
                    
                    // Normalisierte Suche
                    $normalizedName = str_replace(['.', '_', '-'], '', $name);
                    $matchesSearch = str_contains($normalizedName, $normalizedTerm) || str_contains($description, $term);
                    
                    // Read-Only Filter anwenden, falls vorhanden
                    if (isset($arguments['read_only'])) {
                        $readOnly = (bool)$arguments['read_only'];
                        $isReadOnly = str_ends_with($tool->getName(), '.GET');
                        if ($readOnly && !$isReadOnly) {
                            return false;
                        }
                        if (!$readOnly && $isReadOnly) {
                            return false;
                        }
                    }
                    
                    return $matchesSearch;
                });
                
                if (count($fuzzyMatches) > 0) {
                    $filteredTools = array_values($fuzzyMatches);
                    usort($filteredTools, function($a, $b) {
                        return strcmp($a->getName(), $b->getName());
                    });
                    $result['fuzzy_match'] = true;
                    $result['fuzzy_match_note'] = "Modul '{$module}' nicht gefunden, aber ähnliche Tools gefunden (ohne Modul-Filter)";
                    $result['summary']['filtered_tools'] = count($filteredTools);
                }
            }
            
            // Wenn Filter angewendet wurden, zeige sie an
            if ($hasFilters) {
                $result['filters'] = array_filter([
                    'module' => $module,
                    'read_only' => $arguments['read_only'] ?? null,
                    'search' => $searchTerm,
                ], fn($v) => $v !== null && $v !== '');
            }
            
            // Skalierung: wenn keine Filter gesetzt sind, kann die Tool-Liste sehr groß werden.
            // In diesem Fall liefern wir trotzdem die Modul-/Entitäten-Übersicht + eine kleine Sample-Liste.
            $unfilteredLimit = (int) config('openai.tools_get_unfiltered_limit', 120);
            $shouldTruncateToolsList = (!$hasFilters && count($filteredTools) > $unfilteredLimit);
            $toolsToReturn = $shouldTruncateToolsList ? array_slice($filteredTools, 0, $unfilteredLimit) : $filteredTools;

            // Tools-Informationen sammeln
            foreach ($toolsToReturn as $tool) {
                $toolName = $tool->getName();
                $metadata = $this->discovery->getToolMetadata($tool);

                $desc = (string) $tool->getDescription();
                if (mb_strlen($desc) > 220) { $desc = mb_substr($desc, 0, 217) . '...'; }
                
                $toolData = [
                    'name' => $toolName,
                    'description' => $desc,
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

            if ($shouldTruncateToolsList) {
                $result['summary']['tools_truncated'] = true;
                $result['summary']['tools_limit'] = $unfilteredLimit;
                $result['note'] = 'Viele Tools verfügbar. Nutze tools.GET mit module="..." oder search="..." für eine kleinere, relevante Liste.';
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

