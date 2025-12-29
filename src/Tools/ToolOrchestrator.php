<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * Tool-Orchestrator (MCP-Pattern)
 * 
 * Verwaltet Tool-Chains und führt automatisch benötigte Tools aus.
 * 
 * LOOSE COUPLED: Tools definieren ihre Dependencies selbst via ToolDependencyContract.
 * Der Orchestrator liest diese Informationen und führt die Tool-Chains automatisch aus.
 * 
 * MCP-Patterns:
 * - Tool Discovery: Via ToolRegistry
 * - Tool Chaining: Automatische Dependency-Resolution
 * - Context Propagation: Ergebnisse werden zwischen Tools weitergegeben
 * - Error Handling: Graceful degradation
 * 
 * Beispiel:
 * - User: "Erstelle ein Projekt"
 * - Tool 'planner.projects.create' implementiert ToolDependencyContract
 * - Orchestrator liest Dependencies aus dem Tool
 * - Orchestrator ruft automatisch core.teams.list auf (wenn team_id fehlt)
 * - Orchestrator führt planner.projects.create aus
 */
class ToolOrchestrator
{
    /**
     * Cache für gelesene Dependencies (Performance)
     */
    private array $dependencyCache = [];

    /**
     * Chain Planner für Pre-Flight Checks
     */
    private ?ToolChainPlanner $planner = null;

    public function __construct(
        private ToolExecutor $executor,
        private ToolRegistry $registry
    ) {
        $this->planner = new ToolChainPlanner($registry);
    }

    /**
     * Führt ein Tool mit automatischer Dependency-Resolution aus (MCP-Pattern)
     * 
     * @param string $toolName Name des Tools
     * @param array $arguments Argumente für das Tool
     * @param ToolContext $context Kontext für die Ausführung
     * @param int $maxDepth Maximale Tiefe für Tool-Chains (verhindert Endlosschleifen)
     * @param bool $planFirst Wenn true, wird die Chain zuerst geplant (Pre-Flight Check)
     * @return ToolResult Ergebnis der Ausführung
     */
    public function executeWithDependencies(
        string $toolName,
        array $arguments,
        ToolContext $context,
        int $maxDepth = 5,
        bool $planFirst = false
    ): ToolResult {
        if ($maxDepth <= 0) {
            return ToolResult::error(
                'Maximale Tool-Chain-Tiefe erreicht',
                'MAX_DEPTH_EXCEEDED'
            );
        }

        // Optional: Chain zuerst planen (Pre-Flight Check)
        if ($planFirst) {
            $plan = $this->planner->planChain($toolName, $arguments, $context);
            
            // Warnung bei fehlenden Tools, aber nicht abbrechen (könnte später geladen werden)
            if (!empty($plan['missing'])) {
                Log::warning('[ToolOrchestrator] Fehlende Tools im Plan', [
                    'missing' => $plan['missing'],
                    'plan' => $plan
                ]);
                // Nicht abbrechen - versuche trotzdem auszuführen
            }
            
            if (!empty($plan['warnings'])) {
                Log::warning('[ToolOrchestrator] Chain-Plan Warnungen', ['warnings' => $plan['warnings']]);
            }
        }

        // Prüfe, ob dieses Tool Dependencies hat (loose coupled: aus Tool selbst lesen)
        $deps = $this->getToolDependencies($toolName);
        
        if ($deps && !empty($deps['dependencies'])) {
            // Führe Dependency-Tools aus
            foreach ($deps['dependencies'] as $dependency) {
                $depToolName = $dependency['tool_name'] ?? null;
                $condition = $dependency['condition'] ?? null;
                $argsCallback = $dependency['args'] ?? null;
                $mergeCallback = $dependency['merge_result'] ?? null;
                
                if (!$depToolName) {
                    continue;
                }
                
                // Prüfe Condition (wenn vorhanden)
                if ($condition && is_callable($condition)) {
                    if (!$condition($arguments, $context)) {
                        continue; // Condition nicht erfüllt, Dependency überspringen
                    }
                }
                
                // Hole Dependency-Argumente
                $depArgs = [];
                if ($argsCallback && is_callable($argsCallback)) {
                    $depArgs = $argsCallback($arguments, $context) ?? [];
                }
                
                // Wenn depArgs null ist, Dependency nicht aufrufen
                if ($depArgs === null) {
                    continue;
                }

                Log::info("[ToolOrchestrator] Führe Dependency-Tool aus", [
                    'main_tool' => $toolName,
                    'dependency_tool' => $depToolName,
                    'args' => $depArgs
                ]);

                // Dependency-Tool ausführen
                $depResult = $this->executor->execute($depToolName, $depArgs, $context);
                
                if (!$depResult->success) {
                    Log::warning("[ToolOrchestrator] Dependency-Tool fehlgeschlagen", [
                        'main_tool' => $toolName,
                        'dependency_tool' => $depToolName,
                        'error' => $depResult->error
                    ]);
                    // Weiter mit Haupt-Tool, auch wenn Dependency fehlschlägt
                } else {
                    // Merge-Result Callback ausführen (wenn vorhanden)
                    if ($mergeCallback && is_callable($mergeCallback)) {
                        $mergedArgs = $mergeCallback($toolName, $depResult, $arguments);
                        
                        // Wenn null zurückgegeben wird, Dependency-Ergebnis direkt zurückgeben
                        if ($mergedArgs === null) {
                            return ToolResult::success([
                                'dependency_tool_result' => $depResult->data,
                                'message' => 'Bitte wähle aus der Liste aus.',
                                'requires_user_input' => true,
                                'next_tool' => $toolName,
                                'next_tool_args' => $arguments
                            ]);
                        }
                        
                        $arguments = $mergedArgs;
                    } else {
                        // Standard-Merge: Versuche team_id zu setzen (für Backwards-Kompatibilität)
                        $arguments = $this->defaultMergeDependencyResult($toolName, $depToolName, $depResult, $arguments);
                    }
                }
            }
        }

        // Haupt-Tool ausführen
        return $this->executor->execute($toolName, $arguments, $context);
    }

    /**
     * Liest Dependencies aus einem Tool (loose coupled)
     * 
     * Prüft, ob das Tool ToolDependencyContract implementiert und liest Dependencies.
     * Cache wird verwendet für Performance.
     */
    private function getToolDependencies(string $toolName): ?array
    {
        // Cache prüfen
        if (isset($this->dependencyCache[$toolName])) {
            return $this->dependencyCache[$toolName];
        }
        
        // Tool aus Registry holen
        $tool = $this->registry->get($toolName);
        if (!$tool) {
            return null;
        }
        
        // Prüfe, ob Tool ToolDependencyContract implementiert
        if (!($tool instanceof \Platform\Core\Contracts\ToolDependencyContract)) {
            $this->dependencyCache[$toolName] = null;
            return null;
        }
        
        // Dependencies aus Tool lesen
        $deps = $tool->getDependencies();
        $this->dependencyCache[$toolName] = $deps;
        
        return $deps;
    }

    /**
     * Standard-Merge für Dependency-Ergebnisse (Backwards-Kompatibilität)
     * 
     * Wird nur verwendet, wenn Tool keinen merge_result Callback definiert hat.
     */
    private function defaultMergeDependencyResult(
        string $mainToolName,
        string $depToolName,
        ToolResult $depResult,
        array $arguments
    ): array {
        // Standard: Kein automatisches Merging
        // Tools sollten ihren eigenen merge_result Callback definieren
        return $arguments;
    }
}

