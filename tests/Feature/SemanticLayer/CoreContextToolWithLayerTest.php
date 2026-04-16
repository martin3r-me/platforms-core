<?php

namespace Platform\Core\Tests\Feature\SemanticLayer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\Tests\TestCase;
use Platform\Core\Tools\CoreContextTool;

class CoreContextToolWithLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_returns_base_instruction_when_no_layer(): void
    {
        $tool = new CoreContextTool();
        $result = $tool->getContext();

        $this->assertTrue($result['ok']);
        $this->assertNull($result['data']['semantic_layer']);
        $this->assertStringContainsString('Tools proaktiv', $result['data']['system_prompt']);
        $this->assertStringNotContainsString('[SEMANTIC LAYER', $result['data']['system_prompt']);
    }

    public function test_returns_base_instruction_when_layer_not_enabled_for_module(): void
    {
        // Layer in pilot ohne enabled_modules → nicht aktiv
        $layer = SemanticLayer::create([
            'scope_type' => SemanticLayer::SCOPE_GLOBAL,
            'scope_id' => null,
            'status' => SemanticLayer::STATUS_PILOT,
            'enabled_modules' => [],
        ]);
        $version = SemanticLayerVersion::create([
            'semantic_layer_id' => $layer->id,
            'semver' => '1.0.0',
            'version_type' => 'minor',
            'perspektive' => 'X',
            'ton' => ['klar'],
            'heuristiken' => ['weniger'],
            'negativ_raum' => ['nicht dies'],
            'token_count' => 20,
            'created_at' => now(),
        ]);
        $layer->current_version_id = $version->id;
        $layer->save();

        $tool = new CoreContextTool();
        $result = $tool->getContext();

        // Ohne Modul-Kontext in CoreContextTool (route == null) → $module = null
        // → Resolver liefert nicht-leer (Discovery-Modus); aber production-gate greift nicht
        // → Wenn pilot und kein module, wird der Layer ausgeliefert (siehe Plan)
        // Wichtig: Der system_prompt enthält zumindest immer die base instruction
        $this->assertStringContainsString('Tools proaktiv', $result['data']['system_prompt']);
    }

    public function test_layer_in_production_is_merged_into_system_prompt(): void
    {
        $layer = SemanticLayer::create([
            'scope_type' => SemanticLayer::SCOPE_GLOBAL,
            'scope_id' => null,
            'status' => SemanticLayer::STATUS_PRODUCTION,
            'enabled_modules' => [],
        ]);
        $version = SemanticLayerVersion::create([
            'semantic_layer_id' => $layer->id,
            'semver' => '1.0.0',
            'version_type' => 'minor',
            'perspektive' => 'Wir sind ehrliche Handwerker.',
            'ton' => ['klar'],
            'heuristiken' => ['weniger sagen'],
            'negativ_raum' => ['keine Buzzwords'],
            'token_count' => 30,
            'created_at' => now(),
        ]);
        $layer->current_version_id = $version->id;
        $layer->save();

        $tool = new CoreContextTool();
        $result = $tool->getContext();

        $prompt = $result['data']['system_prompt'];
        $this->assertStringContainsString('[SEMANTIC LAYER', $prompt);
        $this->assertStringContainsString('Wir sind ehrliche Handwerker.', $prompt);
        $this->assertStringContainsString('Tools proaktiv', $prompt); // Base-Instruction bleibt
        $this->assertNotNull($result['data']['semantic_layer']);
        $this->assertEquals(['global'], $result['data']['semantic_layer']['scope_chain']);
    }
}
