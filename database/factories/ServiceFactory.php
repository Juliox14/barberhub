<?php

namespace Database\Factories;

use App\Models\Barbershop;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barbershop_id' => Barbershop::factory(),
            'name' => fake()->unique()->randomElement([
                'Corte clásico',
                'Arreglo de barba',
                'Corte infantil',
                'Afeitado tradicional',
                'Corte y barba',
            ]).' '.fake()->unique()->numberBetween(1, 999),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 0, 250),
            'duration_minutes' => fake()->numberBetween(15, 120),
            'active' => true,
        ];
    }
}
