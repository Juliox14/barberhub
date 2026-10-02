<?php

namespace Database\Factories;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'barbershop_id' => Barbershop::factory(),
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ];
    }
}
