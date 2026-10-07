<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBarberAvailabilityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_view_barber_availability(): void
    {
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Centro']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $barberUser = User::factory()->create(['name' => 'Carlos Barbero']);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        $barber = Barber::factory()->for($barbershop)->for($barberUser, 'user')->create(['display_name' => 'Carlos B.']);
        BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.barber-availability.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Disponibilidad de Barbería Centro')
            ->assertSee('Carlos B.')
            ->assertSee('Lunes')
            ->assertSee('09:00');

        $this->actingAs($admin)
            ->get(route('tenant.barber-availability.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Disponibilidad de Barbería Centro')
            ->assertSee('Carlos B.');
    }

    public function test_barber_cannot_manage_barber_availability(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barberUser = User::factory()->create();
        $profile = Barber::factory()->for($barbershop)->for($barberUser, 'user')->create();
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_TUESDAY]);
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($barberUser)
            ->get(route('tenant.barber-availability.index', $barbershop, false))
            ->assertForbidden();

        $this->actingAs($barberUser)
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $profile->id,
                'weekday' => BusinessHour::WEEKDAY_TUESDAY,
                'starts_at' => '10:00',
                'ends_at' => '16:00',
            ])->assertForbidden();

        $this->assertDatabaseMissing('barber_availabilities', [
            'barber_id' => $profile->id,
            'weekday' => BusinessHour::WEEKDAY_TUESDAY,
        ]);
    }

    public function test_platform_admin_can_manage_barber_availability_for_support(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Soporte']);
        $barber = Barber::factory()->for($barbershop)->create(['display_name' => 'Soporte Agenda']);
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_WEDNESDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
        ]);

        $this->actingAs($platformAdmin)
            ->get(route('tenant.barber-availability.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Disponibilidad de Barbería Soporte');

        $this->actingAs($platformAdmin)
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_WEDNESDAY,
                'starts_at' => '10:00',
                'ends_at' => '17:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false));

        $this->assertDatabaseHas('barber_availabilities', [
            'barber_id' => $barber->id,
            'weekday' => BusinessHour::WEEKDAY_WEDNESDAY,
            'starts_at' => '10:00',
            'ends_at' => '17:00',
            'active' => true,
        ]);
    }

    public function test_owner_can_create_availability_inside_business_hours(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create(['active' => true]);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_THURSDAY,
            'opens_at' => '08:30',
            'closes_at' => '17:30',
            'closed' => false,
        ]);

        $this->actingAs($owner)
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_THURSDAY,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false));

        $this->assertDatabaseHas('barber_availabilities', [
            'barber_id' => $barber->id,
            'weekday' => BusinessHour::WEEKDAY_THURSDAY,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'active' => true,
        ]);
    }

    public function test_store_rejects_inactive_barber_profile(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create(['active' => false]);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_FRIDAY]);

        $this->actingAs($owner)
            ->from(route('tenant.barber-availability.index', $barbershop, false))
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_FRIDAY,
                'starts_at' => '09:00',
                'ends_at' => '16:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('barber_id');

        $this->assertDatabaseMissing('barber_availabilities', [
            'barber_id' => $barber->id,
            'weekday' => BusinessHour::WEEKDAY_FRIDAY,
        ]);
    }

    public function test_store_rejects_barber_from_another_tenant(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $otherBarber = Barber::factory()->for($otherBarbershop)->create(['active' => true]);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_SATURDAY]);

        $this->actingAs($owner)
            ->from(route('tenant.barber-availability.index', $barbershop, false))
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $otherBarber->id,
                'weekday' => BusinessHour::WEEKDAY_SATURDAY,
                'starts_at' => '09:00',
                'ends_at' => '16:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('barber_id');

        $this->assertDatabaseMissing('barber_availabilities', [
            'barber_id' => $otherBarber->id,
        ]);
    }

    public function test_store_rejects_missing_or_closed_business_hour(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->closed()->create(['weekday' => BusinessHour::WEEKDAY_MONDAY]);

        $this->actingAs($owner)
            ->from(route('tenant.barber-availability.index', $barbershop, false))
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'starts_at' => '09:00',
                'ends_at' => '16:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('weekday');

        $this->actingAs($owner)
            ->from(route('tenant.barber-availability.index', $barbershop, false))
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_TUESDAY,
                'starts_at' => '09:00',
                'ends_at' => '16:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('weekday');

        $this->assertDatabaseMissing('barber_availabilities', [
            'barber_id' => $barber->id,
        ]);
    }

    public function test_store_rejects_availability_outside_business_hours(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_TUESDAY,
            'opens_at' => '10:00',
            'closes_at' => '18:00',
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.barber-availability.index', $barbershop, false))
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_TUESDAY,
                'starts_at' => '09:30',
                'ends_at' => '18:30',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseMissing('barber_availabilities', [
            'barber_id' => $barber->id,
            'weekday' => BusinessHour::WEEKDAY_TUESDAY,
        ]);
    }

    public function test_store_prevents_duplicate_weekday_per_barber(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_WEDNESDAY]);
        BarberAvailability::factory()->for($barber)->create(['weekday' => BusinessHour::WEEKDAY_WEDNESDAY]);

        $this->actingAs($owner)
            ->from(route('tenant.barber-availability.index', $barbershop, false))
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'weekday' => BusinessHour::WEEKDAY_WEDNESDAY,
                'starts_at' => '10:00',
                'ends_at' => '17:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false))
            ->assertSessionHasErrors('weekday');

        $this->assertDatabaseCount('barber_availabilities', 1);
    }

    public function test_same_weekday_is_allowed_for_another_barber_in_same_tenant(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $firstBarber = Barber::factory()->for($barbershop)->create();
        $secondBarber = Barber::factory()->for($barbershop)->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_THURSDAY]);
        BarberAvailability::factory()->for($firstBarber)->create(['weekday' => BusinessHour::WEEKDAY_THURSDAY]);

        $this->actingAs($owner)
            ->post(route('tenant.barber-availability.store', $barbershop, false), [
                'barber_id' => $secondBarber->id,
                'weekday' => BusinessHour::WEEKDAY_THURSDAY,
                'starts_at' => '10:00',
                'ends_at' => '17:00',
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false));

        $this->assertDatabaseHas('barber_availabilities', [
            'barber_id' => $firstBarber->id,
            'weekday' => BusinessHour::WEEKDAY_THURSDAY,
        ]);
        $this->assertDatabaseHas('barber_availabilities', [
            'barber_id' => $secondBarber->id,
            'weekday' => BusinessHour::WEEKDAY_THURSDAY,
        ]);
    }

    public function test_update_rejects_availability_from_another_tenant_with_404(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $otherBarber = Barber::factory()->for($otherBarbershop)->create();
        $otherAvailability = BarberAvailability::factory()->for($otherBarber)->create([
            'weekday' => BusinessHour::WEEKDAY_FRIDAY,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'active' => true,
        ]);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_FRIDAY]);

        $this->actingAs($owner)
            ->put(route('tenant.barber-availability.update', [$barbershop, $otherAvailability], false), [
                'weekday' => BusinessHour::WEEKDAY_FRIDAY,
                'starts_at' => '10:00',
                'ends_at' => '16:00',
                'active' => false,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('barber_availabilities', [
            'id' => $otherAvailability->id,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'active' => true,
        ]);
    }

    public function test_owner_can_update_availability_active_state(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create();
        $availability = BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_SATURDAY,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'active' => true,
        ]);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_SATURDAY,
            'opens_at' => '08:00',
            'closes_at' => '18:00',
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.barber-availability.update', [$barbershop, $availability], false), [
                'weekday' => BusinessHour::WEEKDAY_SATURDAY,
                'starts_at' => '10:00',
                'ends_at' => '16:00',
                'active' => false,
            ])
            ->assertRedirect(route('tenant.barber-availability.index', $barbershop, false));

        $this->assertDatabaseHas('barber_availabilities', [
            'id' => $availability->id,
            'weekday' => BusinessHour::WEEKDAY_SATURDAY,
            'starts_at' => '10:00',
            'ends_at' => '16:00',
            'active' => false,
        ]);
    }
}
