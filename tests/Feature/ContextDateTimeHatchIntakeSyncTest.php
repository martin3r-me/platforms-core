<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Enums\ContextDateTimeKind;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Tests\TestCase;
use Platform\Core\Traits\HasContextDateTimes;

/**
 * Deckt den Dual-Write für Hatch-Intakes ab (hatch_project_intakes.started_at/
 * completed_at, analog Platform\Hatch\Models\HatchProjectIntake). Das
 * Hatch-Package ist hier nicht installiert, daher bildet ein Stub-Model die
 * echten Spalten/Semantik nach (started_at → start, completed_at → end); die
 * Produktivwhitelist in config/core.php zeigt auf die echte Hatch-Klasse.
 */
class ContextDateTimeHatchIntakeSyncTest extends TestCase
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
            HatchIntakeStub::class => [
                'started_at' => ['kind' => ContextDateTimeKind::Start, 'label' => 'Start'],
                'completed_at' => ['kind' => ContextDateTimeKind::End, 'label' => 'Abschluss'],
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

        if (! Schema::hasTable('hatch_intake_test_stubs')) {
            Schema::create('hatch_intake_test_stubs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->string('name');
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function makeIntake(array $overrides = []): HatchIntakeStub
    {
        return HatchIntakeStub::create(array_merge([
            'team_id' => 3,
            'name' => 'Onboarding Q3',
        ], $overrides));
    }

    private function mirror(HatchIntakeStub $intake, string $column): ?CoreContextDateTime
    {
        return CoreContextDateTime::withTrashed()
            ->where('context_type', $intake->getMorphClass())
            ->where('context_id', $intake->getKey())
            ->where('source', "migrated_from:hatch_intake_test_stubs.{$column}")
            ->first();
    }

    public function test_publishing_dual_writes_start_mirror(): void
    {
        $intake = $this->makeIntake(['started_at' => '2026-09-01 09:00:00']);

        $mirror = $this->mirror($intake, 'started_at');

        $this->assertNotNull($mirror);
        $this->assertSame(ContextDateTimeKind::Start, $mirror->kind);
        $this->assertSame('Start', $mirror->label);
        $this->assertSame(3, $mirror->team_id);
        $this->assertTrue($mirror->starts_at->equalTo(Carbon::parse('2026-09-01 09:00:00')));
        $this->assertNull($this->mirror($intake, 'completed_at'));
    }

    public function test_closing_dual_writes_end_mirror(): void
    {
        $intake = $this->makeIntake(['started_at' => '2026-09-01 09:00:00']);

        $intake->update(['completed_at' => '2026-09-30 17:00:00']);

        $mirror = $this->mirror($intake, 'completed_at');

        $this->assertNotNull($mirror);
        $this->assertSame(ContextDateTimeKind::End, $mirror->kind);
        $this->assertTrue($mirror->starts_at->equalTo(Carbon::parse('2026-09-30 17:00:00')));

        // Keine Duplikate – je Spalte genau eine Row.
        $this->assertSame(1, CoreContextDateTime::query()
            ->where('context_id', $intake->getKey())
            ->where('source', 'migrated_from:hatch_intake_test_stubs.completed_at')
            ->count());
    }

    public function test_unpublishing_clears_start_mirror(): void
    {
        $intake = $this->makeIntake(['started_at' => '2026-09-01 09:00:00']);
        $this->assertNull($this->mirror($intake, 'started_at')->deleted_at);

        $intake->update(['started_at' => null]);

        $mirror = $this->mirror($intake, 'started_at');
        $this->assertNotNull($mirror->deleted_at);
        $this->assertNull(CoreContextDateTime::find($mirror->id));
    }

    public function test_deleting_intake_soft_deletes_both_mirrors(): void
    {
        $intake = $this->makeIntake([
            'started_at' => '2026-09-01 09:00:00',
            'completed_at' => '2026-09-30 17:00:00',
        ]);

        $intake->delete();

        $this->assertNotNull($this->mirror($intake, 'started_at')->deleted_at);
        $this->assertNotNull($this->mirror($intake, 'completed_at')->deleted_at);
    }

    public function test_backfill_command_is_idempotent_and_rerunnable(): void
    {
        $intakes = collect(range(1, 3))->map(fn () => $this->makeIntake([
            'started_at' => '2026-09-01 09:00:00',
            'completed_at' => '2026-09-30 17:00:00',
        ]));

        // Observer-Mirrors entfernen → "noch nicht gebackfillt".
        CoreContextDateTime::query()->forceDelete();
        $this->assertSame(0, CoreContextDateTime::count());

        $this->artisan('core:context-date-times:backfill', ['--model' => 'HatchIntakeStub', '--dry-run' => true])
            ->assertSuccessful();
        $this->assertSame(0, CoreContextDateTime::count());

        $this->artisan('core:context-date-times:backfill', ['--model' => 'HatchIntakeStub'])
            ->assertSuccessful();
        $this->assertSame(6, CoreContextDateTime::count()); // 3 Intakes × 2 Zeitpunkte

        $this->artisan('core:context-date-times:backfill', ['--model' => 'HatchIntakeStub'])
            ->assertSuccessful();
        $this->assertSame(6, CoreContextDateTime::count());
    }
}

/**
 * Stub für hatch_project_intakes: bildet started_at/completed_at nach, ohne
 * das Hatch-Package als Abhängigkeit zu brauchen.
 */
class HatchIntakeStub extends Model
{
    use HasContextDateTimes;
    use SoftDeletes;

    protected $table = 'hatch_intake_test_stubs';

    protected $fillable = ['team_id', 'name', 'started_at', 'completed_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
