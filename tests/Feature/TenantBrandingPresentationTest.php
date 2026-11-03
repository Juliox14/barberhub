<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantBrandingPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_owner_sees_branding_navigation_in_the_tenant_shell(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);
        $admin = $this->createMember($barbershop, Membership::ROLE_ADMIN);
        $barber = $this->createMember($barbershop, Membership::ROLE_BARBER);
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($owner)
            ->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee(route('tenant.branding.edit', $barbershop), false)
            ->assertSee('Marca')
            ->assertDontSee(route('tenant.branding.asset', [$barbershop, 'logo']), false);

        foreach ([$admin, $barber, $platformAdmin] as $user) {
            $this->actingAs($user)
                ->get(route('tenant.dashboard', $barbershop, false))
                ->assertOk()
                ->assertDontSee(route('tenant.branding.edit', $barbershop), false);
        }
    }

    public function test_tenant_shell_shows_business_identity_without_replacing_barberhub_identity(): void
    {
        Storage::fake('local');
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Aurora']);
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);
        $this->actingAs($owner)->post(route('tenant.branding.update', $barbershop, false), [
            '_method' => 'PUT',
            'brand_primary_color' => '#496C35',
            'brand_accent_color' => '#B8885A',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true)),
        ]);

        $response = $this->actingAs($owner)->get(route('tenant.dashboard', $barbershop, false));

        $response->assertOk()
            ->assertSee('Barbería Aurora')
            ->assertSee(route('tenant.branding.asset', [$barbershop, 'logo']), false)
            ->assertSee('BarberHub');
        $this->assertStringContainsString('images/logo.png', $response->getContent());
    }

    public function test_editor_renders_live_customer_brand_preview_with_escaped_shop_content_and_safe_defaults(): void
    {
        Storage::fake('local');
        $barbershop = Barbershop::factory()->create([
            'name' => 'Estudio <script>alert(1)</script>',
            'brand_description' => '<img src=x onerror=alert(1)> Barbería de barrio',
        ]);
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);
        $this->actingAs($owner)->post(route('tenant.branding.update', $barbershop, false), [
            '_method' => 'PUT',
            'brand_primary_color' => '#496C35',
            'brand_accent_color' => '#B8885A',
            'brand_description' => '<img src=x onerror=alert(1)> Barbería de barrio',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', $image),
            'cover' => UploadedFile::fake()->createWithContent('cover.png', $image),
        ]);

        $response = $this->actingAs($owner)->get(route('tenant.branding.edit', $barbershop, false));

        $response->assertOk()
            ->assertSee('Vista previa para clientes')
            ->assertSee('Restablecer colores')
            ->assertSee('Descartar cambios')
            ->assertSee('Vista móvil')
            ->assertSee('Vista de escritorio')
            ->assertSee('La vista previa no es todavía una página pública activa')
            ->assertSee('#496C35')
            ->assertSee('#B8885A')
            ->assertSee('#F8F7F4')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('x-text=', false)
            ->assertSee('brand\\/logo', false)
            ->assertSee('brand\\/cover', false)
            ->assertSee('multipart/form-data', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    private function createMember(Barbershop $barbershop, string $role): User
    {
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create(['role' => $role]);

        return $user;
    }
}
