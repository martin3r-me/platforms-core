<?php

namespace Platform\Core\Tests\Unit\Services;

use Platform\Core\Services\ToolRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Mockery;

class ToolRegistryTest extends TestCase
{
    protected ToolRegistry $toolRegistry;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->toolRegistry = new ToolRegistry();
        Cache::flush();
    }
    
    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test: Tool Registry kann Tools generieren
     */
    public function test_can_generate_tools()
    {
        // Arrange
        $this->mockSchema();
        
        // Act
        $tools = $this->toolRegistry->getAllTools();
        
        // Assert
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);
        
        // Prüfe Tool-Struktur
        foreach ($tools as $tool) {
            $this->assertArrayHasKey('type', $tool);
            $this->assertArrayHasKey('function', $tool);
            $this->assertEquals('function', $tool['type']);
            $this->assertArrayHasKey('name', $tool['function']);
            $this->assertArrayHasKey('description', $tool['function']);
        }
    }
    
    /**
     * Test: Context-aware Tools funktionieren
     */
    public function test_contextual_tools_work()
    {
        // Arrange
        $this->mockSchema();
        $query = 'welche slots und aufgaben haben wir dort verplant?';
        
        // Act
        $tools = $this->toolRegistry->getContextualTools($query);
        
        // Assert
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);
        
        // Prüfe dass relevante Tools enthalten sind
        $toolNames = array_map(fn($tool) => $tool['function']['name'], $tools);
        $this->assertContains('plannerproject_get_all', $toolNames);
        $this->assertContains('plannerprojectslot_get_all', $toolNames);
        $this->assertContains('plannertask_get_all', $toolNames);
    }
    
    /**
     * Test: Module-spezifische Tools funktionieren
     */
    public function test_module_specific_tools_work()
    {
        // Arrange
        $this->mockSchema();
        
        // Act
        $plannerTools = $this->toolRegistry->getToolsForModule('planner');
        
        // Assert
        $this->assertIsArray($plannerTools);
        $this->assertNotEmpty($plannerTools);
        
        // Prüfe dass nur Planner Tools enthalten sind
        foreach ($plannerTools as $tool) {
            $toolName = $tool['function']['name'];
            $this->assertStringStartsWith('planner', $toolName);
        }
    }
    
    /**
     * Test: Cache funktioniert korrekt
     */
    public function test_cache_works_correctly()
    {
        // Arrange
        $this->mockSchema();
        
        // Act - Erste Generierung
        $startTime = microtime(true);
        $tools1 = $this->toolRegistry->getAllTools();
        $firstTime = microtime(true) - $startTime;
        
        // Act - Zweite Generierung (sollte aus Cache kommen)
        $startTime = microtime(true);
        $tools2 = $this->toolRegistry->getAllTools();
        $secondTime = microtime(true) - $startTime;
        
        // Assert
        $this->assertEquals($tools1, $tools2);
        $this->assertLessThan($firstTime, $secondTime); // Cache sollte schneller sein
    }
    
    /**
     * Test: Cache kann geleert werden
     */
    public function test_cache_can_be_cleared()
    {
        // Arrange
        $this->mockSchema();
        $this->toolRegistry->getAllTools(); // Generiere Tools
        
        // Act
        $this->toolRegistry->clearCache();
        
        // Assert
        $this->assertTrue(Cache::get('agent_tools') === null);
    }
    
    /**
     * Test: Fallback Models funktionieren
     */
    public function test_fallback_models_work()
    {
        // Arrange - Mock Composer Autoloader um leeren ClassMap zu simulieren
        $this->app->bind('autoloader', function() {
            $mock = Mockery::mock();
            $mock->shouldReceive('getClassMap')->andReturn([]);
            return $mock;
        });
        
        // Act
        $tools = $this->toolRegistry->getAllTools();
        
        // Assert
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);
    }
    
    /**
     * Test: OpenAI Tool Limit wird respektiert
     */
    public function test_openai_tool_limit_is_respected()
    {
        // Arrange
        $this->mockSchema();
        
        // Act
        $tools = $this->toolRegistry->getAllTools();
        
        // Assert
        $this->assertLessThanOrEqual(128, count($tools));
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
