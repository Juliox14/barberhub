<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_default_to_client_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame('client', $user->refresh()->role);
    }

    public function test_user_role_can_be_persisted(): void
    {
        $user = User::factory()->create([
            'role' => 'barber',
        ]);

        $this->assertSame('barber', $user->role);
    }
}
