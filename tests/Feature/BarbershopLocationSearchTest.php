<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BarbershopLocationSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nominatim.enabled' => true, 'services.nominatim.search_url' => 'https://nominatim.test/search', 'services.nominatim.user_agent' => 'BarberHub tests', 'services.nominatim.cache_store' => 'array']);
    }

    public function test_platform_admin_can_search_and_receives_normalized_choices(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([[
            'lat' => '20.67361234', 'lon' => '-103.34321234', 'display_name' => 'Av. Vallarta 123, Guadalajara',
            'address' => ['country_code' => 'mx', 'state' => 'Jalisco', 'city' => 'Guadalajara', 'road' => 'Av. Vallarta', 'house_number' => '123'],
        ]])]);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create(['slug' => 'barberia-norte']);

        $response = $this->actingAs($admin)->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $shop->slug, 'q' => ' Av. Vallarta 123, Guadalajara ']);

        $response->assertOk()->assertJsonPath('results.0.latitude', '20.6736123')->assertJsonPath('results.0.longitude', '-103.3432123')
            ->assertJsonPath('results.0.country_code', 'MX')->assertJsonPath('results.0.region', 'Jalisco')
            ->assertJsonPath('results.0.city', 'Guadalajara')->assertJsonPath('results.0.street_address', 'Av. Vallarta 123');
        $sentRequest = Http::recorded()->first()[0];
        parse_str((string) parse_url($sentRequest->url(), PHP_URL_QUERY), $query);
        $this->assertSame('/search', parse_url($sentRequest->url(), PHP_URL_PATH));
        $this->assertSame(['format' => 'jsonv2', 'addressdetails' => '1', 'limit' => '5', 'q' => 'Av. Vallarta 123, Guadalajara'], $query);
        $this->assertSame(['BarberHub tests'], $sentRequest->header('User-Agent'));
        $this->assertSame(['es'], $sentRequest->header('Accept-Language'));
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'street_address' => null]);
    }

    public function test_active_owner_can_search_but_unrelated_or_inactive_members_cannot_send_provider_requests(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([])]);
        $shop = Barbershop::factory()->create();
        $otherShop = Barbershop::factory()->create();
        $inactiveShop = Barbershop::factory()->create(['status' => Barbershop::STATUS_INACTIVE]);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_ACTIVE]);
        Membership::factory()->for($owner)->for($inactiveShop)->create(['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_ACTIVE]);

        $this->actingAs($owner)->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $shop->slug, 'q' => 'Calle Central 12'])->assertOk();
        $this->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $otherShop->slug, 'q' => 'Calle Central 12'])->assertForbidden();
        $this->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $inactiveShop->slug, 'q' => 'Calle Central 12'])->assertForbidden();
        $this->assertSame(1, Http::recorded()->count());
    }

    public function test_staff_inactive_owner_unverified_user_and_guest_are_rejected_before_provider_request(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([])]);
        $shop = Barbershop::factory()->create();
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($shop)->create(['role' => Membership::ROLE_BARBER, 'status' => Membership::STATUS_ACTIVE]);
        $inactiveOwner = User::factory()->create();
        Membership::factory()->for($inactiveOwner)->for($shop)->create(['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_INACTIVE]);
        $unverified = User::factory()->unverified()->create();
        Membership::factory()->for($unverified)->for($shop)->create(['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_ACTIVE]);
        $payload = ['barbershop_slug' => $shop->slug, 'q' => 'Calle Central 12'];

        $this->actingAs($staff)->postJson(route('barbershop.location.search', absolute: false), $payload)->assertForbidden();
        $this->actingAs($inactiveOwner)->postJson(route('barbershop.location.search', absolute: false), $payload)->assertForbidden();
        auth()->logout();
        $this->postJson(route('barbershop.location.search', absolute: false), $payload)->assertUnauthorized();
        $this->actingAs($unverified)->postJson(route('barbershop.location.search', absolute: false), $payload)->assertForbidden();
        $this->assertSame(0, Http::recorded()->count());
    }

    public function test_invalid_query_is_rejected_without_outbound_request_or_location_changes(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([])]);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create(['city' => 'Guadalajara']);

        $this->actingAs($admin)->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $shop->slug, 'q' => 'abc'])->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'city' => 'Guadalajara']);
    }

    public function test_successful_query_is_cached_and_global_cooldown_rejects_another_user(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([])]);
        $shop = Barbershop::factory()->create();
        $first = User::factory()->create(['is_platform_admin' => true]);
        $second = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($first)->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $shop->slug, 'q' => 'Calle Central 12'])->assertOk();
        $this->actingAs($second)->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $shop->slug, 'q' => 'Calle Central 12'])->assertOk();
        $this->actingAs($second)->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $shop->slug, 'q' => 'Avenida del Sol 42'])->assertTooManyRequests();

        $this->assertSame(1, Http::recorded()->count());
    }

    public function test_unavailable_provider_returns_manual_fallback_without_mutating_location(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([], 503)]);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create(['street_address' => 'Dirección capturada']);

        $response = $this->actingAs($admin)->postJson(route('barbershop.location.search', absolute: false), ['barbershop_slug' => $shop->slug, 'q' => 'Calle Central 12']);

        $response->assertServiceUnavailable()->assertJsonPath('message', 'No se pudo consultar el servicio. Puedes completar la ubicación manualmente.');
        $this->assertDatabaseHas('barbershops', ['id' => $shop->id, 'street_address' => 'Dirección capturada']);
    }

    public function test_malformed_provider_payload_returns_manual_fallback(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response(['unexpected' => 'shape'])]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->postJson(route('barbershop.location.search', absolute: false), ['q' => 'Calle Central 12'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'No se pudo consultar el servicio. Puedes completar la ubicación manualmente.');
    }

    public function test_provider_connection_failure_returns_manual_fallback(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::failedConnection()]);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->postJson(route('barbershop.location.search', absolute: false), ['q' => 'Calle Central 12'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'No se pudo consultar el servicio. Puedes completar la ubicación manualmente.');
    }

    public function test_search_unavailable_when_cache_store_fails_closed_without_provider_request(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([])]);
        config(['services.nominatim.cache_store' => 'missing-store']);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->postJson(route('barbershop.location.search', absolute: false), ['q' => 'Calle Central 12'])
            ->assertServiceUnavailable();

        Http::assertNothingSent();
    }

    public function test_global_lock_contention_returns_retryable_response_without_provider_request(): void
    {
        Http::preventStrayRequests();
        Http::fake(['nominatim.test/*' => Http::response([])]);
        $lock = Cache::store('array')->lock('nominatim:global-request-lock', 15);
        $this->assertTrue($lock->get());
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->postJson(route('barbershop.location.search', absolute: false), ['q' => 'Calle Central 12'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '2');

        Http::assertNothingSent();
        $lock->release();
    }
}
