<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Tests\TestCase;

class RefreshExpansionHorizonCommandTest extends TestCase
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

    public function test_refreshes_occurrences_for_all_recurring_date_times(): void
    {
        $recurring = CoreContextDateTime::factory()->recurring('FREQ=DAILY;COUNT=5')->create([
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => null,
        ]);
        $recurring->occurrences()->delete();

        $nonRecurring = CoreContextDateTime::factory()->create(['recurrence_rrule' => null]);

        $this->artisan('core:context-date-times:refresh-expansion-horizon')
            ->assertExitCode(0);

        $this->assertSame(5, $recurring->occurrences()->count());
        $this->assertSame(0, $nonRecurring->occurrences()->count());
    }

    public function test_recomputes_and_drops_stale_occurrences(): void
    {
        $recurring = CoreContextDateTime::factory()->recurring('FREQ=DAILY;COUNT=3')->create([
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => null,
        ]);

        // Simuliert eine veraltete Occurrence weit außerhalb des Fensters.
        $recurring->occurrences()->create([
            'starts_at' => Carbon::now()->subYears(2),
            'ends_at' => null,
            'is_exception' => false,
        ]);

        $this->artisan('core:context-date-times:refresh-expansion-horizon')
            ->assertExitCode(0);

        $this->assertSame(0, $recurring->occurrences()->where('starts_at', '<', Carbon::now()->subYear())->count());
        $this->assertSame(3, $recurring->occurrences()->count());
    }
}
