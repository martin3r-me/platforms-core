<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * Dependency Resolver
 * 
 * Interpretiert standardisiertes Dependency-DSL und löst Dependencies auf.
 * 
 * DSL-Format:
 * [
 *   'requires' => ['team_id'], // Fehlende Felder
 *   'resolver_tool' => 'core.teams.GET',
 *   'select_strategy' => 'auto_if_single|ask_user|fail',
 *   'map' => [
 *     'team_id' => '$.teams[0].id', // JSONPath für Mapping
 *   ]
 * ]
 */
class DependencyResolver
{
    /**
     * Löst eine Dependency auf
     * 
     * @param array $dependencyConfig DSL-Konfiguration
     * @param array $arguments Aktuelle Tool-Arguments
     * @param ToolContext $context Tool-Context
     * @param ToolRegistry $registry Tool-Registry
     * @param ToolExecutor $executor Tool-Executor
     * @return array|null Resolved arguments oder null (wenn User-Input benötigt)
     */
    public function resolve(
        array $dependencyConfig,
        array $arguments,
        ToolContext $context,
        ToolRegistry $registry,
        ToolExecutor $executor
    ): ?array {
        // 1. Prüfe requires - welche Felder fehlen?
        $requires = $dependencyConfig['requires'] ?? [];
        $missingFields = $this->checkMissingFields($requires, $arguments);
        
        if (empty($missingFields)) {
            // Keine fehlenden Felder - nichts zu tun
            return $arguments;
        }
        
        // 2. Führe resolver_tool aus
        $resolverTool = $dependencyConfig['resolver_tool'] ?? null;
        if (!$resolverTool) {
            Log::warning('[DependencyResolver] Kein resolver_tool angegeben', [
                'dependency_config' => $dependencyConfig,
            ]);
            return $arguments; // Kein Resolver - gebe Arguments zurück
        }
        
        $resolverToolInstance = $registry->get($resolverTool);
        if (!$resolverToolInstance) {
            Log::warning('[DependencyResolver] Resolver-Tool nicht gefunden', [
                'resolver_tool' => $resolverTool,
            ]);
            return $arguments;
        }
        
        // Führe Resolver-Tool aus
        $resolverResult = $executor->execute($resolverTool, [], $context);
        
        if (!$resolverResult->success) {
            Log::warning('[DependencyResolver] Resolver-Tool fehlgeschlagen', [
                'resolver_tool' => $resolverTool,
                'error' => $resolverResult->error,
            ]);
            return $arguments;
        }
        
        // 3. Wende select_strategy an
        $selectStrategy = $dependencyConfig['select_strategy'] ?? 'auto_if_single';
        $selectedData = $this->applySelectStrategy(
            $selectStrategy,
            $resolverResult->data,
            $missingFields,
            $context
        );
        
        if ($selectedData === null) {
            // User-Input benötigt
            return null;
        }
        
        // 4. Mappe Ergebnis mit JSONPath
        $map = $dependencyConfig['map'] ?? [];
        $mappedArguments = $this->applyMapping($map, $selectedData, $arguments);
        
        return $mappedArguments;
    }
    
    /**
     * Prüft welche Felder fehlen
     */
    private function checkMissingFields(array $requires, array $arguments): array
    {
        $missing = [];
        foreach ($requires as $field) {
            if (!isset($arguments[$field]) || $arguments[$field] === null) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
    
    /**
     * Wendet select_strategy an
     * 
     * @return array|null Selected data oder null (wenn User-Input benötigt)
     */
    private function applySelectStrategy(
        string $strategy,
        $resolverData,
        array $missingFields,
        ToolContext $context
    ): ?array {
        switch ($strategy) {
            case 'auto_if_single':
                // Wenn nur ein Ergebnis: automatisch verwenden
                // Wenn mehrere: null zurückgeben (User-Input benötigt)
                if (is_array($resolverData)) {
                    // Prüfe ob es eine Liste ist (z.B. ['teams' => [...]])
                    $listData = $this->extractListFromData($resolverData);
                    
                    if (count($listData) === 1) {
                        // Nur ein Ergebnis - automatisch verwenden
                        return $listData[0];
                    } elseif (count($listData) > 1) {
                        // Mehrere Ergebnisse - User-Input benötigt
                        return null;
                    }
                }
                
                // Fallback: Daten direkt verwenden
                return is_array($resolverData) ? $resolverData : ['data' => $resolverData];
                
            case 'ask_user':
                // Immer User-Input anfordern
                return null;
                
            case 'fail':
                // Fehler werfen wenn nicht eindeutig
                if (is_array($resolverData)) {
                    $listData = $this->extractListFromData($resolverData);
                    if (count($listData) !== 1) {
                        throw new \RuntimeException("Dependency resolution failed: Multiple or no results found");
                    }
                    return $listData[0];
                }
                return is_array($resolverData) ? $resolverData : ['data' => $resolverData];
                
            default:
                // Default: auto_if_single
                return $this->applySelectStrategy('auto_if_single', $resolverData, $missingFields, $context);
        }
    }
    
    /**
     * Extrahiert Liste aus Resolver-Data
     * 
     * Unterstützt verschiedene Formate:
     * - ['teams' => [...]]
     * - ['data' => [...]]
     * - [[...], [...]] (direkte Liste)
     */
    private function extractListFromData($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        
        // Direkte Liste?
        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }
        
        // In einem Key verschachtelt?
        foreach (['teams', 'data', 'items', 'results', 'list'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        
        // Fallback: Daten selbst als einzelnes Element
        return [$data];
    }
    
    /**
     * Wendet Mapping (JSONPath) an
     */
    private function applyMapping(array $map, array $selectedData, array $arguments): array
    {
        $mapped = $arguments;
        
        foreach ($map as $targetField => $jsonPath) {
            $value = $this->extractValueByJsonPath($jsonPath, $selectedData);
            if ($value !== null) {
                $mapped[$targetField] = $value;
            }
        }
        
        return $mapped;
    }
    
    /**
     * Extrahiert Wert aus Daten mit JSONPath
     * 
     * Unterstützt einfache JSONPath-Syntax:
     * - $.teams[0].id
     * - $.data.name
     * - $[0].id
     */
    private function extractValueByJsonPath(string $jsonPath, $data)
    {
        // Entferne führendes $
        $path = ltrim($jsonPath, '$');
        if (empty($path)) {
            return $data;
        }
        
        // Entferne führenden Punkt
        $path = ltrim($path, '.');
        
        // Teile Pfad in Segmente
        $segments = preg_split('/[\.\[\]]+/', $path, -1, PREG_SPLIT_NO_EMPTY);
        
        $current = $data;
        foreach ($segments as $segment) {
            if (is_array($current) && isset($current[$segment])) {
                $current = $current[$segment];
            } elseif (is_object($current) && isset($current->$segment)) {
                $current = $current->$segment;
            } else {
                return null;
            }
        }
        
        return $current;
    }
}

