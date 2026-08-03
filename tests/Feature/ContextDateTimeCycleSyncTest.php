<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Enums\ContextDateTimeKind;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Tests\TestCase;
use Platform\Core\Traits\HasContextDateTimes;

/**
 * Deckt den Dual-Write für OKR-Zyklen ab. Besonderheit: okr_cycles.starts_at /
 * ends_at sind KEINE echten Spalten, sondern Accessor-Attribute, die an das
 * verknüpfte CycleTemplate delegieren. Der Test bildet das über Stub-Models nach
 * und stellt sicher, dass der Synchronizer den Accessor-Pfad korrekt spiegelt.
 */
class ContextDateTimeCycleSyncTest extends TestCase
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
            CycleStub::class => [
                'starts_at' => ['kind' => ContextDateTimeKind::Start, 'label' => 'Zyklusstart'],
                'ends_at' => ['kind' => ContextDateTimeKind::End, 'label' => 'Zyklusende'],
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

        if (! Schema::hasTable('okr_test_templates')) {
            Schema::create('okr_test_templates', function ($table) {
                $table->id();
                $table->date('starts_at');
                $table->date('ends_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('okr_test_cycles')) {
            Schema::create('okr_test_cycles', function ($table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->unsignedBigInteger('cycle_template_id')->nullable();
                $table->string('status')->default('draft');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function template(string $start, string $end): TemplateStub
    {
        return TemplateStub::create(['starts_at' => $start, 'ends_at' => $end]);
    }

    private function cycle(?TemplateStub $template): CycleStub
    {
        return CycleStub::create([
            'team_id' => 5,
            'cycle_template_id' => $template?->id,
        ]);
    }

    private function mirror(CycleStub $cycle, string $column): ?CoreContextDateTime
    {
        return CoreContextDateTime::withTrashed()
            ->where('context_type', $cycle->getMorphClass())
            ->where('context_id', $cycle->getKey())
            ->where('source', "migrated_from:okr_test_cycles.{$column}")
            ->first();
    }

    public function test_cycle_dual_writes_start_and_end_bands(): void
    {
        $cycle = $this->cycle($this->template('2026-01-01', '2026-03-31'));

        $start = $this->mirror($cycle, 'starts_at');
        $end = $this->mirror($cycle, 'ends_at');

        $this->assertNotNull($start);
        $this->assertNotNull($end);

        $this->assertSame(ContextDateTimeKind::Start, $start->kind);
        $this->assertSame('Zyklusstart', $start->label);
        $this->assertSame(5, $start->team_id);
        $this->assertTrue($start->starts_at->equalTo(Carbon::parse('2026-01-01 00:00:00')));

        $this->assertSame(ContextDateTimeKind::End, $end->kind);
        $this->assertSame('Zyklusende', $end->label);
        $this->assertTrue($end->starts_at->equalTo(Carbon::parse('2026-03-31 00:00:00')));

        // Je ein Occurrence-Schatten pro Band.
        $this->assertCount(1, $start->occurrences);
        $this->assertCount(1, $end->occurrences);
    }

    public function test_cycle_without_template_writes_no_mirror(): void
    {
        $cycle = $this->cycle(null);

        $this->assertNull($this->mirror($cycle, 'starts_at'));
        $this->assertNull($this->mirror($cycle, 'ends_at'));
        $this->assertSame(0, CoreContextDateTime::count());
    }

    public function test_relinking_template_updates_both_bands(): void
    {
        $cycle = $this->cycle($this->template('2026-01-01', '2026-03-31'));
        $other = $this->template('2026-04-01', '2026-06-30');

        // Frisch laden: kein gecachtes template-Relation → der Accessor löst beim
        // Save mit dem NEUEN cycle_template_id auf (so verhält sich auch ein Request).
        CycleStub::findOrFail($cycle->id)->update(['cycle_template_id' => $other->id]);

        $start = $this->mirror($cycle, 'starts_at');
        $end = $this->mirror($cycle, 'ends_at');

        $this->assertTrue($start->starts_at->equalTo(Carbon::parse('2026-04-01 00:00:00')));
        $this->assertTrue($end->starts_at->equalTo(Carbon::parse('2026-06-30 00:00:00')));

        // Keine Duplikate – weiterhin genau zwei Rows.
        $this->assertSame(2, CoreContextDateTime::query()
            ->where('context_id', $cycle->getKey())->count());
    }

    public function test_deleting_cycle_soft_deletes_bands(): void
    {
        $cycle = $this->cycle($this->template('2026-01-01', '2026-03-31'));

        $cycle->delete();

        $this->assertNotNull($this->mirror($cycle, 'starts_at')->deleted_at);
        $this->assertNotNull($this->mirror($cycle, 'ends_at')->deleted_at);
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $tpl = $this->template('2026-01-01', '2026-03-31');
        collect(range(1, 3))->each(fn () => $this->cycle($tpl));

        // Observer-Mirrors entfernen → "noch nicht gebackfillt".
        CoreContextDateTime::query()->forceDelete();
        $this->assertSame(0, CoreContextDateTime::count());

        $this->artisan('core:context-date-times:backfill', ['--model' => 'CycleStub', '--dry-run' => true])
            ->assertSuccessful();
        $this->assertSame(0, CoreContextDateTime::count());

        $this->artisan('core:context-date-times:backfill', ['--model' => 'CycleStub'])
            ->assertSuccessful();
        $this->assertSame(6, CoreContextDateTime::count()); // 3 Zyklen × 2 Bänder

        $this->artisan('core:context-date-times:backfill', ['--model' => 'CycleStub'])
            ->assertSuccessful();
        $this->assertSame(6, CoreContextDateTime::count());
    }
}

/**
 * Stub für okr_cycle_templates: hält die echten Datums-Spalten.
 */
class TemplateStub extends Model
{
    protected $table = 'okr_test_templates';

    protected $fillable = ['starts_at', 'ends_at'];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];
}

/**
 * Stub für okr_cycles: starts_at/ends_at sind Accessoren auf das Template –
 * exakt wie beim echten Platform\Okr\Models\Cycle.
 */
class CycleStub extends Model
{
    use HasContextDateTimes;
    use SoftDeletes;

    protected $table = 'okr_test_cycles';

    protected $fillable = ['team_id', 'cycle_template_id', 'status'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateStub::class, 'cycle_template_id');
    }

    public function getStartsAtAttribute()
    {
        return $this->template?->starts_at;
    }

    public function getEndsAtAttribute()
    {
        return $this->template?->ends_at;
    }
}
