<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Services\RecurrenceExpander;
use Platform\Core\Tests\TestCase;

class RecurrenceExpanderTest extends TestCase
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

    protected function makeDateTime(string $rrule, Carbon $startsAt, ?Carbon $endsAt = null): CoreContextDateTime
    {
        return CoreContextDateTime::factory()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Europe/Berlin',
            'recurrence_rrule' => $rrule,
        ]);
    }

    public function test_weekly_by_day_expands_within_horizon(): void
    {
        $start = Carbon::create(2026, 1, 5, 8, 0, 0, 'UTC'); // Montag, 09:00 Europe/Berlin (CET)
        $dt = $this->makeDateTime('FREQ=WEEKLY;BYDAY=MO', $start);

        $from = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC');
        $to = Carbon::create(2026, 4, 1, 0, 0, 0, 'UTC');

        (new RecurrenceExpander)->expand($dt, $from, $to);

        $occurrences = $dt->occurrences()->orderBy('starts_at')->get();

        $this->assertCount(13, $occurrences);
        foreach ($occurrences as $occurrence) {
            $local = $occurrence->starts_at->copy()->timezone('Europe/Berlin');
            $this->assertSame('Mon', $local->format('D'));
            $this->assertSame('09:00:00', $local->format('H:i:s'));
        }
    }

    public function test_monthly_by_month_day_expands(): void
    {
        $start = Carbon::create(2026, 1, 15, 8, 0, 0, 'UTC'); // 15. Januar, 09:00 Berlin
        $dt = $this->makeDateTime('FREQ=MONTHLY;BYMONTHDAY=15', $start);

        $from = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC');
        $to = Carbon::create(2026, 4, 1, 0, 0, 0, 'UTC');

        (new RecurrenceExpander)->expand($dt, $from, $to);

        $occurrences = $dt->occurrences()->orderBy('starts_at')->get();

        $this->assertCount(3, $occurrences); // Jan, Feb, März
        foreach ($occurrences as $occurrence) {
            $this->assertSame('15', $occurrence->starts_at->copy()->timezone('Europe/Berlin')->format('j'));
        }
    }

    public function test_daily_count_limits_occurrences(): void
    {
        $start = Carbon::create(2026, 1, 1, 8, 0, 0, 'UTC');
        $dt = $this->makeDateTime('FREQ=DAILY;COUNT=10', $start);

        (new RecurrenceExpander)->expand(
            $dt,
            Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC'),
            Carbon::create(2026, 12, 31, 0, 0, 0, 'UTC')
        );

        $this->assertSame(10, $dt->occurrences()->count());
    }

    public function test_dst_transition_march_keeps_local_wall_clock_time(): void
    {
        $start = Carbon::create(2026, 1, 5, 8, 0, 0, 'UTC'); // Montag, 09:00 Berlin (CET)
        $dt = $this->makeDateTime('FREQ=WEEKLY;BYDAY=MO', $start);

        (new RecurrenceExpander)->expand(
            $dt,
            Carbon::create(2026, 3, 1, 0, 0, 0, 'UTC'),
            Carbon::create(2026, 4, 15, 0, 0, 0, 'UTC')
        );

        // DST-Wechsel CET->CEST: Sonntag, 29.03.2026, 02:00 Uhr.
        $before = $dt->occurrences()->whereDate('starts_at', '2026-03-23')->first();
        $after = $dt->occurrences()->whereDate('starts_at', '2026-03-30')->first();

        $this->assertNotNull($before);
        $this->assertNotNull($after);

        $this->assertSame('2026-03-23 08:00:00', $before->starts_at->format('Y-m-d H:i:s')); // UTC, CET (+1)
        $this->assertSame('2026-03-30 07:00:00', $after->starts_at->format('Y-m-d H:i:s')); // UTC, CEST (+2)

        $this->assertSame('09:00:00', $before->starts_at->copy()->timezone('Europe/Berlin')->format('H:i:s'));
        $this->assertSame('09:00:00', $after->starts_at->copy()->timezone('Europe/Berlin')->format('H:i:s'));
    }

    public function test_dst_transition_october_keeps_local_wall_clock_time(): void
    {
        $start = Carbon::create(2026, 1, 5, 8, 0, 0, 'UTC');
        $dt = $this->makeDateTime('FREQ=WEEKLY;BYDAY=MO', $start);

        (new RecurrenceExpander)->expand(
            $dt,
            Carbon::create(2026, 10, 1, 0, 0, 0, 'UTC'),
            Carbon::create(2026, 11, 15, 0, 0, 0, 'UTC')
        );

        // DST-Wechsel CEST->CET: Sonntag, 25.10.2026, 03:00 Uhr.
        $before = $dt->occurrences()->whereDate('starts_at', '2026-10-19')->first();
        $after = $dt->occurrences()->whereDate('starts_at', '2026-10-26')->first();

        $this->assertNotNull($before);
        $this->assertNotNull($after);

        $this->assertSame('2026-10-19 07:00:00', $before->starts_at->format('Y-m-d H:i:s')); // UTC, CEST (+2)
        $this->assertSame('2026-10-26 08:00:00', $after->starts_at->format('Y-m-d H:i:s')); // UTC, CET (+1)

        $this->assertSame('09:00:00', $before->starts_at->copy()->timezone('Europe/Berlin')->format('H:i:s'));
        $this->assertSame('09:00:00', $after->starts_at->copy()->timezone('Europe/Berlin')->format('H:i:s'));
    }

    public function test_expand_is_recomputable_and_preserves_manual_exceptions(): void
    {
        $start = Carbon::create(2026, 1, 5, 8, 0, 0, 'UTC');
        $dt = $this->makeDateTime('FREQ=DAILY;COUNT=5', $start);

        $expander = new RecurrenceExpander;
        $from = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC');
        $to = Carbon::create(2026, 4, 1, 0, 0, 0, 'UTC');

        $expander->expand($dt, $from, $to);
        $this->assertSame(5, $dt->occurrences()->count());

        $dt->occurrences()->create([
            'starts_at' => Carbon::create(2026, 6, 1, 8, 0, 0, 'UTC'),
            'ends_at' => null,
            'is_exception' => true,
        ]);

        $expander->expand($dt, $from, $to);

        $this->assertSame(5, $dt->occurrences()->where('is_exception', false)->count());
        $this->assertSame(1, $dt->occurrences()->where('is_exception', true)->count());
    }

    public function test_non_recurring_date_time_is_skipped(): void
    {
        $dt = CoreContextDateTime::factory()->create(['recurrence_rrule' => null]);

        $count = (new RecurrenceExpander)->expand($dt);

        $this->assertSame(0, $count);
        $this->assertSame(0, $dt->occurrences()->count());
    }
}
