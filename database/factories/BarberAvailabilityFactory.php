<?php

namespace Database\Factories;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarberAvailability>
 */
class BarberAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barber_id' => Barber::factory(),
            'weekday' => fake()->numberBetween(BusinessHour::WEEKDAY_SUNDAY, BusinessHour::WEEKDAY_SATURDAY),
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'active' => false,
        ]);
    }
}
