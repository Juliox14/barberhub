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

    public function test_create_and_edit_forms_offer_only_regional_timezones_and_preserve_existing_values(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['timezone' => 'UTC']);

        $this->actingAs($platformAdmin)
            ->get(route('platform.barbershops.create', absolute: false))
            ->assertOk()
            ->assertSee('value="America/Bogota"', false)
            ->assertSee('value="America/Mexico_City"', false)
            ->assertSee('value="America/Sao_Paulo"', false)
            ->assertSee('value="America/Argentina/Buenos_Aires"', false)
            ->assertSee('value="America/Santiago"', false)
            ->assertSee('value="Pacific/Easter"', false)
            ->assertDontSee('value="America/New_York"', false)
            ->assertDontSee('value="America/Toronto"', false)
            ->assertDontSee('value="Europe/Madrid"', false);

        $this->get(route('platform.barbershops.edit', $barbershop, false))
            ->assertOk()
            ->assertSee('value="UTC" selected disabled', false)
            ->assertSee('UTC', false)
            ->assertSee('<label for="timezone"', false)
            ->assertSee('>Zona horaria</label>', false)
            ->assertSee('id="timezone-search" type="search" x-cloak', false);
    }

    public function test_invalid_timezone_is_rejected_on_create_and_old_selection_is_retained(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Barbería inválida',
                'slug' => 'barberia-invalida',
                'timezone' => 'America/New_York',
                'owner_email' => $owner->email,
            ])
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors(['timezone' => 'La zona horaria debe pertenecer a Latinoamérica.']);

        $this->assertDatabaseMissing('barbershops', ['slug' => 'barberia-invalida']);
        $this->assertDatabaseMissing('memberships', ['user_id' => $owner->id]);

        $this->get(route('platform.barbershops.create', absolute: false))
            ->assertSee('value="America/New_York" selected disabled', false);

        $this->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Barbería con zona inventada',
                'slug' => 'barberia-zona-inventada',
                'timezone' => 'Mars/Inventada',
                'owner_email' => $owner->email,
            ])
            ->assertSessionHasErrors(['timezone' => 'La zona horaria debe pertenecer a Latinoamérica.']);
        $this->assertDatabaseMissing('barbershops', ['slug' => 'barberia-zona-inventada']);
    }

    public function test_invalid_timezone_is_rejected_on_update_without_changing_saved_data_or_memberships(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Bogota']);
        $owner = User::factory()->create();
        $membership = Membership::factory()->create(['barbershop_id' => $barbershop->id, 'user_id' => $owner->id]);
        $originalMembershipAttributes = $membership->getAttributes();
        ksort($originalMembershipAttributes);

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.edit', $barbershop, false))
            ->put(route('platform.barbershops.update', $barbershop, false), [
                'name' => 'Nombre alterado',
                'slug' => $barbershop->slug,
                'timezone' => 'Europe/Madrid',
                'status' => Barbershop::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('platform.barbershops.edit', $barbershop, false))
            ->assertSessionHasErrors(['timezone' => 'La zona horaria debe pertenecer a Latinoamérica.']);

        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'name' => $barbershop->name,
            'timezone' => 'America/Bogota',
            'status' => Barbershop::STATUS_ACTIVE,
        ]);
        $updatedMembershipAttributes = $membership->fresh()->getAttributes();
        ksort($updatedMembershipAttributes);
        $this->assertSame($originalMembershipAttributes, $updatedMembershipAttributes);

        $this->get(route('platform.barbershops.edit', $barbershop, false))
            ->assertSee('value="Europe/Madrid" selected disabled', false);

        $this->from(route('platform.barbershops.edit', $barbershop, false))
            ->put(route('platform.barbershops.update', $barbershop, false), [
                'name' => $barbershop->name,
                'slug' => $barbershop->slug,
                'timezone' => 'Mars/Inventada',
                'status' => Barbershop::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors(['timezone' => 'La zona horaria debe pertenecer a Latinoamérica.']);
        $this->assertDatabaseHas('barbershops', ['id' => $barbershop->id, 'timezone' => 'America/Bogota']);
    }

    public function test_regional_timezone_selection_survives_other_validation_errors_on_create_and_edit(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Bogota']);
        $newTimezone = 'America/Sao_Paulo';
        $editTimezone = 'America/Argentina/Buenos_Aires';

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Barbería con correo inválido',
                'slug' => 'barberia-correo-invalido',
                'timezone' => $newTimezone,
                'owner_email' => 'no-existe@example.com',
            ])
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors('owner_email');

        $createForm = $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('platform.barbershops.create', absolute: false));
        $createForm->assertSee('value="'.$newTimezone.'" selected', false);
        $this->assertDatabaseMissing('barbershops', ['slug' => 'barberia-correo-invalido']);

        $this->from(route('platform.barbershops.edit', $barbershop, false))
            ->put(route('platform.barbershops.update', $barbershop, false), [
                'name' => 'Nombre con slug inválido',
                'slug' => 'slug invalido',
                'timezone' => $editTimezone,
                'status' => Barbershop::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('platform.barbershops.edit', $barbershop, false))
            ->assertSessionHasErrors('slug');

        $editForm = $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('platform.barbershops.edit', $barbershop, false));
        $editForm->assertSee('value="'.$editTimezone.'" selected', false);
        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'timezone' => 'America/Bogota',
        ]);
    }

    public function test_edit_form_selects_a_saved_regional_timezone(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Sao_Paulo']);

        $this->actingAs($platformAdmin)
            ->get(route('platform.barbershops.edit', $barbershop, false))
            ->assertOk()
            ->assertSee('value="America/Sao_Paulo" selected', false);
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
