<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPreference>
 */
class CustomerPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'preferences' => [
                'notes' => fake()->sentence(),
            ],
        ];
    }
}
