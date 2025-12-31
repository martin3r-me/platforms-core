<?php

namespace Platform\Core\Testing;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\User;
use Platform\Core\Models\Team;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolRegistry;
use Tests\TestCase;

/**
 * Base TestCase für Tool-Tests
 * 
 * Bietet Helper-Methoden für Tool-Testing
 */
abstract class ToolTestCase extends TestCase
{
    protected ToolRegistry $registry;
    protected ToolExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->registry = app(ToolRegistry::class);
        $this->executor = app(ToolExecutor::class);
    }

    /**
     * Führt ein Tool aus und gibt das Result zurück
     */
    protected function executeTool(
        string $toolName,
        array $arguments = [],
        ?ToolContext $context = null
    ): ToolResult {
        if ($context === null) {
            $context = $this->createMockContext();
        }

        return $this->executor->execute($toolName, $arguments, $context);
    }

    /**
     * Erstellt einen Mock-ToolContext
     */
    protected function createMockContext(
        ?User $user = null,
        ?Team $team = null
    ): ToolContext {
        if ($user === null) {
            $user = User::factory()->create();
        }

        if ($team === null) {
            $team = Team::factory()->create();
            $user->teams()->attach($team);
        }

        return ToolContext::create(
            user: $user,
            team: $team
        );
    }

    /**
     * Assertion: Tool-Result ist erfolgreich
     */
    protected function assertToolSuccess(ToolResult $result, ?string $message = null): void
    {
        $this->assertTrue(
            $result->success,
            $message ?? "Tool sollte erfolgreich sein, aber Fehler: " . ($result->error['message'] ?? 'Unbekannt')
        );
    }

    /**
     * Assertion: Tool-Result ist fehlgeschlagen
     */
    protected function assertToolFailed(ToolResult $result, ?string $expectedErrorCode = null): void
    {
        $this->assertFalse($result->success, "Tool sollte fehlgeschlagen sein");
        
        if ($expectedErrorCode) {
            $this->assertEquals(
                $expectedErrorCode,
                $result->errorCode ?? null,
                "Erwarteter Error-Code: {$expectedErrorCode}, erhalten: " . ($result->errorCode ?? 'null')
            );
        }
    }

    /**
     * Assertion: Tool-Result hat Daten
     */
    protected function assertToolHasData(ToolResult $result, ?array $expectedData = null): void
    {
        $this->assertNotNull($result->data, "Tool-Result sollte Daten enthalten");
        
        if ($expectedData !== null) {
            $this->assertEquals($expectedData, $result->data, "Tool-Result-Daten stimmen nicht überein");
        }
    }

    /**
     * Assertion: Tool-Result hat spezifisches Feld
     */
    protected function assertToolResultHasField(ToolResult $result, string $field, $expectedValue = null): void
    {
        $this->assertArrayHasKey($field, $result->data ?? [], "Tool-Result sollte Feld '{$field}' enthalten");
        
        if ($expectedValue !== null) {
            $this->assertEquals(
                $expectedValue,
                $result->data[$field] ?? null,
                "Feld '{$field}' sollte '{$expectedValue}' sein"
            );
        }
    }

    /**
     * Assertion: Tool ist registriert
     */
    protected function assertToolRegistered(string $toolName): void
    {
        $this->assertTrue(
            $this->registry->has($toolName),
            "Tool '{$toolName}' sollte registriert sein"
        );
    }

    /**
     * Assertion: Tool-Chain kann ausgeführt werden
     */
    protected function assertToolChainExecutable(
        string $mainToolName,
        array $arguments = [],
        ?ToolContext $context = null
    ): void {
        if ($context === null) {
            $context = $this->createMockContext();
        }

        $orchestrator = app(\Platform\Core\Tools\ToolOrchestrator::class);
        
        $result = $orchestrator->executeWithDependencies(
            $mainToolName,
            $arguments,
            $context,
            maxDepth: 5,
            planFirst: true
        );

        $this->assertToolSuccess($result, "Tool-Chain sollte erfolgreich ausgeführt werden können");
    }
}

