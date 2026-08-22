<?php

namespace Platform\Core\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Models\CoreContextDateTimeOccurrence;
use Platform\Core\Tests\TestCase;
use Platform\Core\Traits\HasContextDateTimes;

/**
 * Deckt den Cascade-Delete ab: löscht man den Host-Record eines Attachable-Models,
 * müssen dessen CoreContextDateTime-Einträge (egal ob Dual-Write-Mirror oder
 * manuell angehängt) samt Occurrences mitgelöscht werden – keine verwaisten
 * polymorphen Referenzen (Lesson Learned aus Issue #147).
 */
class ContextDateTimeCascadeDeleteTest extends TestCase
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
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('cascade_test_events')) {
            Schema::create('cascade_test_events', function ($table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->string('title');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function makeEvent(): CascadeTestEvent
    {
        return CascadeTestEvent::create(['team_id' => 7, 'title' => 'Demo']);
    }

    public function test_soft_deleting_host_soft_deletes_attached_date_times_and_occurrences(): void
    {
        $event = $this->makeEvent();

        $dateTime = CoreContextDateTime::factory()->for($event, 'context')->forTeam(7)->create();
        $occurrence = CoreContextDateTimeOccurrence::factory()->for($dateTime, 'dateTime')->create();

        $event->delete();

        $this->assertNotNull($dateTime->fresh()->deleted_at);
        $this->assertNotNull(CoreContextDateTime::withTrashed()->find($dateTime->id));
        // Occurrences selbst haben keine SoftDeletes, bleiben also unangetastet.
        $this->assertNotNull(CoreContextDateTimeOccurrence::find($occurrence->id));
    }

    public function test_force_deleting_host_force_deletes_date_times_and_occurrences(): void
    {
        $event = $this->makeEvent();

        $dateTime = CoreContextDateTime::factory()->for($event, 'context')->forTeam(7)->create();
        $occurrence = CoreContextDateTimeOccurrence::factory()->for($dateTime, 'dateTime')->create();

        $event->forceDelete();

        $this->assertNull(CoreContextDateTime::withTrashed()->find($dateTime->id));
        $this->assertNull(CoreContextDateTimeOccurrence::find($occurrence->id));
    }

    public function test_deleting_host_leaves_other_contexts_untouched(): void
    {
        $event = $this->makeEvent();
        $otherEvent = $this->makeEvent();

        $dateTime = CoreContextDateTime::factory()->for($event, 'context')->forTeam(7)->create();
        $otherDateTime = CoreContextDateTime::factory()->for($otherEvent, 'context')->forTeam(7)->create();

        $event->delete();

        $this->assertNotNull($dateTime->fresh()->deleted_at);
        $this->assertNull($otherDateTime->fresh()->deleted_at);
    }
}

class CascadeTestEvent extends Model
{
    use HasContextDateTimes;
    use SoftDeletes;

    protected $table = 'cascade_test_events';

    protected $fillable = ['team_id', 'title'];
}
