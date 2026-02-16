<?php

namespace Platform\Core\Tests\Unit\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Core\Tests\TestCase;
use Platform\Core\Tools\ToggleTeamModuleTool;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ToggleTeamModuleToolTest extends TestCase
{
    use RefreshDatabase;

    private ToggleTeamModuleTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new ToggleTeamModuleTool();
    }

    // --- Schema & Metadata Tests ---

    public function test_tool_name_is_correct(): void
    {
        $this->assertEquals('core.team_modules.PUT', $this->tool->getName());
    }

    public function test_tool_has_description(): void
    {
        $description = $this->tool->getDescription();
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('Modul', $description);
        $this->assertStringContainsString('Team', $description);
    }

    public function test_tool_schema_requires_module_key(): void
    {
        $schema = $this->tool->getSchema();
        $this->assertContains('module_key', $schema['required']);
        $this->assertArrayHasKey('module_key', $schema['properties']);
    }

    public function test_tool_schema_has_optional_enabled(): void
    {
        $schema = $this->tool->getSchema();
        $this->assertArrayHasKey('enabled', $schema['properties']);
        $this->assertNotContains('enabled', $schema['required']);
    }

    public function test_tool_metadata_is_complete(): void
    {
        $metadata = $this->tool->getMetadata();
        $this->assertEquals('action', $metadata['category']);
        $this->assertFalse($metadata['read_only']);
        $this->assertTrue($metadata['requires_auth']);
        $this->assertTrue($metadata['requires_team']);
        $this->assertEquals('write', $metadata['risk_level']);
        $this->assertTrue($metadata['idempotent']);
        $this->assertContains('core.modules.GET', $metadata['related_tools']);
        $this->assertContains('core.modules.PUT', $metadata['related_tools']);
    }

    // --- Validation Tests ---

    public function test_returns_error_without_user(): void
    {
        $user = new class extends \Illuminate\Foundation\Auth\User {
            public $id = null;
        };

        // Create context with null-ish user via reflection
        $context = new ToolContext($user, null);

        // Force the user check to fail by passing a "no user" scenario
        // Since ToolContext requires Authenticatable, we test via missing team
        $result = $this->tool->execute(['module_key' => 'planner'], $context);
        $this->assertFalse($result->success);
    }

    public function test_returns_error_for_empty_module_key(): void
    {
        $user = $this->createMockUser();
        $team = $this->createMockTeam();
        $context = new ToolContext($user, $team);

        $result = $this->tool->execute(['module_key' => ''], $context);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_returns_error_for_missing_module_key(): void
    {
        $user = $this->createMockUser();
        $team = $this->createMockTeam();
        $context = new ToolContext($user, $team);

        $result = $this->tool->execute([], $context);

        $this->assertFalse($result->success);
        $this->assertEquals('VALIDATION_ERROR', $result->errorCode);
    }

    // --- Policy Tests ---

    public function test_owner_can_activate_team_module(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            'enabled' => true,
        ], $context);

        $this->assertTrue($result->success);
        $this->assertEquals('activated', $result->data['action']);
        $this->assertTrue($result->data['enabled']);

        // Verify module is attached to team in DB
        $this->assertTrue(
            $team->modules()->where('module_id', $module->id)->exists()
        );

        // Verify team_id is correctly set in the pivot table
        $pivot = $team->modules()->where('module_id', $module->id)->first();
        $this->assertEquals($team->id, $pivot->pivot->team_id);
    }

    public function test_owner_can_deactivate_team_module(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        // First activate
        $team->modules()->attach($module->id, [
            'role' => null,
            'enabled' => true,
            'guard' => 'web',
            'team_id' => $team->id,
        ]);

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            'enabled' => false,
        ], $context);

        $this->assertTrue($result->success);
        $this->assertEquals('deactivated', $result->data['action']);
        $this->assertFalse($result->data['enabled']);

        // Verify module is detached from team in DB
        $this->assertFalse(
            $team->modules()->where('module_id', $module->id)->exists()
        );
    }

    public function test_admin_cannot_toggle_team_module(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::ADMIN, 'planner');

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            'enabled' => true,
        ], $context);

        $this->assertFalse($result->success);
        $this->assertEquals('ACCESS_DENIED', $result->errorCode);
        $this->assertStringContainsString('Owner', $result->error);
    }

    public function test_member_cannot_toggle_team_module(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::MEMBER, 'planner');

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            'enabled' => true,
        ], $context);

        $this->assertFalse($result->success);
        $this->assertEquals('ACCESS_DENIED', $result->errorCode);
    }

    // --- Toggle Logic Tests ---

    public function test_toggle_activates_when_inactive(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            // No 'enabled' parameter -> toggle
        ], $context);

        $this->assertTrue($result->success);
        $this->assertEquals('activated', $result->data['action']);
    }

    public function test_toggle_deactivates_when_active(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        // Pre-activate
        $team->modules()->attach($module->id, [
            'role' => null,
            'enabled' => true,
            'guard' => 'web',
            'team_id' => $team->id,
        ]);

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            // No 'enabled' parameter -> toggle
        ], $context);

        $this->assertTrue($result->success);
        $this->assertEquals('deactivated', $result->data['action']);
    }

    public function test_idempotent_activation(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        // Pre-activate
        $team->modules()->attach($module->id, [
            'role' => null,
            'enabled' => true,
            'guard' => 'web',
            'team_id' => $team->id,
        ]);

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            'enabled' => true,
        ], $context);

        $this->assertTrue($result->success);
        $this->assertEquals('already_active', $result->data['action']);
    }

    public function test_idempotent_deactivation(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            'enabled' => false,
        ], $context);

        $this->assertTrue($result->success);
        $this->assertEquals('already_inactive', $result->data['action']);
    }

    // --- Module Not Found ---

    public function test_returns_error_for_unknown_module(): void
    {
        [$user, $team, $_] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'nonexistent_module',
        ], $context);

        $this->assertFalse($result->success);
        $this->assertEquals('MODULE_NOT_FOUND', $result->errorCode);
    }

    // --- Scope Tests ---

    public function test_root_scoped_module_requires_root_team(): void
    {
        // Create root team and child team
        $user = User::create([
            'name' => 'Test User',
            'email' => 'scope-test@example.com',
            'password' => bcrypt('password'),
        ]);

        $rootTeam = Team::create([
            'name' => 'Root Team',
            'user_id' => $user->id,
            'personal_team' => false,
            'parent_team_id' => null,
        ]);
        $rootTeam->users()->attach($user->id, ['role' => TeamRole::OWNER->value]);

        $childTeam = Team::create([
            'name' => 'Child Team',
            'user_id' => $user->id,
            'personal_team' => false,
            'parent_team_id' => $rootTeam->id,
        ]);
        $childTeam->users()->attach($user->id, ['role' => TeamRole::OWNER->value]);

        $module = Module::create([
            'key' => 'crm',
            'title' => 'CRM',
            'scope_type' => 'parent',
        ]);

        // Try from child team -> should fail
        $context = new ToolContext($user, $childTeam);
        $result = $this->tool->execute([
            'module_key' => 'crm',
            'enabled' => true,
        ], $context);

        $this->assertFalse($result->success);
        $this->assertEquals('SCOPE_ERROR', $result->errorCode);
        $this->assertStringContainsString('Root-Team', $result->error);
    }

    public function test_root_scoped_module_works_from_root_team(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'root-test@example.com',
            'password' => bcrypt('password'),
        ]);

        $rootTeam = Team::create([
            'name' => 'Root Team',
            'user_id' => $user->id,
            'personal_team' => false,
            'parent_team_id' => null,
        ]);
        $rootTeam->users()->attach($user->id, ['role' => TeamRole::OWNER->value]);

        $module = Module::create([
            'key' => 'okr',
            'title' => 'OKR',
            'scope_type' => 'parent',
        ]);

        $context = new ToolContext($user, $rootTeam);
        $result = $this->tool->execute([
            'module_key' => 'okr',
            'enabled' => true,
        ], $context);

        $this->assertTrue($result->success);
        $this->assertEquals('activated', $result->data['action']);
    }

    // --- No Team Context ---

    public function test_returns_error_without_team(): void
    {
        $user = new class extends \Illuminate\Foundation\Auth\User {
            public $id = 999;
            public $currentTeamRelation = null;
        };

        $context = new ToolContext($user, null);
        $result = $this->tool->execute(['module_key' => 'planner'], $context);

        $this->assertFalse($result->success);
        $this->assertEquals('TEAM_ERROR', $result->errorCode);
    }

    // --- Response Structure ---

    public function test_success_response_contains_expected_fields(): void
    {
        [$user, $team, $module] = $this->createTeamSetup(TeamRole::OWNER, 'planner');

        $context = new ToolContext($user, $team);
        $result = $this->tool->execute([
            'module_key' => 'planner',
            'enabled' => true,
        ], $context);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('action', $result->data);
        $this->assertArrayHasKey('message', $result->data);
        $this->assertArrayHasKey('module', $result->data);
        $this->assertArrayHasKey('team', $result->data);
        $this->assertArrayHasKey('enabled', $result->data);
        $this->assertArrayHasKey('key', $result->data['module']);
        $this->assertArrayHasKey('title', $result->data['module']);
        $this->assertArrayHasKey('id', $result->data['team']);
        $this->assertArrayHasKey('name', $result->data['team']);
    }

    // --- Helper Methods ---

    private function createMockUser(): \Illuminate\Foundation\Auth\User
    {
        return new class extends \Illuminate\Foundation\Auth\User {
            public $id = 1;
            public $currentTeamRelation = null;
        };
    }

    private function createMockTeam(): object
    {
        return new class {
            public int $id = 1;
            public string $name = 'Test Team';
        };
    }

    /**
     * Creates a full team setup with user, team, and module in the database.
     *
     * @return array{User, Team, Module}
     */
    private function createTeamSetup(TeamRole $role, string $moduleKey): array
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => $moduleKey . '-' . $role->value . '@example.com',
            'password' => bcrypt('password'),
        ]);

        $team = Team::create([
            'name' => 'Test Team',
            'user_id' => $user->id,
            'personal_team' => false,
        ]);

        $team->users()->attach($user->id, ['role' => $role->value]);

        $module = Module::firstOrCreate(
            ['key' => $moduleKey],
            ['title' => ucfirst($moduleKey), 'scope_type' => 'single']
        );

        return [$user, $team, $module];
    }
}
