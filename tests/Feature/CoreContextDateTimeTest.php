<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Enums\ContextDateTimeKind;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Models\CoreContextDateTimeOccurrence;
use Platform\Core\Tests\TestCase;

class CoreContextDateTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    public function test_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('core_context_date_times'));
        $this->assertTrue(Schema::hasTable('core_context_date_time_occurrences'));

        $this->assertTrue(Schema::hasColumns('core_context_date_times', [
            'id', 'context_type', 'context_id', 'kind', 'label', 'starts_at',
            'ends_at', 'timezone', 'recurrence_rrule', 'is_all_day', 'source',
            'calendar_sync_enabled', 'icalendar_uid', 'last_synced_at', 'team_id',
            'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('core_context_date_time_occurrences', [
            'id', 'core_context_date_time_id', 'starts_at', 'ends_at',
            'is_exception', 'created_at', 'updated_at',
        ]));
    }

    public function test_factory_persists_and_casts(): void
    {
        $dt = CoreContextDateTime::factory()->ofKind(ContextDateTimeKind::Deadline)->create();

        $fresh = $dt->fresh();

        $this->assertInstanceOf(ContextDateTimeKind::class, $fresh->kind);
        $this->assertSame(ContextDateTimeKind::Deadline, $fresh->kind);
        $this->assertSame('Europe/Berlin', $fresh->timezone);
        $this->assertFalse($fresh->is_all_day);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->starts_at);
    }

    public function test_soft_deletes(): void
    {
        $dt = CoreContextDateTime::factory()->create();
        $id = $dt->id;

        $dt->delete();

        $this->assertSoftDeleted('core_context_date_times', ['id' => $id]);
        $this->assertNull(CoreContextDateTime::find($id));
        $this->assertNotNull(CoreContextDateTime::withTrashed()->find($id));
    }

    public function test_occurrence_relation(): void
    {
        $dt = CoreContextDateTime::factory()->recurring()->create();

        CoreContextDateTimeOccurrence::factory()
            ->count(3)
            ->for($dt, 'dateTime')
            ->create();

        $this->assertCount(3, $dt->occurrences);
        $this->assertTrue($dt->isRecurring());
    }

    public function test_scopes_filter(): void
    {
        CoreContextDateTime::factory()->forContext('project', 42)->ofKind(ContextDateTimeKind::Due)->forTeam(7)->create();
        CoreContextDateTime::factory()->forContext('project', 99)->forTeam(8)->create();

        $this->assertSame(1, CoreContextDateTime::forContext('project', 42)->count());
        $this->assertSame(1, CoreContextDateTime::forTeam(7)->count());
        $this->assertSame(1, CoreContextDateTime::ofKind(ContextDateTimeKind::Due)->count());
    }

    public function test_kind_enum_values(): void
    {
        $this->assertSame(
            ['start', 'end', 'due', 'milestone', 'reminder', 'review', 'deadline', 'custom'],
            ContextDateTimeKind::values()
        );
    }
}
