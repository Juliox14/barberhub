<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BarbershopLocationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_form_uses_configured_browser_key_and_google_address_widget(): void
    {
        config(['services.google_maps.api_key' => 'restricted-browser-key']);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get(route('platform.barbershops.create', absolute: false))
            ->assertOk()
            ->assertSee('data-api-key="restricted-browser-key"', false)
            ->assertSee('data-location-search-widget', false)
            ->assertSee('Las coordenadas son manuales')
            ->assertDontSee('Nominatim')
            ->assertDontSee('data-endpoint=');
    }

    public function test_owner_form_exposes_key_only_inside_existing_authorized_location_form(): void
    {
        config(['services.google_maps.api_key' => 'key" onfocus="alert(1)']);
        $shop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create([
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.branding.edit', $shop, false))
            ->assertOk()
            ->assertSee('data-api-key="key&quot; onfocus=&quot;alert(1)"', false)
            ->assertDontSee('data-api-key="key" onfocus=', false);
    }

    public function test_google_key_defaults_to_empty_and_nominatim_endpoint_is_retired(): void
    {
        config(['services.google_maps.api_key' => '']);

        $this->assertSame('', config('services.google_maps.api_key'));
        $this->assertFalse(Route::has('barbershop.location.search'));
        $this->assertArrayNotHasKey('nominatim', config('services'));
    }
}
