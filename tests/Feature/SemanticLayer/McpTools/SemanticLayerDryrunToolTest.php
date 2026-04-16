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
use Platform\Core\SemanticLayer\Models\SemanticLayerVersion;
use Platform\Core\Services\OpenAiService;
use Platform\Core\Tests\TestCase;
use Platform\Core\Tools\SemanticLayer\DryrunTool;

/**
 * Feature-Test für core.semantic_layer.dryrun.POST.
 *
 * OpenAiService wird gegen ein anonymes Fake ersetzt — wir testen ausschließlich
 * die Adapter-Logik: Owner-Check, Validation, Resolver-Probe, Options-Bau und
 * das Zusammenbauen der Response. Den LLM-Call selbst beobachten wir nur
 * indirekt (captured messages + options).
 */
class SemanticLayerDryrunToolTest extends TestCase
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
    private function ownerSetup(): array
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner-' . uniqid() . '@example.com',
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
    private function memberSetup(): array
    {
        $user = User::create([
            'name' => 'Member',
            'email' => 'member-' . uniqid() . '@example.com',
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
            'perspektive' => 'Initial-Perspektive für Dryrun-Tests.',
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

    /**
     * Ersetzt OpenAiService im Container durch ein Fake, das Aufrufe mitschneidet
     * und eine feste Antwort zurückgibt.
     *
     * @return object Fake-Service mit $calls-Property (array of [messages, model, options])
     */
    private function fakeOpenAi(string $content = 'ok'): object
    {
        $fake = new class($content) {
            public array $calls = [];
            public function __construct(public readonly string $content) {}

            public function chat(array $messages, string $model = 'gpt-test', array $options = []): array
            {
                $this->calls[] = [
                    'messages' => $messages,
                    'model' => $model,
                    'options' => $options,
                ];
                return [
                    'content' => $this->content,
                    'model' => $model,
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                    'tool_calls' => null,
                    'finish_reason' => 'stop',
                ];
            }
        };

        $this->app->instance(OpenAiService::class, $fake);

        return $fake;
    }

    // ---------- Tests ----------

    public function test_dryrun_requires_owner_role(): void
    {
        $this->fakeOpenAi();
        [$user, $team] = $this->memberSetup();

        $tool = app(DryrunTool::class);
        $result = $tool->execute(
            ['prompt' => 'Hallo'],
            $this->ctx($user, $team)
        );

        $this->assertFalse($result->success);
        $this->assertSame('ACCESS_DENIED', $result->errorCode);
    }

    public function test_dryrun_rejects_empty_prompt(): void
    {
        $this->fakeOpenAi();
        [$user, $team] = $this->ownerSetup();

        $tool = app(DryrunTool::class);
        $result = $tool->execute(
            ['prompt' => '   '],
            $this->ctx($user, $team)
        );

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_dryrun_returns_layer_active_true_when_layer_resolved(): void
    {
        $fake = $this->fakeOpenAi('Layer-geprägte Antwort');
        [$user, $team] = $this->ownerSetup();

        $layer = $this->makeGlobalLayer('1.0.0', SemanticLayer::STATUS_PILOT);
        $layer->enabled_modules = ['planner'];
        $layer->save();
        Module::firstOrCreate(
            ['key' => 'planner'],
            ['title' => 'Planner', 'scope_type' => 'single']
        );

        $tool = app(DryrunTool::class);
        $result = $tool->execute(
            ['prompt' => 'Was sollte ich heute als erstes anpacken?', 'module' => 'planner'],
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('Layer-geprägte Antwort', $result->data['content']);
        $this->assertTrue($result->data['layer_active']);
        $this->assertIsArray($result->data['layer_meta']);
        $this->assertContains('global', $result->data['layer_meta']['scope_chain']);
        $this->assertContains('1.0.0', $result->data['layer_meta']['version_chain']);
        $this->assertNotEmpty($result->data['layer_meta']['rendered_block']);
        $this->assertArrayNotHasKey('reason', $result->data);
        $this->assertSame('planner', $result->data['module']);
        $this->assertSame($team->id, $result->data['team_id']);
        $this->assertCount(1, $fake->calls);
    }

    public function test_dryrun_returns_layer_active_false_with_reason_when_no_match(): void
    {
        $this->fakeOpenAi('Antwort ohne Layer');
        [$user, $team] = $this->ownerSetup();
        // Kein Layer im Scope angelegt → reason=no_layer_in_scope

        $tool = app(DryrunTool::class);
        $result = $tool->execute(
            ['prompt' => 'Test', 'module' => 'planner'],
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertFalse($result->data['layer_active']);
        $this->assertNull($result->data['layer_meta']);
        $this->assertSame('no_layer_in_scope', $result->data['reason']);
    }

    public function test_dryrun_passes_source_module_to_openai_options(): void
    {
        $fake = $this->fakeOpenAi();
        [$user, $team] = $this->ownerSetup();

        $tool = app(DryrunTool::class);
        $result = $tool->execute(
            [
                'prompt' => 'Hallo',
                'module' => 'planner',
                'max_tokens' => 123,
                'temperature' => 0.3,
            ],
            $this->ctx($user, $team)
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertCount(1, $fake->calls);

        $call = $fake->calls[0];
        $this->assertSame('planner', $call['options']['source_module'] ?? null);
        $this->assertTrue($call['options']['with_context'] ?? null);
        $this->assertFalse($call['options']['tools'] ?? null);
        $this->assertSame(123, $call['options']['max_tokens'] ?? null);
        $this->assertSame(0.3, $call['options']['temperature'] ?? null);

        // messages: nur user-message (kein optional system)
        $this->assertCount(1, $call['messages']);
        $this->assertSame('user', $call['messages'][0]['role']);
        $this->assertSame('Hallo', $call['messages'][0]['content']);
    }
}
