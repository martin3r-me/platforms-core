<?php

namespace Platform\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Platform\Core\Enums\ContextDateTimeKind;
use Platform\Core\Models\CoreContextDateTime;

/**
 * @extends Factory<CoreContextDateTime>
 */
class CoreContextDateTimeFactory extends Factory
{
    protected $model = CoreContextDateTime::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-1 week', '+1 month');
        $endsAt = (clone $startsAt)->modify('+1 hour');

        return [
            'context_type' => 'test_context',
            'context_id' => $this->faker->numberBetween(1, 1000),
            'kind' => $this->faker->randomElement(ContextDateTimeKind::values()),
            'label' => $this->faker->optional()->sentence(3),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Europe/Berlin',
            'recurrence_rrule' => null,
            'is_all_day' => false,
            'source' => 'factory',
            'calendar_sync_enabled' => false,
            'icalendar_uid' => null,
            'last_synced_at' => null,
            'team_id' => 1,
        ];
    }

    /**
     * Ganztägiger Zeitpunkt ohne Endzeit.
     */
    public function allDay(): static
    {
        return $this->state(fn () => [
            'is_all_day' => true,
            'ends_at' => null,
        ]);
    }

    /**
     * Wiederkehrender Zeitpunkt mit RRULE.
     */
    public function recurring(string $rrule = 'FREQ=WEEKLY;BYDAY=MO'): static
    {
        return $this->state(fn () => [
            'recurrence_rrule' => $rrule,
        ]);
    }

    public function ofKind(ContextDateTimeKind $kind): static
    {
        return $this->state(fn () => [
            'kind' => $kind->value,
        ]);
    }

    public function forContext(string $type, int $id): static
    {
        return $this->state(fn () => [
            'context_type' => $type,
            'context_id' => $id,
        ]);
    }

    public function forTeam(int $teamId): static
    {
        return $this->state(fn () => [
            'team_id' => $teamId,
        ]);
    }
}
