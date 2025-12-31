<?php

namespace Platform\Core\Testing;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolOrchestrator;

/**
 * Helper-Funktionen für Tool-Tests
 * 
 * Kann in Tests verwendet werden, die nicht von ToolTestCase erben
 */
class ToolTestHelpers
{
    /**
     * Führt ein Tool aus
     */
    public static function executeTool(
        string $toolName,
        array $arguments = [],
        ?ToolContext $context = null
    ): ToolResult {
        $executor = app(ToolExecutor::class);
        
        if ($context === null) {
            $context = self::createMockContext();
        }

        return $executor->execute($toolName, $arguments, $context);
    }

    /**
     * Erstellt einen Mock-ToolContext
     */
    public static function createMockContext(
        ?int $userId = null,
        ?int $teamId = null
    ): ToolContext {
        $user = null;
        $team = null;

        if ($userId) {
            $user = \Platform\Core\Models\User::find($userId);
        }

        if ($teamId) {
            $team = \Platform\Core\Models\Team::find($teamId);
        }

        return ToolContext::create(
            user: $user,
            team: $team
        );
    }

    /**
     * Führt eine Tool-Chain aus
     */
    public static function executeToolChain(
        string $mainToolName,
        array $arguments = [],
        ?ToolContext $context = null
    ): ToolResult {
        $orchestrator = app(ToolOrchestrator::class);
        
        if ($context === null) {
            $context = self::createMockContext();
        }

        return $orchestrator->executeWithDependencies(
            $mainToolName,
            $arguments,
            $context,
            maxDepth: 5,
            planFirst: true
        );
    }

    /**
     * Prüft ob Tool registriert ist
     */
    public static function isToolRegistered(string $toolName): bool
    {
        $registry = app(\Platform\Core\Tools\ToolRegistry::class);
        return $registry->has($toolName);
    }

    /**
     * Gibt alle registrierten Tools zurück
     */
    public static function getAllTools(): array
    {
        $registry = app(\Platform\Core\Tools\ToolRegistry::class);
        return $registry->all();
    }
}

