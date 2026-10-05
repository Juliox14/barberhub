<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDashboardFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_dashboard_redirects_to_platform_dashboard(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->get('/dashboard')
            ->assertRedirect(route('platform.dashboard', absolute: false));
    }

    public function test_one_active_membership_dashboard_redirects_to_tenant_dashboard(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($barbershop)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('tenant.dashboard', $barbershop, false));
    }

    public function test_multiple_active_memberships_dashboard_redirects_to_selector(): void
    {
        $user = User::factory()->create();

        Membership::factory()->for($user)->for(Barbershop::factory()->create())->create();
        Membership::factory()->for($user)->for(Barbershop::factory()->create())->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('tenant.selector', absolute: false));
    }

    public function test_inactive_memberships_do_not_count_for_dashboard_destination(): void
    {
        $user = User::factory()->create();
        $inactiveBarbershop = Barbershop::factory()->create();
        $activeBarbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($inactiveBarbershop)->create([
            'status' => Membership::STATUS_INACTIVE,
        ]);
        Membership::factory()->for($user)->for($activeBarbershop)->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('tenant.dashboard', $activeBarbershop, false));
    }

    public function test_dashboard_is_forbidden_without_active_membership_or_platform_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_tenant_dashboard_denies_user_with_no_or_other_barbershop_membership(): void
    {
        $user = User::factory()->create();
        $allowedBarbershop = Barbershop::factory()->create();
        $blockedBarbershop = Barbershop::factory()->create();

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $blockedBarbershop, false))
            ->assertForbidden();

        Membership::factory()->for($user)->for($allowedBarbershop)->create();

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $blockedBarbershop, false))
            ->assertForbidden();
    }

    public function test_platform_admin_can_access_tenant_dashboard(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create();

        $this->actingAs($platformAdmin)
            ->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee('Administrador de plataforma');
    }

    public function test_tenant_dashboard_shows_role_specific_copy(): void
    {
        $barbershop = Barbershop::factory()->create();

        foreach ([
            Membership::ROLE_OWNER => 'Gestiona la operación completa',
            Membership::ROLE_ADMIN => 'Coordina agenda',
            Membership::ROLE_BARBER => 'Consulta tus citas',
        ] as $role => $copy) {
            $user = User::factory()->create();
            Membership::factory()->for($user)->for($barbershop)->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('tenant.dashboard', $barbershop, false))
                ->assertOk()
                ->assertSee($copy);
        }
    }

    public function test_login_uses_tenant_aware_redirect_and_ignores_cross_tenant_intended_dashboard(): void
    {
        $user = User::factory()->create();
        $allowedBarbershop = Barbershop::factory()->create();
        $blockedBarbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($allowedBarbershop)->create();

        $this->get(route('tenant.dashboard', $blockedBarbershop, false))
            ->assertRedirect(route('login', absolute: false));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('tenant.dashboard', $allowedBarbershop, false));
    }

    public function test_login_rejects_external_intended_dashboard_urls(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();

        Membership::factory()->for($user)->for($barbershop)->create();

        $this->withSession([
            'url.intended' => 'https://evil.example/barbershops/'.$barbershop->slug.'/dashboard',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('tenant.dashboard', $barbershop, false));
    }

    public function test_navigation_shows_tenant_aware_link_for_single_membership_and_platform_admin(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create();

        $tenantResponse = $this->actingAs($user)->get(route('tenant.dashboard', $barbershop, false));

        $tenantResponse->assertOk()
            ->assertSee(route('tenant.dashboard', $barbershop), false)
            ->assertSee('Panel de barbería');

        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $platformResponse = $this->actingAs($platformAdmin)->get(route('platform.dashboard', absolute: false));

        $platformResponse->assertOk()
            ->assertSee(route('platform.dashboard'), false)
            ->assertSee('Dashboard global');
    }
}
