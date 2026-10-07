<?php

namespace Database\Factories;

use App\Models\Barbershop;
use App\Models\BusinessHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barbershop_id' => Barbershop::factory(),
            'weekday' => fake()->numberBetween(BusinessHour::WEEKDAY_SUNDAY, BusinessHour::WEEKDAY_SATURDAY),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'opens_at' => null,
            'closes_at' => null,
            'closed' => true,
        ]);
    }
}
