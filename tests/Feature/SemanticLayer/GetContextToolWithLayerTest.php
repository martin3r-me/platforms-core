<?php

namespace Platform\Core\Tests\Feature\SemanticLayer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\Tests\TestCase;
use Platform\Core\Tools\GetContextTool;

class GetContextToolWithLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_result_has_no_semantic_layer_when_empty(): void
    {
        $tool = new GetContextTool();
        $user = User::create([
            'name' => 'Test User',
            'email' => 'layer-test-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $team = Team::create(['name' => 'Test', 'user_id' => $user->id, 'personal_team' => false]);

        $ctx = new ToolContext(
            user: $user,
            team: $team,
            metadata: [],
        );
        $result = $tool->execute(
            arguments: ['include_metadata' => false, 'include_modules' => false],
            context: $ctx,
        );

        $this->assertTrue($result->success);
        $data = $result->data;
        $this->assertArrayNotHasKey('semantic_layer', $data);
    }

    public function test_result_includes_semantic_layer_when_active_production(): void
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
            'perspektive' => 'Test Perspektive.',
            'ton' => ['klar'],
            'heuristiken' => ['weniger'],
            'negativ_raum' => ['nicht dies'],
            'token_count' => 25,
            'created_at' => now(),
        ]);
        $layer->current_version_id = $version->id;
        $layer->save();

        $tool = new GetContextTool();
        $user = User::create([
            'name' => 'Test User',
            'email' => 'layer-test-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $team = Team::create(['name' => 'Test', 'user_id' => $user->id, 'personal_team' => false]);

        $ctx = new ToolContext(
            user: $user,
            team: $team,
            metadata: ['module' => 'anything'],
        );
        $result = $tool->execute(
            arguments: ['include_metadata' => false, 'include_modules' => false],
            context: $ctx,
        );

        $this->assertTrue($result->success);
        $data = $result->data;
        $this->assertArrayHasKey('semantic_layer', $data);
        $this->assertEquals(['global'], $data['semantic_layer']['scope_chain']);
        $this->assertEquals('Test Perspektive.', $data['semantic_layer']['perspektive']);
    }
}
