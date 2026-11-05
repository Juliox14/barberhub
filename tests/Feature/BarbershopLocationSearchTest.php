<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BarbershopLocationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_form_uses_manual_address_fields_and_google_maps_link(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get(route('platform.barbershops.create', absolute: false))
            ->assertOk()
            ->assertSee('name="country_code"', false)
            ->assertSee('name="region"', false)
            ->assertSee('name="city"', false)
            ->assertSee('name="street_address"', false)
            ->assertSee('name="latitude"', false)
            ->assertSee('name="longitude"', false)
            ->assertSee('Abrir Google Maps')
            ->assertDontSee('data-api-key=')
            ->assertDontSee('location-search-widget')
            ->assertDontSee('maps.googleapis.com');
    }

    public function test_google_sdk_configuration_and_lookup_route_are_retired(): void
    {
        $this->assertFalse(Route::has('barbershop.location.search'));
        $this->assertArrayNotHasKey('google_maps', config('services'));
        $this->assertArrayNotHasKey('nominatim', config('services'));
    }
}
