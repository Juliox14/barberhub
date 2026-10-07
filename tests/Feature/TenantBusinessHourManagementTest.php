<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBusinessHourManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_view_business_hours(): void
    {
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Centro']);
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.business-hours.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Horarios de Barbería Centro')
            ->assertSee('Lunes')
            ->assertSee('09:00');

        $this->actingAs($admin)
            ->get(route('tenant.business-hours.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Horarios de Barbería Centro')
            ->assertSee('Lunes');
    }

    public function test_barber_cannot_manage_business_hours(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($barber)
            ->get(route('tenant.business-hours.index', $barbershop, false))
            ->assertForbidden();

        $this->actingAs($barber)
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'opens_at' => '09:00',
                'closes_at' => '18:00',
            ])->assertForbidden();

        $this->assertDatabaseMissing('business_hours', [
            'barbershop_id' => $barbershop->id,
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
        ]);
    }

    public function test_platform_admin_can_manage_business_hours_for_support(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Soporte']);

        $this->actingAs($platformAdmin)
            ->get(route('tenant.business-hours.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Horarios de Barbería Soporte');

        $this->actingAs($platformAdmin)
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'weekday' => BusinessHour::WEEKDAY_TUESDAY,
                'opens_at' => '10:00',
                'closes_at' => '19:00',
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false));

        $this->assertDatabaseHas('business_hours', [
            'barbershop_id' => $barbershop->id,
            'weekday' => BusinessHour::WEEKDAY_TUESDAY,
            'opens_at' => '10:00',
            'closes_at' => '19:00',
            'closed' => false,
        ]);
    }

    public function test_owner_can_create_open_business_hour_with_valid_payload(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'weekday' => BusinessHour::WEEKDAY_WEDNESDAY,
                'opens_at' => '08:30',
                'closes_at' => '17:30',
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false));

        $this->assertDatabaseHas('business_hours', [
            'barbershop_id' => $barbershop->id,
            'weekday' => BusinessHour::WEEKDAY_WEDNESDAY,
            'opens_at' => '08:30',
            'closes_at' => '17:30',
            'closed' => false,
        ]);
    }

    public function test_owner_can_create_closed_business_hour_without_times(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'weekday' => BusinessHour::WEEKDAY_SUNDAY,
                'closed' => true,
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false));

        $this->assertDatabaseHas('business_hours', [
            'barbershop_id' => $barbershop->id,
            'weekday' => BusinessHour::WEEKDAY_SUNDAY,
            'opens_at' => null,
            'closes_at' => null,
            'closed' => true,
        ]);
    }

    public function test_store_prevents_duplicate_weekday_per_tenant(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_THURSDAY]);

        $this->actingAs($owner)
            ->from(route('tenant.business-hours.index', $barbershop, false))
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'weekday' => BusinessHour::WEEKDAY_THURSDAY,
                'opens_at' => '09:00',
                'closes_at' => '18:00',
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false))
            ->assertSessionHasErrors('weekday');

        $this->assertDatabaseCount('business_hours', 1);
    }

    public function test_same_weekday_is_allowed_in_different_tenants(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($otherBarbershop)->create(['weekday' => BusinessHour::WEEKDAY_FRIDAY]);

        $this->actingAs($owner)
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'weekday' => BusinessHour::WEEKDAY_FRIDAY,
                'opens_at' => '09:00',
                'closes_at' => '18:00',
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false));

        $this->assertDatabaseHas('business_hours', [
            'barbershop_id' => $barbershop->id,
            'weekday' => BusinessHour::WEEKDAY_FRIDAY,
        ]);
        $this->assertDatabaseHas('business_hours', [
            'barbershop_id' => $otherBarbershop->id,
            'weekday' => BusinessHour::WEEKDAY_FRIDAY,
        ]);
    }

    public function test_store_rejects_invalid_weekday_and_opening_time_not_before_closing_time(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->from(route('tenant.business-hours.index', $barbershop, false))
            ->post(route('tenant.business-hours.store', $barbershop, false), [
                'weekday' => 7,
                'opens_at' => '18:00',
                'closes_at' => '18:00',
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false))
            ->assertSessionHasErrors(['weekday', 'opens_at']);

        $this->assertDatabaseMissing('business_hours', [
            'barbershop_id' => $barbershop->id,
            'weekday' => 7,
        ]);
    }

    public function test_update_rejects_business_hour_from_another_tenant_with_404(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $otherBusinessHour = BusinessHour::factory()->for($otherBarbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_SATURDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.business-hours.update', [$barbershop, $otherBusinessHour], false), [
                'weekday' => BusinessHour::WEEKDAY_SATURDAY,
                'opens_at' => '10:00',
                'closes_at' => '19:00',
                'closed' => false,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('business_hours', [
            'id' => $otherBusinessHour->id,
            'barbershop_id' => $otherBarbershop->id,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);
    }

    public function test_owner_can_update_business_hour_between_closed_and_open_states(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $businessHour = BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.business-hours.update', [$barbershop, $businessHour], false), [
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'closed' => true,
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false));

        $this->assertDatabaseHas('business_hours', [
            'id' => $businessHour->id,
            'opens_at' => null,
            'closes_at' => null,
            'closed' => true,
        ]);

        $this->actingAs($owner)
            ->put(route('tenant.business-hours.update', [$barbershop, $businessHour], false), [
                'weekday' => BusinessHour::WEEKDAY_MONDAY,
                'opens_at' => '10:00',
                'closes_at' => '16:00',
                'closed' => false,
            ])
            ->assertRedirect(route('tenant.business-hours.index', $barbershop, false));

        $this->assertDatabaseHas('business_hours', [
            'id' => $businessHour->id,
            'opens_at' => '10:00',
            'closes_at' => '16:00',
            'closed' => false,
        ]);
    }
}
