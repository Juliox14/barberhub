<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformBarbershopManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_barbershop_index(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Central']);

        $this->actingAs($platformAdmin)
            ->get(route('platform.barbershops.index', absolute: false))
            ->assertOk()
            ->assertSee('Barberías')
            ->assertSee($barbershop->name);
    }

    public function test_non_platform_admin_cannot_manage_platform_barbershops(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $barbershop = Barbershop::factory()->create(['slug' => 'barberia-restringida']);

        $this->actingAs($user)
            ->get(route('platform.barbershops.index', absolute: false))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('platform.barbershops.create', absolute: false))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Nueva Barbería',
                'slug' => 'nueva-barberia',
                'timezone' => 'America/Bogota',
                'owner_email' => $owner->email,
            ])->assertForbidden();

        $this->actingAs($user)
            ->get(route('platform.barbershops.edit', $barbershop, false))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('platform.barbershops.update', $barbershop, false), [
                'name' => 'Barbería Restringida',
                'slug' => 'barberia-restringida',
                'timezone' => 'America/Bogota',
                'status' => Barbershop::STATUS_ACTIVE,
            ])->assertForbidden();

        $this->actingAs($user)
            ->delete(route('platform.barbershops.destroy', $barbershop, false))
            ->assertForbidden();
    }

    public function test_platform_admin_can_create_barbershop_with_existing_owner_email(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create(['email' => 'duena@example.com']);

        $response = $this->actingAs($platformAdmin)
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Barbería Norte',
                'slug' => 'barberia-norte',
                'timezone' => 'America/Bogota',
                'owner_email' => $owner->email,
            ]);

        $barbershop = Barbershop::query()->where('slug', 'barberia-norte')->firstOrFail();

        $response->assertRedirect(route('platform.barbershops.index', absolute: false));

        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'name' => 'Barbería Norte',
            'slug' => 'barberia-norte',
            'timezone' => 'America/Bogota',
            'status' => Barbershop::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('memberships', [
            'user_id' => $owner->id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
    }

    public function test_creation_validates_unique_slug(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        Barbershop::factory()->create(['slug' => 'slug-existente']);

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Otra Barbería',
                'slug' => 'slug-existente',
                'timezone' => 'America/Bogota',
                'owner_email' => $owner->email,
            ])
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors('slug');
    }

    public function test_platform_admin_can_view_edit_form(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create([
            'name' => 'Barbería Central',
            'slug' => 'barberia-central',
        ]);

        $this->actingAs($platformAdmin)
            ->get(route('platform.barbershops.edit', $barbershop, false))
            ->assertOk()
            ->assertSee('Editar barbería')
            ->assertSee('Barbería Central')
            ->assertSee('barberia-central');
    }

    public function test_platform_admin_can_update_barbershop(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create([
            'name' => 'Barbería Central',
            'slug' => 'barberia-central',
            'timezone' => 'America/Bogota',
            'status' => Barbershop::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($platformAdmin)
            ->put(route('platform.barbershops.update', $barbershop, false), [
                'name' => 'Barbería Central Renovada',
                'slug' => 'barberia-central',
                'timezone' => 'America/Mexico_City',
                'status' => Barbershop::STATUS_INACTIVE,
            ]);

        $response->assertRedirect(route('platform.barbershops.index', absolute: false));

        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'name' => 'Barbería Central Renovada',
            'slug' => 'barberia-central',
            'timezone' => 'America/Mexico_City',
            'status' => Barbershop::STATUS_INACTIVE,
        ]);
    }

    public function test_update_validates_unique_slug_except_current_barbershop(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['slug' => 'barberia-central']);
        Barbershop::factory()->create(['slug' => 'otra-barberia']);

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.edit', $barbershop, false))
            ->put(route('platform.barbershops.update', $barbershop, false), [
                'name' => 'Barbería Central',
                'slug' => 'otra-barberia',
                'timezone' => 'America/Bogota',
                'status' => Barbershop::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('platform.barbershops.edit', $barbershop, false))
            ->assertSessionHasErrors('slug');
    }

    public function test_platform_admin_can_delete_barbershop(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Central']);
        Membership::query()->create([
            'user_id' => $owner->id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($platformAdmin)
            ->delete(route('platform.barbershops.destroy', $barbershop, false));

        $response->assertRedirect(route('platform.barbershops.index', absolute: false))
            ->assertSessionHas('status', 'Barbería eliminada correctamente.');

        $this->assertDatabaseMissing('barbershops', [
            'id' => $barbershop->id,
        ]);
        $this->assertDatabaseMissing('memberships', [
            'barbershop_id' => $barbershop->id,
        ]);
    }

    public function test_creation_validates_owner_email_must_exist(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Barbería Sin Dueño',
                'slug' => 'barberia-sin-dueno',
                'timezone' => 'America/Bogota',
                'owner_email' => 'no-existe@example.com',
            ])
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors('owner_email');
    }

    public function test_platform_dashboard_contains_link_to_barbershop_management(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->get(route('platform.dashboard', absolute: false))
            ->assertOk()
            ->assertSee('Gestionar barberías')
            ->assertSee(route('platform.barbershops.index'), false);
    }
}
