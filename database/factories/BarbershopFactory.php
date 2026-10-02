<?php

namespace Database\Factories;

use App\Models\Barbershop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Barbershop>
 */
class BarbershopFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Barbershop';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'status' => Barbershop::STATUS_ACTIVE,
            'timezone' => 'UTC',
        ];
    }
}
