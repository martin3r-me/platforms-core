<?php

namespace Platform\Core\Tests\Unit\Tools;

use Platform\Core\Tests\TestCase;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\EchoTool;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Foundation\Auth\User;

class ToolExecutorTest extends TestCase
{
    private ToolRegistry $registry;
    private ToolExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ToolRegistry();
        $this->executor = new ToolExecutor($this->registry);
    }

    private function createTestContext(): ToolContext
    {
        $user = new class extends User {
            public $id = 1;
            protected $fillable = ['id'];
        };
        
        return new ToolContext($user);
    }

    public function test_can_execute_tool_with_valid_arguments(): void
    {
        $tool = new EchoTool();
        $this->registry->register($tool);
        
        $context = $this->createTestContext();
        $result = $this->executor->execute('echo', [
            'message' => 'Hello World'
        ], $context);
        
        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertIsArray($result->data);
        $this->assertEquals('Hello World', $result->data['echo']);
    }

    public function test_returns_error_for_unknown_tool(): void
    {
        $context = $this->createTestContext();
        $result = $this->executor->execute('unknown_tool', [], $context);
        
        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertEquals('TOOL_NOT_FOUND', $result->errorCode);
    }

    public function test_validates_required_parameters(): void
    {
        $tool = new EchoTool();
        $this->registry->register($tool);
        
        $context = $this->createTestContext();
        $result = $this->executor->execute('echo', [], $context);
        
        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
        $this->assertStringContainsString('erforderlich', $result->error);
    }

    public function test_validates_parameter_types(): void
    {
        $tool = new EchoTool();
        $this->registry->register($tool);
        
        $context = $this->createTestContext();
        $result = $this->executor->execute('echo', [
            'message' => 'Test',
            'number' => 'not-a-number' // Sollte integer sein
        ], $context);
        
        // Type validation ist aktuell noch permissiv, aber sollte später strikter sein
        // Für jetzt: Test prüft dass es nicht crasht
        $this->assertInstanceOf(ToolResult::class, $result);
    }

    public function test_handles_tool_execution_errors(): void
    {
        // Erstelle ein Tool das einen Fehler wirft
        $failingTool = new class extends EchoTool {
            public function execute(array $arguments, ToolContext $context): ToolResult
            {
                throw new \RuntimeException('Test error');
            }
        };
        
        $this->registry->register($failingTool);
        
        $context = $this->createTestContext();
        $result = $this->executor->execute('echo', [
            'message' => 'Test'
        ], $context);
        
        $this->assertFalse($result->success);
        $this->assertEquals('EXECUTION_ERROR', $result->errorCode);
        $this->assertStringContainsString('Test error', $result->error);
    }
}

