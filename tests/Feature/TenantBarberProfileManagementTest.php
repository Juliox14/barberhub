<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBarberProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_view_barber_profiles(): void
    {
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Centro']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $barberUser = User::factory()->create(['name' => 'Carlos Barbero']);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        Barber::factory()->for($barbershop)->for($barberUser, 'user')->create(['display_name' => 'Carlos B.']);

        $this->actingAs($owner)
            ->get(route('tenant.barbers.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Barberos de Barbería Centro')
            ->assertSee('Carlos B.');

        $this->actingAs($admin)
            ->get(route('tenant.barbers.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Barberos de Barbería Centro')
            ->assertSee('Carlos B.');
    }

    public function test_barber_cannot_manage_barber_profiles(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barberUser = User::factory()->create();
        $candidate = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        Membership::factory()->for($candidate)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($barberUser)
            ->get(route('tenant.barbers.index', $barbershop, false))
            ->assertForbidden();

        $this->actingAs($barberUser)
            ->post(route('tenant.barbers.store', $barbershop, false), [
                'user_id' => $candidate->id,
                'display_name' => 'Candidato',
            ])->assertForbidden();

        $this->assertDatabaseMissing('barbers', [
            'user_id' => $candidate->id,
            'barbershop_id' => $barbershop->id,
        ]);
    }

    public function test_platform_admin_can_manage_barber_profiles_for_support(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Soporte']);
        $barberUser = User::factory()->create(['name' => 'Soporte Barbero']);
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($platformAdmin)
            ->get(route('tenant.barbers.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Barberos de Barbería Soporte');

        $this->actingAs($platformAdmin)
            ->post(route('tenant.barbers.store', $barbershop, false), [
                'user_id' => $barberUser->id,
                'display_name' => 'Soporte Agenda',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false));

        $this->assertDatabaseHas('barbers', [
            'user_id' => $barberUser->id,
            'barbershop_id' => $barbershop->id,
            'display_name' => 'Soporte Agenda',
            'active' => true,
        ]);
    }

    public function test_owner_can_create_profile_for_active_same_tenant_member(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barberUser = User::factory()->create(['name' => 'Ana Navajas']);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($barberUser)->for($barbershop)->create([
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->post(route('tenant.barbers.store', $barbershop, false), [
                'user_id' => $barberUser->id,
                'display_name' => 'Ana Cortes',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false));

        $this->assertDatabaseHas('barbers', [
            'user_id' => $barberUser->id,
            'barbershop_id' => $barbershop->id,
            'display_name' => 'Ana Cortes',
            'active' => true,
        ]);
    }

    public function test_store_rejects_member_from_another_tenant(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $otherMember = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($otherMember)->for($otherBarbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($owner)
            ->from(route('tenant.barbers.index', $barbershop, false))
            ->post(route('tenant.barbers.store', $barbershop, false), [
                'user_id' => $otherMember->id,
                'display_name' => 'Fuera de tenant',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('barbers', [
            'user_id' => $otherMember->id,
            'barbershop_id' => $barbershop->id,
        ]);
    }

    public function test_store_prevents_duplicate_barber_profile(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barberUser = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        Barber::factory()->for($barbershop)->for($barberUser, 'user')->create(['display_name' => 'Existente']);

        $this->actingAs($owner)
            ->from(route('tenant.barbers.index', $barbershop, false))
            ->post(route('tenant.barbers.store', $barbershop, false), [
                'user_id' => $barberUser->id,
                'display_name' => 'Duplicado',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('barbers', 1);
        $this->assertDatabaseHas('barbers', [
            'user_id' => $barberUser->id,
            'barbershop_id' => $barbershop->id,
            'display_name' => 'Existente',
        ]);
    }

    public function test_store_rejects_inactive_membership(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $inactiveMember = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($inactiveMember)->for($barbershop)->create([
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_INACTIVE,
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.barbers.index', $barbershop, false))
            ->post(route('tenant.barbers.store', $barbershop, false), [
                'user_id' => $inactiveMember->id,
                'display_name' => 'Inactivo',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false))
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('barbers', [
            'user_id' => $inactiveMember->id,
            'barbershop_id' => $barbershop->id,
        ]);
    }

    public function test_update_rejects_barber_profile_from_another_tenant_with_404(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $otherBarber = Barber::factory()->for($otherBarbershop)->for($otherUser, 'user')->create(['active' => true]);

        $this->actingAs($owner)
            ->put(route('tenant.barbers.update', [$barbershop, $otherBarber], false), [
                'display_name' => 'No permitido',
                'active' => false,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('barbers', [
            'id' => $otherBarber->id,
            'active' => true,
        ]);
    }

    public function test_owner_can_update_barber_profile_active_status(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barberUser = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->for($barberUser, 'user')->create([
            'display_name' => 'Nombre actual',
            'active' => true,
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.barbers.update', [$barbershop, $barber], false), [
                'display_name' => 'Nombre agenda',
                'active' => false,
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false));

        $this->assertDatabaseHas('barbers', [
            'id' => $barber->id,
            'display_name' => 'Nombre agenda',
            'active' => false,
        ]);
    }
}
