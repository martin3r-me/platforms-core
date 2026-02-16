<?php

namespace Platform\Core\Services;

use Platform\Core\Models\CoreExtraFieldDefinition;

/**
 * Detects circular dependencies between extra field visibility conditions.
 *
 * Prevents configurations like:
 * - A depends on B, B depends on A
 * - A depends on B, B depends on C, C depends on A
 */
class ExtraFieldCircularDependencyDetector
{
    /**
     * Check if setting a visibility config on a field would create a circular dependency.
     *
     * @param string $fieldName The field being configured
     * @param array $visibilityConfig The proposed visibility config
     * @param array $allDefinitions All field definitions in the same context (as arrays with 'name' and 'visibility_config')
     * @return array|null Returns null if no cycle, or array with the cycle path if one is found
     */
    public function detectCycle(string $fieldName, array $visibilityConfig, array $allDefinitions): ?array
    {
        // Build dependency graph
        $graph = $this->buildDependencyGraph($allDefinitions);

        // Add/override the proposed dependencies for the field being edited
        $proposedDependencies = $this->extractFieldNames($visibilityConfig);
        $graph[$fieldName] = $proposedDependencies;

        // Check for cycles starting from the field being edited
        return $this->findCycle($fieldName, $graph);
    }

    /**
     * Build a dependency graph from all field definitions.
     *
     * @param array $allDefinitions
     * @return array<string, string[]> Map of field name to array of field names it depends on
     */
    protected function buildDependencyGraph(array $allDefinitions): array
    {
        $graph = [];

        foreach ($allDefinitions as $def) {
            $name = $def['name'];
            $config = $def['visibility_config'] ?? null;

            if (empty($config) || !($config['enabled'] ?? false)) {
                $graph[$name] = [];
                continue;
            }

            $graph[$name] = $this->extractFieldNames($config);
        }

        return $graph;
    }

    /**
     * Extract field names from a visibility config.
     */
    protected function extractFieldNames(array $config): array
    {
        if (!($config['enabled'] ?? false)) {
            return [];
        }

        $fieldNames = [];
        foreach ($config['groups'] ?? [] as $group) {
            foreach ($group['conditions'] ?? [] as $condition) {
                if (!empty($condition['field'])) {
                    $fieldNames[] = $condition['field'];
                }
            }
        }

        return array_unique($fieldNames);
    }

    /**
     * Find a cycle in the dependency graph using DFS.
     *
     * @return array|null The cycle path or null
     */
    protected function findCycle(string $startNode, array $graph): ?array
    {
        $visited = [];
        $path = [];

        return $this->dfs($startNode, $graph, $visited, $path);
    }

    /**
     * Depth-first search for cycle detection.
     */
    protected function dfs(string $node, array $graph, array &$visited, array &$path): ?array
    {
        if (in_array($node, $path, true)) {
            // Found a cycle - return the path from the repeated node
            $cycleStart = array_search($node, $path, true);
            $cycle = array_slice($path, $cycleStart);
            $cycle[] = $node; // Close the cycle

            return $cycle;
        }

        if (in_array($node, $visited, true)) {
            return null; // Already fully explored, no cycle through this node
        }

        $path[] = $node;

        foreach ($graph[$node] ?? [] as $dependency) {
            $result = $this->dfs($dependency, $graph, $visited, $path);
            if ($result !== null) {
                return $result;
            }
        }

        array_pop($path);
        $visited[] = $node;

        return null;
    }

    /**
     * Get a human-readable description of a cycle.
     *
     * @param array $cycle The cycle path
     * @param array $fieldLabels Map of field name to label
     * @return string
     */
    public function describeCycle(array $cycle, array $fieldLabels = []): string
    {
        $labels = array_map(
            fn(string $name) => $fieldLabels[$name] ?? $name,
            $cycle
        );

        return implode(' → ', $labels);
    }
}
