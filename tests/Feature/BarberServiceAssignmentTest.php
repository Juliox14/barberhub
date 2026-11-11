<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BarberServiceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_barber_starts_with_all_existing_shop_services(): void
    {
        $barbershop = Barbershop::factory()->create();
        $services = Service::factory()->count(2)->for($barbershop)->create();
        $barber = Barber::factory()->for($barbershop)->create();

        $this->assertEqualsCanonicalizing($services->modelKeys(), $barber->services()->pluck('services.id')->all());
    }

    public function test_barber_can_view_and_update_only_their_own_service_assignments_and_clear_them(): void
    {
        [$barbershop, $serviceOne, $serviceTwo, $barber, $otherBarber] = $this->shopWithBarbers();

        $this->actingAs($barber->user)
            ->get(route('tenant.barber-services.self.edit', $barbershop, false))
            ->assertOk()
            ->assertSee($serviceOne->name)
            ->assertSee($serviceTwo->name);

        $this->actingAs($barber->user)
            ->put(route('tenant.barber-services.self.update', $barbershop, false), [
                'service_ids' => [$serviceOne->id],
            ])
            ->assertRedirect(route('tenant.barber-services.self.edit', $barbershop, false));

        $this->assertEquals([$serviceOne->id], $barber->fresh()->services()->pluck('services.id')->all());

        $this->actingAs($barber->user)
            ->put(route('tenant.barber-services.self.update', $barbershop, false), [
                'service_ids' => [],
            ])
            ->assertRedirect(route('tenant.barber-services.self.edit', $barbershop, false));

        $this->assertSame([], $barber->fresh()->services()->pluck('services.id')->all());

        $this->actingAs($barber->user)
            ->put(route('tenant.barber-services.update', [$barbershop, $otherBarber], false), [
                'service_ids' => [$serviceTwo->id],
            ])
            ->assertForbidden();

        $this->assertEqualsCanonicalizing([$serviceOne->id, $serviceTwo->id], $otherBarber->fresh()->services()->pluck('services.id')->all());

        $otherShop = Barbershop::factory()->create();
        $this->actingAs($barber->user)
            ->put(route('tenant.barber-services.self.update', $otherShop, false), [
                'service_ids' => [$serviceTwo->id],
            ])
            ->assertForbidden();

        $foreignBarber = Barber::factory()->for($otherShop)->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $this->actingAs($owner)
            ->put(route('tenant.barber-services.update', [$barbershop, $foreignBarber], false), [
                'service_ids' => [$serviceTwo->id],
            ])
            ->assertNotFound();
        $this->assertSame([], $foreignBarber->fresh()->services()->pluck('services.id')->all());
    }

    public function test_owner_and_admin_can_assign_any_barber_in_their_shop(): void
    {
        [$barbershop, $serviceOne, $serviceTwo, $barber] = $this->shopWithBarbers();

        foreach ([Membership::ROLE_OWNER, Membership::ROLE_ADMIN] as $role) {
            $manager = User::factory()->create();
            Membership::factory()->for($manager)->for($barbershop)->create(['role' => $role]);

            $this->actingAs($manager)
                ->put(route('tenant.barber-services.update', [$barbershop, $barber], false), [
                    'service_ids' => [$serviceTwo->id],
                ])
                ->assertRedirect(route('tenant.barber-services.edit', [$barbershop, $barber], false));

            $this->assertSame([$serviceTwo->id], $barber->fresh()->services()->pluck('services.id')->all());
        }
    }

    public function test_platform_admin_can_assign_services_for_support(): void
    {
        [$barbershop, , $serviceTwo, $barber] = $this->shopWithBarbers();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->put(route('tenant.barber-services.update', [$barbershop, $barber], false), [
                'service_ids' => [$serviceTwo->id],
            ])
            ->assertRedirect(route('tenant.barber-services.edit', [$barbershop, $barber], false));

        $this->assertSame([$serviceTwo->id], $barber->fresh()->services()->pluck('services.id')->all());
    }

    public function test_invalid_or_cross_tenant_service_ids_leave_assignments_unchanged(): void
    {
        [$barbershop, $serviceOne, $serviceTwo, $barber] = $this->shopWithBarbers();
        $otherShop = Barbershop::factory()->create();
        $foreignService = Service::factory()->for($otherShop)->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        foreach ([[$foreignService->id], [999999]] as $ids) {
            $this->actingAs($owner)
                ->from(route('tenant.barber-services.edit', [$barbershop, $barber], false))
                ->put(route('tenant.barber-services.update', [$barbershop, $barber], false), [
                    'service_ids' => $ids,
                ])
                ->assertRedirect(route('tenant.barber-services.edit', [$barbershop, $barber], false))
                ->assertSessionHasErrors('service_ids.0');

            $this->assertEqualsCanonicalizing([$serviceOne->id, $serviceTwo->id], $barber->fresh()->services()->pluck('services.id')->all());
        }
    }

    public function test_assignment_changes_do_not_delete_existing_appointments(): void
    {
        [$barbershop, $serviceOne, $serviceTwo, $barber] = $this->shopWithBarbers();
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->for($serviceOne)->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->put(route('tenant.barber-services.update', [$barbershop, $barber], false), [
                'service_ids' => [$serviceTwo->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'service_id' => $serviceOne->id]);
    }

    public function test_migration_backfills_existing_same_shop_barber_service_pairs(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherShop = Barbershop::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        $otherService = Service::factory()->for($otherShop)->create();

        Schema::drop('barber_service');
        $migrationPath = collect(glob(database_path('migrations/*create_barber_service_table.php')))->first();
        $migration = require $migrationPath;
        $migration->up();

        $this->assertDatabaseHas('barber_service', ['barber_id' => $barber->id, 'service_id' => $service->id]);
        $this->assertDatabaseMissing('barber_service', ['barber_id' => $barber->id, 'service_id' => $otherService->id]);
    }

    /** @return array{Barbershop, Service, Service, Barber, Barber} */
    private function shopWithBarbers(): array
    {
        $barbershop = Barbershop::factory()->create();
        $serviceOne = Service::factory()->for($barbershop)->create(['name' => 'Corte clásico']);
        $serviceTwo = Service::factory()->for($barbershop)->create(['name' => 'Afeitado']);
        $barber = Barber::factory()->for($barbershop)->create();
        $otherBarber = Barber::factory()->for($barbershop)->create();
        Membership::query()->create([
            'user_id' => $barber->user_id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        Membership::query()->create([
            'user_id' => $otherBarber->user_id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        return [$barbershop, $serviceOne, $serviceTwo, $barber, $otherBarber];
    }
}
