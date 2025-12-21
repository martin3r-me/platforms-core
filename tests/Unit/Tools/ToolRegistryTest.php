<?php

namespace Platform\Core\Tests\Unit\Tools;

use Platform\Core\Tests\TestCase;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\EchoTool;
use Platform\Core\Contracts\ToolContract;

class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ToolRegistry();
    }

    public function test_can_register_tool(): void
    {
        $tool = new EchoTool();
        
        $this->registry->register($tool);
        
        $this->assertTrue($this->registry->has('echo'));
    }

    public function test_can_get_registered_tool(): void
    {
        $tool = new EchoTool();
        $this->registry->register($tool);
        
        $retrieved = $this->registry->get('echo');
        
        $this->assertInstanceOf(ToolContract::class, $retrieved);
        $this->assertInstanceOf(EchoTool::class, $retrieved);
        $this->assertEquals('echo', $retrieved->getName());
    }

    public function test_returns_null_for_unknown_tool(): void
    {
        $result = $this->registry->get('unknown_tool');
        
        $this->assertNull($result);
    }

    public function test_has_returns_false_for_unknown_tool(): void
    {
        $this->assertFalse($this->registry->has('unknown_tool'));
    }

    public function test_can_get_all_tools(): void
    {
        $tool1 = new EchoTool();
        $this->registry->register($tool1);
        
        $all = $this->registry->all();
        
        $this->assertIsArray($all);
        $this->assertArrayHasKey('echo', $all);
        $this->assertCount(1, $all);
    }

    public function test_can_get_all_tool_names(): void
    {
        $tool = new EchoTool();
        $this->registry->register($tool);
        
        $names = $this->registry->names();
        
        $this->assertIsArray($names);
        $this->assertContains('echo', $names);
    }
}

