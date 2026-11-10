<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerPreference;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_verified_customer_sees_only_own_completed_visits_in_tenant_time_and_can_update_preferences(): void
    {
        $shop = Barbershop::factory()->create([
            'timezone' => 'America/Mexico_City',
            'brand_primary_color' => '#225588',
        ]);
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create([
            'created_at' => '2024-02-03 12:00:00',
        ]);
        $barber = Barber::factory()->for($shop)->create(['display_name' => 'Barbero local']);
        $service = Service::factory()->for($shop)->create(['name' => 'Corte local']);
        $inactiveBarber = Barber::factory()->for($shop)->create(['active' => false]);
        $inactiveService = Service::factory()->for($shop)->create(['active' => false]);
        $foreignShop = Barbershop::factory()->create();
        $foreignBarber = Barber::factory()->for($foreignShop)->create();
        $foreignService = Service::factory()->for($foreignShop)->create();
        $completed = Appointment::factory()->for($shop)->for($barber)->for($service)->for($customer)->completed()->create([
            'starts_at' => '2026-10-05 02:00:00',
        ]);
        Appointment::factory()->for($shop)->for($barber)->for($service)->for($customer)->create([
            'status' => Appointment::STATUS_SCHEDULED,
            'starts_at' => '2026-10-06 10:00:00',
        ]);
        Appointment::factory()->for($shop)->for($barber)->for($service)->create([
            'customer_id' => null,
            'status' => Appointment::STATUS_COMPLETED,
        ]);
        Appointment::factory()->for($foreignShop)->for($foreignBarber)->for($foreignService)->completed()->create([
            'customer_id' => $customer->id,
        ]);
        CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'preferred_barber_id' => $barber->id,
            'preferred_service_id' => $service->id,
            'notes' => 'Dejar volumen arriba.',
            'reminder_frequency' => 'monthly',
        ]]);

        $this->actingAs($user)->get(route('customer.profile.show', $shop))
            ->assertOk()
            ->assertSee('#225588')
            ->assertSee('4 de octubre de 2026')
            ->assertSee('8:00 p. m.')
            ->assertSee('Corte local')
            ->assertSee('Barbero local')
            ->assertSee('3 de febrero de 2024')
            ->assertSee('1 visita')
            ->assertDontSee('Cita futura')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 1
                && $appointments->first()->is($completed))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->contains('id', $barber->id)
                && ! $barbers->contains('id', $inactiveBarber->id))
            ->assertViewHas('services', fn ($services): bool => $services->contains('id', $service->id)
                && ! $services->contains('id', $inactiveService->id));

        $this->actingAs($user)->put(route('customer.profile.preferences.update', $shop), [
            'preferred_barber_id' => $barber->id,
            'preferred_service_id' => $service->id,
            'notes' => 'Dejar un poco de largo arriba.',
        ])->assertRedirect(route('customer.profile.show', $shop))
            ->assertSessionHas('status');

        $this->assertSame([
            'preferred_barber_id' => $barber->id,
            'preferred_service_id' => $service->id,
            'notes' => 'Dejar un poco de largo arriba.',
            'reminder_frequency' => 'monthly',
        ], $customer->preference()->firstOrFail()->preferences);
    }

    public function test_profile_requires_verified_customer_ownership_and_never_links_by_email_or_membership(): void
    {
        $shop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Customer::factory()->for($shop)->for($owner)->create(['email' => $otherUser->email]);
        Membership::factory()->for($otherUser)->for($shop)->create(['role' => Membership::ROLE_BARBER]);

        $this->get(route('customer.profile.show', $shop))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('customer.profile.show', $shop))->assertNotFound();
        $this->actingAs($otherUser)->get(route('customer.profile.show', $shop))->assertNotFound();
        $this->actingAs($owner)->get(route('customer.profile.show', Barbershop::factory()->create()))->assertNotFound();
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_invalid_cross_tenant_or_inactive_preference_ids_do_not_mutate_preferences(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        $otherShop = Barbershop::factory()->create();
        $inactiveBarber = Barber::factory()->for($shop)->create(['active' => false]);
        $foreignService = Service::factory()->for($otherShop)->create();
        CustomerPreference::factory()->for($customer)->create(['preferences' => [
            'notes' => 'Original',
            'future_setting' => ['enabled' => true],
        ]]);
        $original = $customer->preference()->firstOrFail()->preferences;
        $this->actingAs($user);

        $this->followingRedirects()->from(route('customer.profile.show', $shop))
            ->put(route('customer.profile.preferences.update', $shop), [
                'preferred_barber_id' => $inactiveBarber->id,
                'preferred_service_id' => $foreignService->id,
                'notes' => 'Alterado',
            ])->assertOk()
            ->assertSee('aria-describedby="preferred-barber-error"', false)
            ->assertSee('class="bh-profile-field-error"', false)
            ->assertSee('role="alert"', false);
        $this->assertSame($original, $customer->preference()->firstOrFail()->preferences);
    }

    public function test_completed_history_is_paginated_and_reserve_links_use_active_tenant_options(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        $customer = Customer::factory()->for($shop)->for($user)->create();
        $barber = Barber::factory()->for($shop)->create();
        $service = Service::factory()->for($shop)->create();
        for ($visit = 0; $visit < 11; $visit++) {
            Appointment::factory()->for($shop)->for($barber)->for($service)->for($customer)->completed()->create([
                'starts_at' => now()->subDays($visit + 1),
            ]);
        }
        Appointment::factory()->for($shop)->for($barber)->for($service)->for($customer)->cancelled()->create();

        $this->actingAs($user)->get(route('customer.profile.show', $shop))
            ->assertOk()
            ->assertSee('11 visitas')
            ->assertSee('page=2')
            ->assertDontSee('Cancelar')
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->total() === 11
                && $appointments->count() === 10);
        $this->get(route('customer.profile.show', [$shop, 'page' => 2]))
            ->assertOk()
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->count() === 1);
    }

    public function test_profile_link_is_customer_only_and_booking_page_still_renders(): void
    {
        $shop = Barbershop::factory()->create();
        $user = User::factory()->create();
        Customer::factory()->for($shop)->for($user)->create();

        $this->actingAs($user)->get(route('public.booking.create', $shop))
            ->assertOk()->assertSee(route('customer.profile.show', $shop));
        $this->app['auth']->forgetGuards();
        $this->get(route('public.booking.create', $shop))->assertOk()->assertDontSee(route('customer.profile.show', $shop));
    }
}
