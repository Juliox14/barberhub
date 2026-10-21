<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.fake()->uuid();

        return [
            'user_id' => User::factory(),
            'endpoint' => $endpoint,
            'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => rtrim(strtr(base64_encode("\x04".str_repeat('x', 64)), '+/', '-_'), '='),
            'auth' => rtrim(strtr(base64_encode(str_repeat('a', 16)), '+/', '-_'), '='),
            'session_hash' => fake()->sha256(),
        ];
    }
}
