<?php

namespace Database\Factories;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerInvitation>
 */
class CustomerInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barbershop_id' => Barbershop::factory(),
            'customer_id' => Customer::factory(),
            'issued_by' => User::factory(),
            'accepted_by' => null,
            'email' => fake()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHours(48),
            'consumed_at' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'barbershop_id' => $customer->barbershop_id,
            'customer_id' => $customer->id,
        ]);
    }
}
