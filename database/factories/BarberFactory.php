<?php

namespace Database\Factories;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barber>
 */
class BarberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barbershop_id' => Barbershop::factory(),
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'active' => true,
        ];
    }
}
