<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * Tool Chain Planner (MCP-Pattern)
 * 
 * Plant Tool-Chains VOR der Ausführung, um:
 * - Alle benötigten Tools zu identifizieren
 * - Dependencies zu resolven
 * - Optimale Ausführungsreihenfolge zu bestimmen
 * - Fehler früh zu erkennen
 * 
 * LOOSE COUPLED: Nutzt ToolDependencyContract und ToolMetadataContract
 */
class ToolChainPlanner
{
    public function __construct(
        private ToolRegistry $registry
    ) {}

    /**
     * Plant eine Tool-Chain für ein gegebenes Tool
     * 
     * @param string $toolName Name des Haupt-Tools
     * @param array $arguments Argumente für das Tool
     * @param ToolContext $context Kontext
     * @return array Plan mit: ['tools' => [...], 'order' => [...], 'missing' => [...]]
     */
    public function planChain(string $toolName, array $arguments, ToolContext $context): array
    {
        $plan = [
            'main_tool' => $toolName,
            'tools' => [],
            'order' => [],
            'missing' => [],
            'warnings' => []
        ];

        // Rekursiv alle Dependencies sammeln
        $this->collectDependencies($toolName, $arguments, $context, $plan, []);

        // Reihenfolge bestimmen (Topological Sort)
        $plan['order'] = $this->topologicalSort($plan['tools']);

        return $plan;
    }

    /**
     * Sammelt rekursiv alle Dependencies
     */
    private function collectDependencies(
        string $toolName,
        array $arguments,
        ToolContext $context,
        array &$plan,
        array $visited
    ): void {
        // Verhindere Zyklen
        if (in_array($toolName, $visited)) {
            $plan['warnings'][] = "Zyklische Dependency erkannt: {$toolName}";
            return;
        }

        $visited[] = $toolName;

        // Tool aus Registry holen
        $tool = $this->registry->get($toolName);
        if (!$tool) {
            $plan['missing'][] = $toolName;
            return;
        }

        // Tool zum Plan hinzufügen
        if (!isset($plan['tools'][$toolName])) {
            $plan['tools'][$toolName] = [
                'name' => $toolName,
                'arguments' => $arguments,
                'dependencies' => []
            ];
        }

        // Prüfe, ob Tool Dependencies hat
        if ($tool instanceof \Platform\Core\Contracts\ToolDependencyContract) {
            $deps = $tool->getDependencies();
            
            foreach ($deps['dependencies'] ?? [] as $dependency) {
                $depToolName = $dependency['tool_name'] ?? null;
                if (!$depToolName) {
                    continue;
                }

                // Prüfe Condition
                $condition = $dependency['condition'] ?? null;
                if ($condition && is_callable($condition)) {
                    if (!$condition($arguments, $context)) {
                        continue; // Condition nicht erfüllt
                    }
                }

                // Dependency zum Plan hinzufügen
                $plan['tools'][$toolName]['dependencies'][] = $depToolName;

                // Rekursiv Dependency-Tool prüfen
                $depArgs = [];
                $argsCallback = $dependency['args'] ?? null;
                if ($argsCallback && is_callable($argsCallback)) {
                    $depArgs = $argsCallback($arguments, $context) ?? [];
                }

                $this->collectDependencies($depToolName, $depArgs, $context, $plan, $visited);
            }
        }
    }

    /**
     * Topological Sort für Tool-Reihenfolge
     */
    private function topologicalSort(array $tools): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach ($tools as $toolName => $tool) {
            if (!isset($visited[$toolName])) {
                $this->visit($toolName, $tools, $visited, $visiting, $sorted);
            }
        }

        return array_reverse($sorted); // Reverse, damit Dependencies zuerst kommen
    }

    private function visit(string $toolName, array $tools, array &$visited, array &$visiting, array &$sorted): void
    {
        if (isset($visiting[$toolName])) {
            return; // Zyklus erkannt, aber bereits behandelt
        }

        if (isset($visited[$toolName])) {
            return; // Bereits besucht
        }

        $visiting[$toolName] = true;

        // Besuche alle Dependencies zuerst
        $deps = $tools[$toolName]['dependencies'] ?? [];
        foreach ($deps as $dep) {
            if (isset($tools[$dep])) {
                $this->visit($dep, $tools, $visited, $visiting, $sorted);
            }
        }

        unset($visiting[$toolName]);
        $visited[$toolName] = true;
        $sorted[] = $toolName;
    }
}

