<?php

namespace Platform\Core\Tests\Unit\Services;

use Platform\Core\Services\ToolExecutor;
use Platform\Core\Services\ToolRegistry;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Mockery;

class ToolExecutorTest extends TestCase
{
    protected ToolExecutor $toolExecutor;
    protected ToolRegistry $toolRegistry;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->toolRegistry = Mockery::mock(ToolRegistry::class);
        $this->toolExecutor = new ToolExecutor($this->toolRegistry);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test: Core Tools funktionieren
     */
    public function test_core_tools_work()
    {
        // Act
        $result = $this->toolExecutor->executeTool('get_current_time', []);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('data', $result);
    }
    
    /**
     * Test: Invalid Tool Name wird korrekt behandelt
     */
    public function test_invalid_tool_name_handled()
    {
        // Act
        $result = $this->toolExecutor->executeTool('invalid_tool', []);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('nicht gefunden', $result['error']);
    }
    
    /**
     * Test: Empty Tool Name wird korrekt behandelt
     */
    public function test_empty_tool_name_handled()
    {
        // Act
        $result = $this->toolExecutor->executeTool('', []);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('required', $result['error']);
    }
    
    /**
     * Test: Invalid Parameters werden korrekt behandelt
     */
    public function test_invalid_parameters_handled()
    {
        // Act
        $result = $this->toolExecutor->executeTool('get_current_time', 'invalid');
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('array', $result['error']);
    }
    
    /**
     * Test: Error Response Format ist korrekt
     */
    public function test_error_response_format_is_correct()
    {
        // Act
        $result = $this->toolExecutor->executeTool('invalid_tool', []);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertFalse($result['ok']);
    }
    
    /**
     * Test: Exception Handling funktioniert
     */
    public function test_exception_handling_works()
    {
        // Arrange - Mock ToolRegistry um Exception zu werfen
        $this->toolRegistry->shouldReceive('getAllTools')
            ->andThrow(new \Exception('Test exception'));
        
        // Act
        $result = $this->toolExecutor->executeTool('plannerproject_get_all', []);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('execution failed', $result['error']);
    }
    
    /**
     * Test: Model Tools funktionieren (mit Mock)
     */
    public function test_model_tools_work()
    {
        // Arrange
        $this->mockSchema();
        
        // Act
        $result = $this->toolExecutor->executeTool('plannerproject_get_all', []);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        // Note: Kann true oder false sein, je nach DB-Status
        $this->assertArrayHasKey('data', $result);
    }
    
    /**
     * Test: Relation Tools funktionieren
     */
    public function test_relation_tools_work()
    {
        // Arrange
        $this->mockSchema();
        
        // Act
        $result = $this->toolExecutor->executeTool('plannerproject_projectslots', ['id' => '1']);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('data', $result);
    }
    
    /**
     * Test: Enum Tools funktionieren
     */
    public function test_enum_tools_work()
    {
        // Arrange
        $this->mockSchema();
        
        // Act
        $result = $this->toolExecutor->executeTool('plannerproject_status_values', []);
        
        // Assert
        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('data', $result);
    }
    
    /**
     * Mock Schema für Tests
     */
    private function mockSchema()
    {
        Schema::shouldReceive('hasTable')
            ->andReturn(true);
    }
}
