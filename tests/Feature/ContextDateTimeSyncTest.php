<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Enums\ContextDateTimeKind;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Services\ContextDateTimes\ContextDateTimeSynchronizer;
use Platform\Core\Tests\TestCase;
use Platform\Core\Traits\HasContextDateTimes;

/**
 * Deckt den generischen Dual-Write (Trait/Observer/Synchronizer) und den
 * Backfill-Command ab – exemplarisch über ein Test-Model, das due_date auf
 * ContextDateTimeKind::Due spiegelt (analog planner_tasks.due_date).
 */
class ContextDateTimeSyncTest extends TestCase
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

        $app['config']->set('core.context_date_times.sync', [
            SyncTestTask::class => [
                'due_date' => ['kind' => ContextDateTimeKind::Due, 'label' => 'Fällig'],
            ],
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('sync_test_tasks')) {
            Schema::create('sync_test_tasks', function ($table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->string('title');
                $table->dateTime('due_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function makeTask(?string $due = '2026-09-01 10:00:00'): SyncTestTask
    {
        return SyncTestTask::create([
            'team_id' => 7,
            'title' => 'Demo',
            'due_date' => $due,
        ]);
    }

    private function mirror(SyncTestTask $task): ?CoreContextDateTime
    {
        return CoreContextDateTime::withTrashed()
            ->where('context_type', $task->getMorphClass())
            ->where('context_id', $task->getKey())
            ->where('source', 'migrated_from:sync_test_tasks.due_date')
            ->first();
    }

    public function test_create_dual_writes_mirror_and_occurrence(): void
    {
        $task = $this->makeTask();

        $mirror = $this->mirror($task);

        $this->assertNotNull($mirror);
        $this->assertSame(ContextDateTimeKind::Due, $mirror->kind);
        $this->assertSame('Fällig', $mirror->label);
        $this->assertSame(7, $mirror->team_id);
        $this->assertTrue($mirror->starts_at->equalTo(Carbon::parse('2026-09-01 10:00:00')));
        $this->assertNull($mirror->deleted_at);

        // Occurrence-Schatten (genau eine, nicht wiederkehrend).
        $this->assertCount(1, $mirror->occurrences);
        $this->assertTrue($mirror->occurrences->first()->starts_at->equalTo(Carbon::parse('2026-09-01 10:00:00')));
    }

    public function test_update_due_date_keeps_both_sources_consistent(): void
    {
        $task = $this->makeTask();

        $task->update(['due_date' => '2026-12-24 08:00:00']);

        $task->refresh();
        $mirror = $this->mirror($task);

        // Beide Quellen zeigen denselben Zeitpunkt.
        $this->assertTrue($task->due_date->equalTo($mirror->starts_at));
        $this->assertTrue($mirror->starts_at->equalTo(Carbon::parse('2026-12-24 08:00:00')));

        // Kein Duplikat entstanden.
        $this->assertSame(1, CoreContextDateTime::query()
            ->where('context_id', $task->getKey())
            ->where('source', 'migrated_from:sync_test_tasks.due_date')
            ->count());

        // Occurrence mitgezogen.
        $this->assertCount(1, $mirror->occurrences);
        $this->assertTrue($mirror->occurrences->first()->starts_at->equalTo(Carbon::parse('2026-12-24 08:00:00')));
    }

    public function test_clearing_due_date_soft_deletes_mirror(): void
    {
        $task = $this->makeTask();
        $this->assertNull($this->mirror($task)->deleted_at);

        $task->update(['due_date' => null]);

        $mirror = $this->mirror($task);
        $this->assertNotNull($mirror->deleted_at);
        $this->assertNull(CoreContextDateTime::find($mirror->id));
    }

    public function test_setting_due_date_again_restores_mirror(): void
    {
        $task = $this->makeTask();
        $task->update(['due_date' => null]);
        $this->assertNotNull($this->mirror($task)->deleted_at);

        $task->update(['due_date' => '2027-01-15 09:00:00']);

        $mirror = $this->mirror($task);
        $this->assertNull($mirror->deleted_at);
        $this->assertTrue($mirror->starts_at->equalTo(Carbon::parse('2027-01-15 09:00:00')));

        // Es bleibt bei genau einer Row (Restore statt Neuanlage).
        $this->assertSame(1, CoreContextDateTime::withTrashed()
            ->where('context_id', $task->getKey())->count());
    }

    public function test_deleting_task_soft_deletes_mirror(): void
    {
        $task = $this->makeTask();

        $task->delete();

        $this->assertNotNull($this->mirror($task)->deleted_at);
    }

    public function test_synchronizer_is_idempotent(): void
    {
        $task = $this->makeTask();
        $map = ['due_date' => ['kind' => ContextDateTimeKind::Due, 'label' => 'Fällig']];

        $sync = app(ContextDateTimeSynchronizer::class);

        // Erster erneuter Lauf: nichts ändert sich (Observer hat bereits gespiegelt).
        $result = $sync->sync($task, $map);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['unchanged']);

        // Weiterhin genau eine Row.
        $this->assertSame(1, CoreContextDateTime::query()
            ->where('context_id', $task->getKey())->count());
    }

    public function test_backfill_command_is_idempotent_and_rerunnable(): void
    {
        // Drei Tasks anlegen und deren Observer-Mirrors hart entfernen, um den
        // "noch nicht gebackfillten" Ausgangszustand zu simulieren.
        $tasks = collect(range(1, 3))->map(fn () => $this->makeTask());
        CoreContextDateTime::query()->forceDelete();
        $this->assertSame(0, CoreContextDateTime::count());

        // Dry-Run schreibt nichts.
        $this->artisan('core:context-date-times:backfill', ['--model' => 'SyncTestTask', '--dry-run' => true])
            ->assertSuccessful();
        $this->assertSame(0, CoreContextDateTime::count());

        // Echter Lauf: drei Rows.
        $this->artisan('core:context-date-times:backfill', ['--model' => 'SyncTestTask'])
            ->assertSuccessful();
        $this->assertSame(3, CoreContextDateTime::count());

        // Re-Run: keine Duplikate.
        $this->artisan('core:context-date-times:backfill', ['--model' => 'SyncTestTask'])
            ->assertSuccessful();
        $this->assertSame(3, CoreContextDateTime::count());
    }

    public function test_backfill_rejects_unknown_model(): void
    {
        $this->artisan('core:context-date-times:backfill', ['--model' => 'DoesNotExist'])
            ->assertFailed();
    }
}

/**
 * Fixture-Model für den Dual-Write-Test. Nutzt das Trait; das Mapping kommt aus
 * der in getEnvironmentSetUp() gesetzten Whitelist.
 */
class SyncTestTask extends Model
{
    use HasContextDateTimes;
    use SoftDeletes;

    protected $table = 'sync_test_tasks';

    protected $fillable = ['team_id', 'title', 'due_date'];

    protected $casts = [
        'due_date' => 'datetime',
    ];
}
