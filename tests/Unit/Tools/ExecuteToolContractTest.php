<?php

namespace Platform\Core\Tests\Unit\Tools;

use Platform\Core\Tests\TestCase;
use Platform\Core\Mcp\Tools\ExecuteToolContract;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\EchoTool;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Foundation\Auth\User;

class ExecuteToolContractTest extends TestCase
{
    private ExecuteToolContract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contract = new ExecuteToolContract();

        $registry = app(ToolRegistry::class);
        if (!$registry->has('echo')) {
            $registry->register(new EchoTool());
        }
    }

    private function createTestContext(): ToolContext
    {
        $user = new class extends User {
            public $id = 1;
            protected $fillable = ['id'];
        };

        return new ToolContext($user);
    }

    public function test_can_execute_with_valid_tool_and_arguments(): void
    {
        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
        ], $this->createTestContext());

        $this->assertTrue($result->success);
        $this->assertEquals('Hallo', $result->data['echo']);
    }

    public function test_rejects_params_instead_of_arguments(): void
    {
        $result = $this->contract->execute([
            'tool' => 'echo',
            'params' => ['message' => 'Hallo'],
        ], $this->createTestContext());

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_PAYLOAD', $result->errorCode);
        $this->assertStringContainsString('params', $result->error);
        $this->assertStringContainsString('arguments', $result->error);
    }

    public function test_rejects_unknown_top_level_key(): void
    {
        $result = $this->contract->execute([
            'tool' => 'echo',
            'arguments' => ['message' => 'Hallo'],
            'foo' => 'bar',
        ], $this->createTestContext());

        $this->assertFalse($result->success);
        $this->assertEquals('INVALID_PAYLOAD', $result->errorCode);
        $this->assertStringContainsString('foo', $result->error);
    }
}
