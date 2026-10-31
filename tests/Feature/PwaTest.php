<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function activeTenantRoles(): array
    {
        return [
            'owner' => [Membership::ROLE_OWNER],
            'barber' => [Membership::ROLE_BARBER],
        ];
    }

    #[DataProvider('activeTenantRoles')]
    public function test_account_security_page_renders_for_active_tenant_roles(string $role): void
    {
        config(['webpush.public_key' => 'test-vapid-public-key']);
        $barbershop = Barbershop::factory()->create();
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create([
            'role' => $role,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->get(route('two-factor.settings'));

        $response->assertOk()
            ->assertSee('Autenticación en dos pasos')
            ->assertSee('data-subscribe-url="'.route('tenant.push-subscriptions.store', $barbershop).'"', false);
    }

    public function test_tenant_dashboard_retains_its_push_subscription_url(): void
    {
        config(['webpush.public_key' => 'test-vapid-public-key']);
        $barbershop = Barbershop::factory()->create();
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $response = $this->actingAs($user)->get(route('tenant.dashboard', $barbershop, false));

        $response->assertOk()
            ->assertSee('data-subscribe-url="'.route('tenant.push-subscriptions.store', $barbershop).'"', false);
    }
}
