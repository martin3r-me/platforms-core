<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Platform\Core\Jobs\ExpandContextDateTimeOccurrences;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Services\RecurrenceExpander;
use Platform\Core\Tests\TestCase;

class ExpandContextDateTimeOccurrencesJobTest extends TestCase
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
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_job_is_dispatched_when_recurring_date_time_is_created(): void
    {
        Queue::fake();

        $dt = CoreContextDateTime::factory()->recurring()->create();

        Queue::assertPushed(
            ExpandContextDateTimeOccurrences::class,
            fn (ExpandContextDateTimeOccurrences $job) => $job->coreContextDateTimeId === $dt->id
        );
    }

    public function test_job_is_not_dispatched_for_non_recurring_date_time(): void
    {
        Queue::fake();

        CoreContextDateTime::factory()->create(['recurrence_rrule' => null]);

        Queue::assertNotPushed(ExpandContextDateTimeOccurrences::class);
    }

    public function test_job_is_dispatched_again_when_rrule_changes(): void
    {
        $dt = CoreContextDateTime::factory()->recurring('FREQ=DAILY')->create();

        Queue::fake();

        $dt->update(['recurrence_rrule' => 'FREQ=WEEKLY;BYDAY=TU']);

        Queue::assertPushed(ExpandContextDateTimeOccurrences::class, 1);
    }

    public function test_job_is_not_dispatched_when_unrelated_field_changes(): void
    {
        $dt = CoreContextDateTime::factory()->recurring()->create();

        Queue::fake();

        $dt->update(['label' => 'Neuer Titel']);

        Queue::assertNotPushed(ExpandContextDateTimeOccurrences::class);
    }

    public function test_handle_expands_occurrences(): void
    {
        // starts_at bewusst in der (nahen) Zukunft: die COUNT-limitierten
        // Occurrences müssen innerhalb des rollenden 90-Tage-Fensters ab "jetzt" liegen.
        $dt = CoreContextDateTime::factory()->recurring('FREQ=DAILY;COUNT=3')->create([
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => null,
        ]);
        $dt->occurrences()->delete();

        (new ExpandContextDateTimeOccurrences($dt->id))->handle(new RecurrenceExpander);

        $this->assertSame(3, $dt->occurrences()->count());
    }

    public function test_handle_is_a_noop_when_date_time_no_longer_exists(): void
    {
        $job = new ExpandContextDateTimeOccurrences(999999);

        $job->handle(new RecurrenceExpander);

        $this->addToAssertionCount(1); // kein Fehler = bestanden
    }
}
