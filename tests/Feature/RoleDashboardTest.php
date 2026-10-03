<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_role_dashboards_are_no_longer_the_authenticated_landing_flow(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $barbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($barbershop)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('tenant.dashboard', $barbershop, false));
    }

    public function test_legacy_role_only_user_without_membership_is_forbidden_from_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }
}
