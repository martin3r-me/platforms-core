<?php

namespace Platform\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Models\CoreContextDateTimeOccurrence;

/**
 * @extends Factory<CoreContextDateTimeOccurrence>
 */
class CoreContextDateTimeOccurrenceFactory extends Factory
{
    protected $model = CoreContextDateTimeOccurrence::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-1 week', '+1 month');
        $endsAt = (clone $startsAt)->modify('+1 hour');

        return [
            'core_context_date_time_id' => CoreContextDateTime::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_exception' => false,
        ];
    }

    public function exception(): static
    {
        return $this->state(fn () => [
            'is_exception' => true,
        ]);
    }
}
