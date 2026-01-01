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
     * In-Memory Cache für aktuelle Request
     */
    private array $dependencyCache = [];

    /**
     * Chain Planner für Pre-Flight Checks
     */
    private ?ToolChainPlanner $planner = null;

    /**
     * Cache-Service für persistente Dependency-Caches
     */
    private ?ToolCacheService $cacheService = null;
    
    /**
     * Dependency Resolver für DSL-Format
     */
    private ?DependencyResolver $dependencyResolver = null;
    
    /**
     * Tool Run Service für persistente Multi-Step-Runs
     */
    private ?\Platform\Core\Services\ToolRunService $runService = null;

    private const DEPENDENCY_CACHE_PREFIX = 'tool_dependencies:';
    private const DEPENDENCY_CACHE_TTL = 86400; // 24 Stunden

    public function __construct(
        private ToolExecutor $executor,
        private ToolRegistry $registry
    ) {
        $this->planner = new ToolChainPlanner($registry);
        
        // Lazy-Load Cache-Service
        try {
            $this->cacheService = app(ToolCacheService::class);
        } catch (\Throwable $e) {
            // Service noch nicht verfügbar
            $this->cacheService = null;
        }
        
        // Lazy-Load DependencyResolver
        try {
            $this->dependencyResolver = new DependencyResolver();
        } catch (\Throwable $e) {
            // DependencyResolver nicht verfügbar
            $this->dependencyResolver = null;
        }
        
        // Lazy-Load ToolRunService
        try {
            $this->runService = app(\Platform\Core\Services\ToolRunService::class);
        } catch (\Throwable $e) {
            // Service noch nicht verfügbar
            $this->runService = null;
        }
    }

    /**
     * Führt ein Tool mit automatischer Dependency-Resolution aus (MCP-Pattern)
     * 
     * @param string $toolName Name des Tools
     * @param array $arguments Argumente für das Tool
     * @param ToolContext $context Kontext für die Ausführung
     * @param int $maxDepth Maximale Tiefe für Tool-Chains (verhindert Endlosschleifen)
     * @param bool $planFirst Wenn true, wird die Chain zuerst geplant (Pre-Flight Check)
     * @param string|null $conversationId Conversation-ID für Multi-Step-Runs (optional)
     * @param int|null $runId Run-ID für Resume (optional)
     * @return ToolResult Ergebnis der Ausführung
     */
    public function executeWithDependencies(
        string $toolName,
        array $arguments,
        ToolContext $context,
        int $maxDepth = 5,
        bool $planFirst = false,
        ?string $conversationId = null,
        ?int $runId = null
    ): ToolResult {
        if ($maxDepth <= 0) {
            return ToolResult::error(
                'Maximale Tool-Chain-Tiefe erreicht',
                'MAX_DEPTH_EXCEEDED'
            );
        }
        
        // Multi-Step-Run: Resume oder Create
        $run = null;
        $step = 0;
        if ($this->runService && $conversationId) {
            if ($runId) {
                // Resume existing run
                $run = $this->runService->getRun($runId);
                if ($run) {
                    $step = $run->step + 1;
                    $toolName = $run->next_tool ?? $toolName;
                    $arguments = $run->arguments;
                }
            } else {
                // Create new run
                $run = $this->runService->createRun($conversationId, $toolName, $arguments, $context, $step);
            }
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
                // NEUES DSL-Format prüfen
                if (isset($dependency['requires']) && isset($dependency['resolver_tool'])) {
                    // DSL-Format verwenden
                    if ($this->dependencyResolver) {
                        $resolvedArgs = $this->dependencyResolver->resolve(
                            $dependency,
                            $arguments,
                            $context,
                            $this->registry,
                            $this->executor
                        );
                        
                        if ($resolvedArgs === null) {
                            // User-Input benötigt
                            // Führe resolver_tool aus, um Optionen zu bekommen
                            $resolverTool = $dependency['resolver_tool'];
                            $depResult = $this->executor->execute($resolverTool, [], $context);
                            
                            if ($depResult->success) {
                                // Speichere Run-State (wenn Service verfügbar)
                                if ($this->runService && $run) {
                                    $inputOptions = $this->extractInputOptions($depResult->data);
                                    $this->runService->updateRunWaitingInput(
                                        $run->id,
                                        $inputOptions,
                                        $toolName,
                                        $arguments
                                    );
                                }
                                
                                return ToolResult::success([
                                    'dependency_tool_result' => $depResult->data,
                                    'message' => 'Bitte wähle aus der Liste aus.',
                                    'requires_user_input' => true,
                                    'next_tool' => $toolName,
                                    'next_tool_args' => $arguments,
                                    'run_id' => $run?->id,
                                    'conversation_id' => $conversationId,
                                ]);
                            }
                        } else {
                            // Arguments wurden resolved
                            $arguments = $resolvedArgs;
                        }
                    }
                } else {
                    // ALTES Format (Callbacks) - für Backwards-Kompatibilität
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

                    Log::info("[ToolOrchestrator] Führe Dependency-Tool aus (altes Format)", [
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
                            // Speichere Run-State (wenn Service verfügbar)
                            if ($this->runService && $run) {
                                $inputOptions = $this->extractInputOptions($depResult->data);
                                $this->runService->updateRunWaitingInput(
                                    $run->id,
                                    $inputOptions,
                                    $toolName,
                                    $arguments
                                );
                            }
                            
                            return ToolResult::success([
                                'dependency_tool_result' => $depResult->data,
                                'message' => 'Bitte wähle aus der Liste aus.',
                                'requires_user_input' => true,
                                'next_tool' => $toolName,
                                'next_tool_args' => $arguments,
                                'run_id' => $run?->id,
                                'conversation_id' => $conversationId,
                            ]);
                        }
                            
                            $arguments = $mergedArgs;
                        } else {
                            // Standard-Merge: Versuche team_id zu setzen (für Backwards-Kompatibilität)
                            $mergedArgs = $this->defaultMergeDependencyResult($toolName, $depToolName, $depResult, $arguments);
                            
                            // Wenn null zurückgegeben wird, User-Input anfordern
                            if ($mergedArgs === null) {
                                // Speichere Run-State (wenn Service verfügbar)
                                if ($this->runService && $run) {
                                    $inputOptions = $this->extractInputOptions($depResult->data);
                                    $this->runService->updateRunWaitingInput(
                                        $run->id,
                                        $inputOptions,
                                        $toolName,
                                        $arguments
                                    );
                                }
                                
                                return ToolResult::success([
                                    'dependency_tool_result' => $depResult->data,
                                    'message' => 'Bitte wähle aus der Liste aus.',
                                    'requires_user_input' => true,
                                    'next_tool' => $toolName,
                                    'next_tool_args' => $arguments,
                                    'run_id' => $run?->id,
                                    'conversation_id' => $conversationId,
                                ]);
                            }
                            
                            $arguments = $mergedArgs;
                        }
                    }
                }
            }
        }

        // Haupt-Tool ausführen
        $result = $this->executor->execute($toolName, $arguments, $context);
        
        // Update Run-Status (wenn Service verfügbar)
        if ($this->runService && $run) {
            if ($result->success) {
                $this->runService->completeRun($run->id);
            } else {
                $this->runService->failRun($run->id, $result->error);
            }
        }
        
        return $result;
    }
    
    /**
     * Extrahiert Input-Optionen aus Dependency-Result
     * 
     * Unterstützt verschiedene Formate:
     * - ['teams' => [...]]
     * - ['data' => [...]]
     * - [[...], [...]] (direkte Liste)
     */
    private function extractInputOptions($data): array
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
     * Liest Dependencies aus einem Tool (loose coupled)
     * 
     * Prüft, ob das Tool ToolDependencyContract implementiert und liest Dependencies.
     * Nutzt sowohl In-Memory- als auch persistenten Cache für Performance.
     */
    private function getToolDependencies(string $toolName): ?array
    {
        // In-Memory Cache prüfen (für aktuelle Request)
        if (isset($this->dependencyCache[$toolName])) {
            return $this->dependencyCache[$toolName];
        }
        
        // Persistent Cache prüfen (wenn verfügbar)
        $cacheKey = self::DEPENDENCY_CACHE_PREFIX . $toolName;
        if ($this->cacheService) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $this->dependencyCache[$toolName] = $cached;
                return $cached;
            }
        }
        
        // Tool aus Registry holen
        $tool = $this->registry->get($toolName);
        if (!$tool) {
            $this->dependencyCache[$toolName] = null;
            return null;
        }
        
        // Prüfe, ob Tool ToolDependencyContract implementiert
        if (!($tool instanceof \Platform\Core\Contracts\ToolDependencyContract)) {
            $this->dependencyCache[$toolName] = null;
            // Cache auch "keine Dependencies" (verhindert wiederholte Prüfungen)
            if ($this->cacheService) {
                Cache::put($cacheKey, null, self::DEPENDENCY_CACHE_TTL);
            }
            return null;
        }
        
        // Dependencies aus Tool lesen
        $deps = $tool->getDependencies();
        
        // In-Memory Cache
        $this->dependencyCache[$toolName] = $deps;
        
        // Persistent Cache (wenn verfügbar)
        if ($this->cacheService) {
            Cache::put($cacheKey, $deps, self::DEPENDENCY_CACHE_TTL);
        }
        
        return $deps;
    }

    /**
     * Invalidiert Dependency-Cache für ein Tool
     * 
     * Wird aufgerufen, wenn ein Tool neu registriert wird
     */
    public function invalidateDependencyCache(string $toolName): void
    {
        // In-Memory Cache
        unset($this->dependencyCache[$toolName]);
        
        // Persistent Cache
        $cacheKey = self::DEPENDENCY_CACHE_PREFIX . $toolName;
        Cache::forget($cacheKey);
    }

    /**
     * Invalidiert alle Dependency-Caches
     */
    public function invalidateAllDependencyCaches(): void
    {
        // In-Memory Cache
        $this->dependencyCache = [];
        
        // Persistent Cache: Wird automatisch nach TTL ablaufen
        // Später: Tag-basierte Invalidation implementieren
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
        // Spezialfall: core.teams.list → planner.projects.create
        // Wenn mehrere Teams vorhanden sind und kein team_id angegeben, User-Input anfordern
        if ($depToolName === 'core.teams.list' && $depResult->success && isset($depResult->data['teams'])) {
            $teams = $depResult->data['teams'];
            $teamCount = count($teams);
            
            // Wenn kein team_id in Arguments und mehrere Teams vorhanden → User-Input anfordern
            if (empty($arguments['team_id']) && $teamCount > 1) {
                // Gib null zurück, um User-Input anzufordern
                return null; // Wird in execute() erkannt und führt zu requires_user_input
            }
            
            // Wenn nur ein Team vorhanden, automatisch verwenden
            if (empty($arguments['team_id']) && $teamCount === 1) {
                $arguments['team_id'] = $teams[0]['id'];
                return $arguments;
            }
            
            // Wenn current_team_id vorhanden und kein team_id angegeben, verwende current
            if (empty($arguments['team_id']) && isset($depResult->data['current_team_id'])) {
                $arguments['team_id'] = $depResult->data['current_team_id'];
                return $arguments;
            }
        }
        
        // Standard: Kein automatisches Merging
        // Tools sollten ihren eigenen merge_result Callback definieren
        return $arguments;
    }
}

