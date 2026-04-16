<?php

namespace Platform\Core\Tests\Feature\SemanticLayer\McpTools;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\Tests\TestCase;
use Platform\Core\Tools\SemanticLayer\CreateVersionTool;
use Platform\Core\Tools\SemanticLayer\GetLayerTool;
use Platform\Core\Tools\SemanticLayer\GetResolvedTool;
use Platform\Core\Tools\SemanticLayer\ListLayersTool;
use Platform\Core\Tools\SemanticLayer\SetStatusTool;
use Platform\Core\Tools\SemanticLayer\ToggleModuleTool;

/**
 * Konsolidierter Feature-Test für die sechs Semantic-Layer-MCP-Tools.
 */
class SemanticLayerMcpToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ---------- Helpers ----------

    /**
     * @return array{User, Team}
     */
    private function ownerSetup(string $emailSuffix = ''): array
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner-' . uniqid() . $emailSuffix . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $team = Team::create([
            'name' => 'Owner Team',
            'user_id' => $user->id,
            'personal_team' => false,
        ]);
        $team->users()->attach($user->id, ['role' => TeamRole::OWNER->value]);

        return [$user, $team];
    }

    /**
     * @return array{User, Team}
     */
    private function memberSetup(string $emailSuffix = ''): array
    {
        $user = User::create([
            'name' => 'Member',
            'email' => 'member-' . uniqid() . $emailSuffix . '@example.com',
            'password' => bcrypt('password'),
        ]);
        $team = Team::create([
            'name' => 'Member Team',
            'user_id' => $user->id,
            'personal_team' => false,
        ]);
        $team->users()->attach($user->id, ['role' => TeamRole::MEMBER->value]);

        return [$user, $team];
    }

    private function ctx(User $user, Team $team): ToolContext
    {
        return new ToolContext(user: $user, team: $team, metadata: []);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'scope' => 'global',
            'semver' => '1.0.0',
            'version_type' => 'minor',
            'perspektive' => 'Wir sind ehrliche Handwerker, die Werkzeuge zuerst für sich selbst bauen.',
            'ton' => ['klar', 'direkt', 'kurze Sätze'],
            'heuristiken' => [
                'Im Zweifel: weniger sagen.',
                'Outcome immer explizit machen.',
            ],
            'negativ_raum' => ['keine Buzzwords', 'kein Weichspüler'],
        ], $overrides);
    }

    private function makeGlobalLayer(string $semver = '1.0.0', string $status = SemanticLayer::STATUS_PILOT): SemanticLayer
    {
        $layer = SemanticLayer::create([
            'scope_type' => SemanticLayer::SCOPE_GLOBAL,
            'scope_id' => null,
            'status' => $status,
            'enabled_modules' => [],
        ]);
        $version = SemanticLayerVersion::create([
            'semantic_layer_id' => $layer->id,
            'semver' => $semver,
            'version_type' => 'minor',
            'perspektive' => 'Initial-Perspektive für Tests.',
            'ton' => ['klar'],
            'heuristiken' => ['weniger ist mehr'],
            'negativ_raum' => ['keine Floskeln'],
            'token_count' => 30,
            'created_at' => now(),
        ]);
        $layer->current_version_id = $version->id;
        $layer->save();
        return $layer;
    }

    // ---------- Tests ----------

    public function test_list_layers_requires_owner_role(): void
    {
        [$user, $team] = $this->memberSetup();
        $tool = app(ListLayersTool::class);
        $result = $tool->execute([], $this->ctx($user, $team));

        $this->assertFalse($result->success);
        $this->assertSame('ACCESS_DENIED', $result->errorCode);
    }

    public function test_list_layers_returns_empty_when_none_exist(): void
    {
        [$user, $team] = $this->ownerSetup();
        $tool = app(ListLayersTool::class);
        $result = $tool->execute([], $this->ctx($user, $team));

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame(0, $result->data['count']);
        $this->assertSame([], $result->data['layers']);
    }

    public function test_create_version_creates_new_layer_and_activates_it(): void
    {
        [$user, $team] = $this->ownerSetup();
        $tool = app(CreateVersionTool::class);

        $result = $tool->execute($this->validPayload(), $this->ctx($user, $team));

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($result->data['layer_was_new']);
        $this->assertSame('1.0.0', $result->data['semver']);
        $this->assertGreaterThan(0, $result->data['token_count']);
        $this->assertNotEmpty($result->data['rendered_block']);

        $layer = SemanticLayer::find($result->data['layer_id']);
        $this->assertNotNull($layer);
        $this->assertSame(SemanticLayer::STATUS_PILOT, $layer->status);
        $this->assertSame($result->data['version_id'], $layer->current_version_id);

        $this->assertDatabaseHas('semantic_layer_audit', [
            'semantic_layer_id' => $layer->id,
            'action' => 'created',
        ]);
    }

    public function test_create_version_adds_new_version_to_existing_layer(): void
    {
        [$user, $team] = $this->ownerSetup();
        $existing = $this->makeGlobalLayer('1.0.0');

        $tool = app(CreateVersionTool::class);
        $result = $tool->execute(
            $this->validPayload(['semver' => '1.1.0']),
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertFalse($result->data['layer_was_new']);
        $this->assertSame($existing->id, $result->data['layer_id']);

        $existing->refresh();
        $this->assertSame($result->data['version_id'], $existing->current_version_id);
        $this->assertSame(2, $existing->versions()->count());

        $this->assertDatabaseHas('semantic_layer_audit', [
            'semantic_layer_id' => $existing->id,
            'action' => 'version_created',
        ]);
    }

    public function test_create_version_rejects_duplicate_semver(): void
    {
        [$user, $team] = $this->ownerSetup();
        $this->makeGlobalLayer('1.0.0');

        $tool = app(CreateVersionTool::class);
        $result = $tool->execute(
            $this->validPayload(['semver' => '1.0.0']),
            $this->ctx($user, $team)
        );

        $this->assertFalse($result->success);
        $this->assertSame('VERSION_EXISTS', $result->errorCode);
    }

    public function test_create_version_rejects_invalid_schema(): void
    {
        [$user, $team] = $this->ownerSetup();
        $tool = app(CreateVersionTool::class);

        $result = $tool->execute(
            $this->validPayload(['ton' => []]),
            $this->ctx($user, $team)
        );

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
        $this->assertStringContainsString('ton', $result->error ?? '');
    }

    public function test_create_version_emits_budget_warning_when_small(): void
    {
        [$user, $team] = $this->ownerSetup();
        $tool = app(CreateVersionTool::class);

        // Sehr knapper Payload → Token-Count unter Soft-Min (80)
        $result = $tool->execute(
            $this->validPayload([
                'perspektive' => 'kurz',
                'ton' => ['a'],
                'heuristiken' => ['b'],
                'negativ_raum' => ['c'],
            ]),
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertArrayHasKey('budget_warning', $result->data);
        $this->assertLessThan(80, $result->data['token_count']);
    }

    public function test_get_layer_returns_full_current_version_content(): void
    {
        [$user, $team] = $this->ownerSetup();
        $this->makeGlobalLayer('1.0.0');

        $tool = app(GetLayerTool::class);
        $result = $tool->execute(['scope' => 'global'], $this->ctx($user, $team));

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('global', $result->data['layer']['scope_type']);
        $this->assertSame('Initial-Perspektive für Tests.', $result->data['layer']['current_version']['perspektive']);
        $this->assertSame(['klar'], $result->data['layer']['current_version']['ton']);
        $this->assertSame('1.0.0', $result->data['layer']['current_version']['semver']);
    }

    public function test_set_status_writes_audit_and_busts_cache(): void
    {
        [$user, $team] = $this->ownerSetup();
        $layer = $this->makeGlobalLayer('1.0.0', SemanticLayer::STATUS_DRAFT);

        $tool = app(SetStatusTool::class);
        $result = $tool->execute(
            ['scope' => 'global', 'status' => SemanticLayer::STATUS_PILOT],
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($result->data['changed']);
        $this->assertSame(SemanticLayer::STATUS_DRAFT, $result->data['previous_status']);
        $this->assertSame(SemanticLayer::STATUS_PILOT, $result->data['status']);

        $layer->refresh();
        $this->assertSame(SemanticLayer::STATUS_PILOT, $layer->status);

        $audit = SemanticLayerAudit::where('semantic_layer_id', $layer->id)
            ->where('action', 'status_changed')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('draft', $audit->diff[0]['from'] ?? null);
        $this->assertSame('pilot', $audit->diff[0]['to'] ?? null);
    }

    public function test_set_status_to_production_includes_warning(): void
    {
        [$user, $team] = $this->ownerSetup();
        $this->makeGlobalLayer('1.0.0', SemanticLayer::STATUS_PILOT);

        $tool = app(SetStatusTool::class);
        $result = $tool->execute(
            ['scope' => 'global', 'status' => SemanticLayer::STATUS_PRODUCTION],
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertArrayHasKey('warning_production_broadens_scope', $result->data);
    }

    public function test_module_toggle_rejects_unknown_module_key(): void
    {
        [$user, $team] = $this->ownerSetup();
        $this->makeGlobalLayer('1.0.0');

        $tool = app(ToggleModuleTool::class);
        $result = $tool->execute(
            ['scope' => 'global', 'module' => 'definitely-not-a-module-' . uniqid(), 'enabled' => true],
            $this->ctx($user, $team)
        );

        $this->assertFalse($result->success);
        $this->assertSame('UNKNOWN_MODULE', $result->errorCode);
    }

    public function test_module_toggle_enables_known_module(): void
    {
        [$user, $team] = $this->ownerSetup();
        $layer = $this->makeGlobalLayer('1.0.0');
        Module::firstOrCreate(
            ['key' => 'okr'],
            ['title' => 'OKR', 'scope_type' => 'single']
        );

        $tool = app(ToggleModuleTool::class);
        $result = $tool->execute(
            ['scope' => 'global', 'module' => 'okr', 'enabled' => true],
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($result->data['changed']);
        $this->assertContains('okr', $result->data['enabled_modules']);

        $layer->refresh();
        $this->assertContains('okr', $layer->enabled_modules ?? []);
    }

    public function test_resolved_get_returns_rendered_block_when_active(): void
    {
        [$user, $team] = $this->ownerSetup();
        $layer = $this->makeGlobalLayer('1.0.0', SemanticLayer::STATUS_PILOT);
        $layer->enabled_modules = ['okr'];
        $layer->save();
        Module::firstOrCreate(
            ['key' => 'okr'],
            ['title' => 'OKR', 'scope_type' => 'single']
        );

        $tool = app(GetResolvedTool::class);
        $result = $tool->execute(['module' => 'okr'], $this->ctx($user, $team));

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($result->data['active']);
        $this->assertNotEmpty($result->data['rendered_block']);
        $this->assertContains('global', $result->data['scope_chain']);
        $this->assertContains('1.0.0', $result->data['version_chain']);
    }

    public function test_resolved_get_reports_module_not_enabled(): void
    {
        [$user, $team] = $this->ownerSetup();
        $this->makeGlobalLayer('1.0.0', SemanticLayer::STATUS_PILOT); // enabled_modules = []

        $tool = app(GetResolvedTool::class);
        $result = $tool->execute(['module' => 'okr'], $this->ctx($user, $team));

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertFalse($result->data['active']);
        $this->assertSame('module_not_enabled', $result->data['reason']);
    }
}
