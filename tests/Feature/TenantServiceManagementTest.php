<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_view_services(): void
    {
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Centro']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        Service::factory()->for($barbershop)->create(['name' => 'Corte clásico']);

        $this->actingAs($owner)
            ->get(route('tenant.services.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Servicios de Barbería Centro')
            ->assertSee('Corte clásico');

        $this->actingAs($admin)
            ->get(route('tenant.services.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Servicios de Barbería Centro')
            ->assertSee('Corte clásico');
    }

    public function test_barber_cannot_manage_services(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($barber)
            ->get(route('tenant.services.index', $barbershop, false))
            ->assertForbidden();

        $this->actingAs($barber)
            ->post(route('tenant.services.store', $barbershop, false), [
                'name' => 'Corte express',
                'description' => 'Servicio rápido',
                'price' => '15.00',
                'duration_minutes' => 30,
            ])->assertForbidden();

        $this->assertDatabaseMissing('services', [
            'barbershop_id' => $barbershop->id,
            'name' => 'Corte express',
        ]);
    }

    public function test_platform_admin_can_manage_services_for_support(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Soporte']);

        $this->actingAs($platformAdmin)
            ->get(route('tenant.services.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Servicios de Barbería Soporte');

        $this->actingAs($platformAdmin)
            ->post(route('tenant.services.store', $barbershop, false), [
                'name' => 'Arreglo de barba',
                'description' => 'Perfilado y acabado',
                'price' => '12.50',
                'duration_minutes' => 25,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false));

        $this->assertDatabaseHas('services', [
            'barbershop_id' => $barbershop->id,
            'name' => 'Arreglo de barba',
            'price' => 12.50,
            'duration_minutes' => 25,
            'active' => true,
        ]);
    }

    public function test_owner_can_create_service_with_valid_payload(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->post(route('tenant.services.store', $barbershop, false), [
                'name' => 'Corte infantil',
                'description' => 'Corte para niños',
                'price' => '18.00',
                'duration_minutes' => 35,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false));

        $this->assertDatabaseHas('services', [
            'barbershop_id' => $barbershop->id,
            'name' => 'Corte infantil',
            'description' => 'Corte para niños',
            'price' => 18.00,
            'duration_minutes' => 35,
            'active' => true,
        ]);
    }

    public function test_store_prevents_duplicate_service_name_per_tenant(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Service::factory()->for($barbershop)->create(['name' => 'Corte clásico']);

        $this->actingAs($owner)
            ->from(route('tenant.services.index', $barbershop, false))
            ->post(route('tenant.services.store', $barbershop, false), [
                'name' => 'Corte clásico',
                'description' => 'Duplicado',
                'price' => '20.00',
                'duration_minutes' => 40,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('services', 1);
    }

    public function test_same_service_name_is_allowed_in_different_tenants(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Service::factory()->for($otherBarbershop)->create(['name' => 'Corte clásico']);

        $this->actingAs($owner)
            ->post(route('tenant.services.store', $barbershop, false), [
                'name' => 'Corte clásico',
                'description' => null,
                'price' => '20.00',
                'duration_minutes' => 40,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false));

        $this->assertDatabaseHas('services', [
            'barbershop_id' => $barbershop->id,
            'name' => 'Corte clásico',
        ]);
        $this->assertDatabaseHas('services', [
            'barbershop_id' => $otherBarbershop->id,
            'name' => 'Corte clásico',
        ]);
    }

    public function test_store_rejects_invalid_price_and_duration(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->from(route('tenant.services.index', $barbershop, false))
            ->post(route('tenant.services.store', $barbershop, false), [
                'name' => 'Servicio inválido',
                'price' => '-1',
                'duration_minutes' => 0,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false))
            ->assertSessionHasErrors(['price', 'duration_minutes']);

        $this->assertDatabaseMissing('services', [
            'barbershop_id' => $barbershop->id,
            'name' => 'Servicio inválido',
        ]);
    }

    public function test_update_rejects_service_from_another_tenant_with_404(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $otherService = Service::factory()->for($otherBarbershop)->create([
            'name' => 'Servicio ajeno',
            'active' => true,
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.services.update', [$barbershop, $otherService], false), [
                'name' => 'No permitido',
                'description' => 'Intento cruzado',
                'price' => '30.00',
                'duration_minutes' => 45,
                'active' => false,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('services', [
            'id' => $otherService->id,
            'name' => 'Servicio ajeno',
            'active' => true,
        ]);
    }

    public function test_owner_can_update_service_active_state(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $service = Service::factory()->for($barbershop)->create([
            'name' => 'Corte clásico',
            'active' => true,
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.services.update', [$barbershop, $service], false), [
                'name' => 'Corte clásico premium',
                'description' => 'Lavado incluido',
                'price' => '28.00',
                'duration_minutes' => 50,
                'active' => false,
            ])
            ->assertRedirect(route('tenant.services.index', $barbershop, false));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Corte clásico premium',
            'description' => 'Lavado incluido',
            'price' => 28.00,
            'duration_minutes' => 50,
            'active' => false,
        ]);
    }
}
