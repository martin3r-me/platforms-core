<?php

namespace Platform\Core\Tests\Unit\SemanticLayer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\SemanticLayer\Schema\LayerSchemaValidator;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\SemanticLayer\Services\SemanticLayerScaffold;
use Platform\Core\Tests\TestCase;

class SemanticLayerResolverTest extends TestCase
{
    use RefreshDatabase;

    private SemanticLayerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->resolver = new SemanticLayerResolver(
            new SemanticLayerScaffold(),
            new LayerSchemaValidator(),
        );
    }

    public function test_returns_empty_when_no_layer_exists(): void
    {
        $resolved = $this->resolver->resolveFor(null, null);
        $this->assertTrue($resolved->isEmpty());
    }

    public function test_resolves_global_layer_only(): void
    {
        $this->createGlobalLayerInPilot(['okr']);

        $resolved = $this->resolver->resolveFor(null, 'okr');

        $this->assertFalse($resolved->isEmpty());
        $this->assertEquals(['global'], $resolved->scope_chain);
        $this->assertEquals(['1.0.0'], $resolved->version_chain);
        $this->assertStringContainsString('Wir sind ehrliche', $resolved->rendered_block);
    }

    public function test_returns_empty_when_module_not_enabled(): void
    {
        $this->createGlobalLayerInPilot(['okr']); // nur OKR enabled

        $resolved = $this->resolver->resolveFor(null, 'canvas');

        $this->assertTrue($resolved->isEmpty());
    }

    public function test_module_gate_bypassed_by_production_status(): void
    {
        $global = $this->createGlobalLayerInPilot(['okr']);
        $global->status = SemanticLayer::STATUS_PRODUCTION;
        $global->save();

        // Obwohl canvas NICHT in enabled_modules → production bypasses
        $resolved = $this->resolver->resolveFor(null, 'canvas');

        $this->assertFalse($resolved->isEmpty());
    }

    public function test_team_extension_merges_and_deduplicates(): void
    {
        $this->createGlobalLayerInPilot(['okr']);
        $user = User::create([
            'name' => 'Test',
            'email' => 'resolver-test-' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
        ]);
        $team = Team::create([
            'name' => 'Test Team',
            'user_id' => $user->id,
            'personal_team' => false,
        ]);

        $teamLayer = SemanticLayer::create([
            'scope_type' => SemanticLayer::SCOPE_TEAM,
            'scope_id' => $team->id,
            'status' => SemanticLayer::STATUS_PILOT,
            'enabled_modules' => ['okr'],
        ]);
        $teamVersion = SemanticLayerVersion::create([
            'semantic_layer_id' => $teamLayer->id,
            'semver' => '0.2.0',
            'version_type' => SemanticLayerVersion::TYPE_MINOR,
            'perspektive' => 'Wir sind Venture X.',
            'ton' => ['klar', 'direkt'], // 'klar' bereits im Core
            'heuristiken' => ['Im Venture gilt: weniger ist mehr.'],
            'negativ_raum' => ['keine Fachwörter'],
            'token_count' => 50,
            'created_at' => now(),
        ]);
        $teamLayer->current_version_id = $teamVersion->id;
        $teamLayer->save();

        $resolved = $this->resolver->resolveFor($team, 'okr');

        // Perspektive: Extension override
        $this->assertEquals('Wir sind Venture X.', $resolved->perspektive);

        // Scope/Version-Chain
        $this->assertEquals(['global', 'team:' . $team->id], $resolved->scope_chain);
        $this->assertEquals(['1.0.0', '0.2.0'], $resolved->version_chain);

        // Ton: deduplicated — 'klar' taucht genau einmal auf
        $tonLower = array_map('mb_strtolower', $resolved->ton);
        $klarCount = count(array_filter($tonLower, fn ($v) => $v === 'klar'));
        $this->assertEquals(1, $klarCount);
        $this->assertContains('direkt', $resolved->ton);

        // Heuristiken / negativ_raum append
        $this->assertContains('Im Venture gilt: weniger ist mehr.', $resolved->heuristiken);
        $this->assertContains('keine Fachwörter', $resolved->negativ_raum);
    }

    public function test_cache_is_invalidated_on_version_save(): void
    {
        $global = $this->createGlobalLayerInPilot(['okr']);

        $first = $this->resolver->resolveFor(null, 'okr');
        $this->assertStringContainsString('Wir sind ehrliche', $first->rendered_block);

        // Neue Version anlegen und aktivieren
        $newVersion = SemanticLayerVersion::create([
            'semantic_layer_id' => $global->id,
            'semver' => '1.1.0',
            'version_type' => SemanticLayerVersion::TYPE_MINOR,
            'perspektive' => 'Wir sind NEU.',
            'ton' => ['frech'],
            'heuristiken' => ['anders denken'],
            'negativ_raum' => ['keine Floskeln'],
            'token_count' => 40,
            'created_at' => now(),
        ]);
        $global->current_version_id = $newVersion->id;
        $global->save();

        $second = $this->resolver->resolveFor(null, 'okr');
        $this->assertStringContainsString('Wir sind NEU.', $second->rendered_block);
    }

    public function test_draft_layer_is_not_resolved(): void
    {
        $global = $this->createGlobalLayerInPilot(['okr']);
        $global->status = SemanticLayer::STATUS_DRAFT;
        $global->save();

        $resolved = $this->resolver->resolveFor(null, 'okr');
        $this->assertTrue($resolved->isEmpty());
    }

    private function createGlobalLayerInPilot(array $enabledModules): SemanticLayer
    {
        $layer = SemanticLayer::create([
            'scope_type' => SemanticLayer::SCOPE_GLOBAL,
            'scope_id' => null,
            'status' => SemanticLayer::STATUS_PILOT,
            'enabled_modules' => $enabledModules,
        ]);
        $version = SemanticLayerVersion::create([
            'semantic_layer_id' => $layer->id,
            'semver' => '1.0.0',
            'version_type' => SemanticLayerVersion::TYPE_MINOR,
            'perspektive' => 'Wir sind ehrliche Handwerker.',
            'ton' => ['klar', 'präzise'],
            'heuristiken' => ['Im Zweifel: weniger sagen.'],
            'negativ_raum' => ['keine Buzzwords'],
            'token_count' => 120,
            'created_at' => now(),
        ]);
        $layer->current_version_id = $version->id;
        $layer->save();
        return $layer;
    }
}
