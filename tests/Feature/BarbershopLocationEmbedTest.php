<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\GoogleMapsEmbed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BarbershopLocationEmbedTest extends TestCase
{
    use RefreshDatabase;

    private const EMBED_URL = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d123';

    public function test_platform_admin_can_store_only_the_source_from_a_pasted_iframe(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $iframe = '<iframe'."\n\t".'src="'.self::EMBED_URL.'"'."\n\t".'width="600" height="450" style="border:0" onclick="alert(1)" srcdoc="<script>alert(2)</script>" allowfullscreen></iframe>';

        $this->actingAs($admin)
            ->post(route('platform.barbershops.store', absolute: false), [
                'name' => 'Barbería Norte',
                'slug' => 'barberia-norte',
                'timezone' => 'America/Mexico_City',
                'owner_email' => $owner->email,
                'google_maps_embed_url' => $iframe,
            ])
            ->assertRedirect(route('platform.barbershops.index', absolute: false));

        $shop = Barbershop::query()->where('slug', 'barberia-norte')->firstOrFail();
        $this->assertSame(self::EMBED_URL, $shop->google_maps_embed_url);

        $this->put(route('platform.barbershops.update', $shop, false), [
            'name' => $shop->name,
            'slug' => $shop->slug,
            'timezone' => $shop->timezone,
            'status' => $shop->status,
            'google_maps_embed_url' => 'https://www.google.com/maps/embed?pb=platform-update',
        ])->assertRedirect(route('platform.barbershops.index', absolute: false));
        $this->assertSame('https://www.google.com/maps/embed?pb=platform-update', $shop->fresh()->google_maps_embed_url);

        $this->assertDatabaseHas('memberships', [
            'user_id' => $owner->id,
            'barbershop_id' => $shop->id,
            'role' => Membership::ROLE_OWNER,
        ]);
    }

    public function test_active_owner_can_store_direct_embed_url_and_omitted_or_blank_values_are_distinct(): void
    {
        $shop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create([
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $branding = ['brand_primary_color' => '#123456', 'brand_accent_color' => '#654321'];

        $this->actingAs($owner)
            ->put(route('tenant.branding.update', $shop, false), $branding + ['google_maps_embed_url' => self::EMBED_URL])
            ->assertRedirect(route('tenant.branding.edit', $shop, false));
        $this->assertSame(self::EMBED_URL, $shop->fresh()->google_maps_embed_url);

        $this->put(route('tenant.branding.update', $shop, false), $branding)
            ->assertRedirect(route('tenant.branding.edit', $shop, false));
        $this->assertSame(self::EMBED_URL, $shop->fresh()->google_maps_embed_url);

        $this->put(route('tenant.branding.update', $shop, false), $branding + ['google_maps_embed_url' => ''])
            ->assertRedirect(route('tenant.branding.edit', $shop, false));
        $this->assertNull($shop->fresh()->google_maps_embed_url);
    }

    public function test_invalid_embed_urls_are_rejected_without_changing_barbershops_or_memberships(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $existing = Barbershop::factory()->create(['city' => 'Guadalajara']);
        $beforeShops = Barbershop::query()->count();
        $beforeMemberships = Membership::query()->count();

        foreach ([
            'http://www.google.com/maps/embed?pb=abc',
            'https://google.com/maps/embed?pb=abc',
            'https://www.google.com.evil.test/maps/embed?pb=abc',
            'https://user@www.google.com/maps/embed?pb=abc',
            'https://www.google.com:443/maps/embed?pb=abc',
            'https://www.google.com/maps/embed/v1/place?pb=abc',
            'https://www.google.com/maps?pb=abc',
            'https://www.google.com/maps/embed?pb=',
            '//www.google.com/maps/embed?pb=abc',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><iframe src="'.self::EMBED_URL.'"></iframe>',
            str_repeat('x', 10001),
        ] as $invalidEmbed) {
            $this->actingAs($admin)
                ->from(route('platform.barbershops.create', absolute: false))
                ->post(route('platform.barbershops.store', absolute: false), [
                    'name' => 'Nueva',
                    'slug' => 'nueva',
                    'timezone' => 'America/Bogota',
                    'owner_email' => $owner->email,
                    'google_maps_embed_url' => $invalidEmbed,
                ])
                ->assertRedirect(route('platform.barbershops.create', absolute: false))
                ->assertSessionHasErrors([
                    'google_maps_embed_url' => GoogleMapsEmbed::VALIDATION_MESSAGE,
                ]);
        }

        $this->assertSame($beforeShops, Barbershop::query()->count());
        $this->assertSame($beforeMemberships, Membership::query()->count());
        $this->assertSame('Guadalajara', $existing->fresh()->city);
    }

    public function test_location_forms_show_copy_instructions_without_google_sdk_configuration(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get(route('platform.barbershops.create', absolute: false))
            ->assertOk()
            ->assertSee('<a href="https://www.google.com/maps" target="_blank" rel="noopener noreferrer">Abrir Google Maps</a>', false)
            ->assertSee('Compartir')
            ->assertSee('Insertar un mapa')
            ->assertSee('Copiar HTML')
            ->assertSee('name="google_maps_embed_url"', false)
            ->assertDontSee('data-api-key=')
            ->assertDontSee('location-search-widget')
            ->assertDontSee('maps.googleapis.com');
    }

    public function test_booking_summary_and_confirmation_render_embed_without_coordinates_and_escape_address(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        $shop = Barbershop::factory()->create([
            'country_code' => 'MX',
            'city' => 'Guadalajara',
            'street_address' => 'Av. Vallarta <script>alert(1)</script>',
            'google_maps_embed_url' => self::EMBED_URL,
        ]);
        $service = Service::factory()->for($shop)->create(['duration_minutes' => 30]);
        $barber = Barber::factory()->for($shop)->create();
        BusinessHour::factory()->for($shop)->create(['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '11:00']);
        BarberAvailability::factory()->for($barber)->create(['weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $booking = $this->get(route('public.booking.create', $shop))->assertOk();
        $this->assertRenderedSafeEmbed($booking->getContent());

        $summary = $this->post(route('public.booking.summary', $shop), [
            'service_id' => $service->id,
            'slot' => $barber->id.'|2026-10-05T10:00',
            'name' => $user->name,
            'email' => $user->email,
        ])->assertOk();
        $this->assertRenderedSafeEmbed($summary->getContent());
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());

        $confirmation = $this->post(route('public.booking.store', $shop), ['draft_id' => $summary->viewData('draft_id')])->assertOk();
        $this->assertRenderedSafeEmbed($confirmation->getContent());
    }

    public function test_corrupt_stored_embed_is_not_rendered_and_coordinates_remain_legacy_fallback(): void
    {
        $shop = Barbershop::factory()->create([
            'google_maps_embed_url' => self::EMBED_URL,
            'latitude' => '20.6736',
            'longitude' => '-103.344',
        ]);
        DB::table('barbershops')->where('id', $shop->id)->update([
            'google_maps_embed_url' => 'javascript:alert(1)',
        ]);

        $html = $this->get(route('public.booking.create', $shop))->assertOk()->getContent();
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringNotContainsString('/maps/embed?pb=', $html);
        $this->assertStringContainsString('maps.google.com/maps?q=20.6736,-103.344', $html);

        DB::table('barbershops')->where('id', $shop->id)->update(['latitude' => null, 'longitude' => null]);
        $withoutCoordinates = $this->get(route('public.booking.create', $shop))->assertOk()->getContent();
        $this->assertStringNotContainsString('<iframe', $withoutCoordinates);
    }

    public function test_additive_embed_migration_preserves_a_preexisting_row(): void
    {
        $connectionName = 'embed_migration_probe';
        config(['database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge($connectionName);
        $originalDefault = config('database.default');
        config(['database.default' => $connectionName]);

        try {
            Schema::create('barbershops', function ($table): void {
                $table->id();
                $table->string('name');
                $table->string('city')->nullable();
            });
            DB::table('barbershops')->insert(['id' => 41, 'name' => 'Barbería existente', 'city' => 'Monterrey']);

            $migration = require database_path('migrations/2026_11_05_083918_add_google_maps_embed_url_to_barbershops_table.php');
            $migration->up();

            $this->assertSame(1, DB::table('barbershops')->count());
            $this->assertSame('Barbería existente', DB::table('barbershops')->where('id', 41)->value('name'));
            $this->assertSame('Monterrey', DB::table('barbershops')->where('id', 41)->value('city'));
            $this->assertNull(DB::table('barbershops')->where('id', 41)->value('google_maps_embed_url'));
        } finally {
            config(['database.default' => $originalDefault]);
            DB::purge($connectionName);
        }
    }

    public function test_staff_cannot_change_embed_through_owner_branding_route(): void
    {
        $shop = Barbershop::factory()->create(['google_maps_embed_url' => self::EMBED_URL]);
        $staff = User::factory()->create();
        Membership::factory()->for($staff)->for($shop)->create([
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->actingAs($staff)
            ->put(route('tenant.branding.update', $shop, false), [
                'brand_primary_color' => '#123456',
                'brand_accent_color' => '#654321',
                'google_maps_embed_url' => 'https://www.google.com/maps/embed?pb=attacker',
            ])
            ->assertForbidden();

        $this->assertSame(self::EMBED_URL, $shop->fresh()->google_maps_embed_url);
    }

    private function assertRenderedSafeEmbed(string $html): void
    {
        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertStringContainsString('src="'.self::EMBED_URL.'"', $html);
        $this->assertStringContainsString('title="Mapa de Google Maps"', $html);
        $this->assertStringContainsString('width="600" height="350"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('referrerpolicy="no-referrer"', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('srcdoc=', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
