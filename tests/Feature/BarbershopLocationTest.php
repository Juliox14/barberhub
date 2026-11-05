<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\CountryNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BarbershopLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_form_uses_spanish_names_and_legacy_iso2_payloads_still_persist(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create(['country_code' => 'MX']);

        $this->actingAs($admin)
            ->get(route('platform.barbershops.edit', $shop, false))
            ->assertOk()
            ->assertSee('<select id="country_code" name="country_code"', false)
            ->assertSee('Seleccionar país (opcional)')
            ->assertSee('<option value="MX" selected>México</option>', false)
            ->assertDontSee('País (código de dos letras)');

        $this->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Barbería Alemania',
                'slug' => 'barberia-alemania',
                'timezone' => 'America/Bogota',
                'owner_email' => 'missing@example.com',
                'country_code' => 'de',
            ])
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors('owner_email');

        $this->get(route('platform.barbershops.create', absolute: false))
            ->assertSee('<option value="DE" selected>Alemania</option>', false);

        $this->put(route('platform.barbershops.update', $shop, false), [
            'name' => $shop->name,
            'slug' => $shop->slug,
            'timezone' => 'America/Mexico_City',
            'status' => $shop->status,
            'country_code' => 'us',
        ])->assertRedirect(route('platform.barbershops.index', absolute: false));

        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'country_code' => 'US']);

        $this->put(route('platform.barbershops.update', $shop, false), [
            'name' => $shop->name,
            'slug' => $shop->slug,
            'timezone' => 'America/Mexico_City',
            'status' => $shop->status,
            'country_code' => '',
        ])->assertRedirect(route('platform.barbershops.index', absolute: false));
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'country_code' => null]);

        $this->get(route('platform.barbershops.edit', $shop, false))
            ->assertSee('<option value="" selected>Seleccionar país (opcional)</option>', false);
    }

    public function test_country_names_cover_the_full_existing_iso2_catalog(): void
    {
        $countries = CountryNames::all();

        $this->assertCount(249, $countries);
        $this->assertSame('Andorra', $countries['AD']);
        $this->assertSame('Argentina', $countries['AR']);
        $this->assertSame('México', $countries['MX']);
        $this->assertSame('Estados Unidos', $countries['US']);
        $this->assertSame('Alemania', $countries['DE']);
        $this->assertSame('Japón', $countries['JP']);
    }

    public function test_public_booking_address_uses_spanish_country_name_for_existing_iso2_data(): void
    {
        $shop = Barbershop::factory()->create([
            'country_code' => 'MX',
            'city' => 'Guadalajara',
            'street_address' => 'Av. Vallarta 123',
        ]);

        $this->get(route('public.booking.create', $shop))->assertOk()
            ->assertSee('Av. Vallarta 123, Guadalajara, México')
            ->assertDontSee('Guadalajara, MX');
    }

    public function test_platform_admin_can_create_and_update_barbershop_location(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $location = [
            'country_code' => 'MX', 'region' => 'Jalisco', 'city' => 'Guadalajara',
            'street_address' => 'Av. Vallarta 123', 'latitude' => '0', 'longitude' => '-103.3496',
        ];

        $this->actingAs($admin)->post(route('platform.barbershops.store', absolute: false), [
            'name' => 'Barbería Norte', 'slug' => 'barberia-norte', 'timezone' => 'America/Mexico_City',
            'owner_email' => $owner->email, ...$location,
        ])->assertRedirect(route('platform.barbershops.index', absolute: false));

        $shop = Barbershop::query()->where('slug', 'barberia-norte')->firstOrFail();
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, ...$location]);
        $this->assertDatabaseHas('memberships', ['user_id' => $owner->id, 'barbershop_id' => $shop->id, 'role' => Membership::ROLE_OWNER]);

        $this->put(route('platform.barbershops.update', $shop, false), [
            'name' => $shop->name, 'slug' => $shop->slug, 'timezone' => $shop->timezone,
            'status' => $shop->status, ...$location, 'city' => 'Zapopan',
        ])->assertRedirect(route('platform.barbershops.index', absolute: false));

        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'city' => 'Zapopan', 'latitude' => '0.0000000']);
    }

    public function test_invalid_location_is_rejected_without_creating_or_changing_records(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $shop = Barbershop::factory()->create(['country_code' => 'CA', 'city' => 'Toronto']);
        $payload = [
            'name' => 'Nueva', 'slug' => 'nueva', 'timezone' => 'America/Bogota', 'owner_email' => $owner->email,
            'country_code' => 'ZZ', 'latitude' => '91', 'longitude' => '0',
        ];

        $this->actingAs($admin)->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), $payload)
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors(['country_code', 'latitude']);
        $this->assertDatabaseCount('barbershops', 1);
        $this->assertDatabaseCount('memberships', 0);

        $this->put(route('platform.barbershops.update', $shop, false), [
            'name' => $shop->name, 'slug' => $shop->slug, 'timezone' => $shop->timezone, 'status' => $shop->status,
            'country_code' => 'CA', 'latitude' => '45',
        ])->assertSessionHasErrors('longitude');
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'city' => 'Toronto', 'country_code' => 'CA']);
    }

    public function test_owner_can_update_location_and_legacy_payload_preserves_it(): void
    {
        $shop = Barbershop::factory()->create(['country_code' => 'AR', 'region' => 'Córdoba', 'city' => 'Córdoba']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_ACTIVE]);
        $colors = ['brand_primary_color' => '#123456', 'brand_accent_color' => '#654321'];

        $this->actingAs($owner)->put(route('tenant.branding.update', $shop, false), $colors + [
            'country_code' => 'BR', 'region' => 'São Paulo', 'city' => 'São Paulo',
            'street_address' => 'Rua A <script>alert(1)</script>', 'latitude' => '-23.5505', 'longitude' => '-46.6333',
        ])->assertRedirect(route('tenant.branding.edit', $shop, false));
        $shop->refresh();
        $this->assertSame('BR', $shop->country_code);

        $this->put(route('tenant.branding.update', $shop, false), $colors)->assertRedirect();
        $this->assertSame('BR', $shop->fresh()->country_code);

        $booking = $this->get(route('public.booking.create', $shop))->assertOk();
        $booking->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('https://maps.google.com/maps?q=-23.5505,-46.6333&amp;z=16&amp;output=embed', false)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=-23.5505,-46.6333', false)
            ->assertSee('loading="lazy"', false)->assertSee('referrerpolicy="no-referrer-when-downgrade"', false)
            ->assertDontSee('maps.googleapis.com')->assertDontSee('key=');
    }

    public function test_owner_cannot_leave_a_partial_stored_coordinate_pair_and_can_clear_both(): void
    {
        $shop = Barbershop::factory()->create(['latitude' => '12.5', 'longitude' => '-45.25']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_ACTIVE]);
        $branding = ['brand_primary_color' => '#123456', 'brand_accent_color' => '#654321'];

        $this->actingAs($owner)->from(route('tenant.branding.edit', $shop, false))
            ->put(route('tenant.branding.update', $shop, false), $branding + ['latitude' => null])
            ->assertRedirect(route('tenant.branding.edit', $shop, false))
            ->assertSessionHasErrors('latitude');
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'latitude' => '12.5', 'longitude' => '-45.25']);

        $this->put(route('tenant.branding.update', $shop, false), $branding + ['latitude' => null, 'longitude' => null])
            ->assertRedirect(route('tenant.branding.edit', $shop, false));
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'latitude' => null, 'longitude' => null]);
    }

    public function test_old_shop_without_location_renders_no_map_and_address_without_coordinates_has_no_position(): void
    {
        $shop = Barbershop::factory()->create();

        $this->get(route('public.booking.create', $shop))->assertOk()
            ->assertDontSee('<iframe', false)->assertDontSee('google.com/maps', false);

        $shop->update(['country_code' => 'US', 'region' => 'Alaska', 'city' => 'Anchorage', 'street_address' => '1 Main St']);
        $this->get(route('public.booking.create', $shop))->assertOk()
            ->assertSee('1 Main St, Anchorage, Alaska, Estados Unidos')
            ->assertDontSee('<iframe', false)->assertDontSee('google.com/maps', false);
    }

    public function test_summary_and_confirmation_render_the_location(): void
    {
        Notification::fake();
        $this->travelTo('2026-10-05 08:00:00 UTC');
        $shop = Barbershop::factory()->create([
            'timezone' => 'America/Mexico_City', 'country_code' => 'MX', 'city' => 'Guadalajara',
            'street_address' => 'Av. Vallarta 123', 'latitude' => '20.6736', 'longitude' => '-103.344',
        ]);
        $service = Service::factory()->for($shop)->create(['duration_minutes' => 30]);
        $barber = Barber::factory()->for($shop)->create();
        BusinessHour::factory()->for($shop)->create(['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '11:00']);
        BarberAvailability::factory()->for($barber)->create(['weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $summary = $this->post(route('public.booking.summary', $shop), [
            'service_id' => $service->id, 'slot' => $barber->id.'|2026-10-05T10:00',
            'name' => $user->name, 'email' => $user->email,
        ])->assertOk()->assertSee('Av. Vallarta 123, Guadalajara, México')
            ->assertSee('https://maps.google.com/maps?q=20.6736,-103.344&amp;z=16&amp;output=embed', false);
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());

        $this->post(route('public.booking.store', $shop), ['draft_id' => $summary->viewData('draft_id')])
            ->assertOk()->assertSee('Av. Vallarta 123, Guadalajara, México')
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=20.6736,-103.344', false);
    }

    public function test_coordinate_bounds_and_zero_are_rendered_as_exact_numeric_map_destination(): void
    {
        $shop = Barbershop::factory()->create([
            'country_code' => 'MX', 'latitude' => '90', 'longitude' => '-180',
        ]);

        $this->get(route('public.booking.create', $shop))->assertOk()
            ->assertSee('https://maps.google.com/maps?q=90,-180&amp;z=16&amp;output=embed', false)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=90,-180', false);

        $shop->update(['latitude' => '0', 'longitude' => '0']);
        $this->get(route('public.booking.create', $shop))->assertOk()
            ->assertSee('https://maps.google.com/maps?q=0,0&amp;z=16&amp;output=embed', false);
    }
}
