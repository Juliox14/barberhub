<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use App\Support\TenantBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_safe_brand_defaults_and_update_tenant_branding(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);

        $this->actingAs($owner)
            ->get(route('tenant.branding.edit', $barbershop, false))
            ->assertOk()
            ->assertSee('#496C35')
            ->assertSee('#B8885A')
            ->assertSee('background-color: #F8F7F4; color: #1F2937', false);

        $this->actingAs($owner)
            ->put(route('tenant.branding.update', $barbershop, false), [
                'brand_primary_color' => '#123456',
                'brand_accent_color' => '#F5F5F5',
                'brand_description' => 'Barbería de confianza',
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'brand_primary_color' => '#123456',
            'brand_accent_color' => '#F5F5F5',
            'brand_description' => 'Barbería de confianza',
        ]);
    }

    public function test_branding_editor_escapes_description_and_rejects_noncanonical_colors(): void
    {
        $barbershop = Barbershop::factory()->create([
            'brand_primary_color' => '#496C35',
            'brand_accent_color' => '#B8885A',
            'brand_description' => '<script>alert("x")</script>',
        ]);
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);

        $this->actingAs($owner)
            ->get(route('tenant.branding.edit', $barbershop, false))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("x")</script>', false);

        $this->actingAs($owner)
            ->from(route('tenant.branding.edit', $barbershop, false))
            ->put(route('tenant.branding.update', $barbershop, false), [
                'brand_primary_color' => 'red; background:url(javascript:alert(1))',
                'brand_accent_color' => '#B8885A',
                'brand_description' => '<script>alert("x")</script>',
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false))
            ->assertSessionHasErrors(['brand_primary_color' => 'El color principal debe usar el formato #RRGGBB.']);

        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'brand_primary_color' => '#496C35',
            'brand_description' => '<script>alert("x")</script>',
        ]);
    }

    public function test_only_active_owner_membership_can_change_brand_settings(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        Storage::fake('local');
        $client = User::factory()->create();
        Customer::factory()->for($barbershop)->create(['user_id' => $client->id]);
        $users = [
            $client,
            $this->createMember($barbershop, Membership::ROLE_ADMIN),
            $this->createMember($barbershop, Membership::ROLE_BARBER),
            $this->createMember($otherBarbershop, Membership::ROLE_OWNER),
            $this->createMember($barbershop, Membership::ROLE_OWNER, Membership::STATUS_INACTIVE),
            User::factory()->create(['is_platform_admin' => true]),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->put(route('tenant.branding.update', $barbershop, false), [
                    'brand_primary_color' => '#123456',
                    'brand_accent_color' => '#654321',
                    'brand_description' => 'No autorizado',
                    'logo' => $this->pngUpload('unauthorized.png'),
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('barbershops', [
            'id' => $barbershop->id,
            'brand_primary_color' => '#123456',
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_guest_cannot_change_brand_settings(): void
    {
        $barbershop = Barbershop::factory()->create();

        $this->put(route('tenant.branding.update', $barbershop, false), [
            'brand_primary_color' => '#123456',
            'brand_accent_color' => '#654321',
        ])->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'brand_primary_color' => null,
        ]);
    }

    public function test_owner_uploads_private_raster_assets_and_public_active_shop_serves_them_safely(): void
    {
        Storage::fake('local');
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);

        $this->actingAs($owner)
            ->post(route('tenant.branding.update', $barbershop, false), [
                '_method' => 'PUT',
                'brand_primary_color' => '#496C35',
                'brand_accent_color' => '#B8885A',
                'logo' => $this->pngUpload('logo.png'),
                'cover' => $this->pngUpload('cover.png'),
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false));

        $barbershop->refresh();
        $this->assertNotNull($barbershop->brand_logo_path);
        $this->assertNotNull($barbershop->brand_cover_path);
        $this->assertStringStartsWith('tenant-branding/'.$barbershop->id.'/', $barbershop->brand_logo_path);
        $this->assertStringStartsWith('tenant-branding/'.$barbershop->id.'/', $barbershop->brand_cover_path);
        $this->assertStringNotContainsString('logo.png', $barbershop->brand_logo_path);
        Storage::disk('local')->assertExists($barbershop->brand_logo_path);
        Storage::disk('local')->assertExists($barbershop->brand_cover_path);

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();

        $logoResponse = $this->get(route('tenant.branding.asset', [$barbershop, 'logo'], false))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($this->pngContent(), $logoResponse->getContent());

        $coverResponse = $this->get(route('tenant.branding.asset', [$barbershop, 'cover'], false))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($this->pngContent(), $coverResponse->getContent());

        $otherShop = Barbershop::factory()->create();
        $this->get(route('tenant.branding.asset', [$otherShop, 'logo'], false))->assertNotFound();
        $this->get('/barbershops/nonexistent-shop/brand/logo')->assertNotFound();
        $this->get(route('tenant.branding.asset', [$barbershop, 'unknown'], false))->assertNotFound();

        $inactiveShop = Barbershop::factory()->create(['status' => Barbershop::STATUS_INACTIVE]);
        $this->get(route('tenant.branding.asset', [$inactiveShop, 'logo'], false))->assertNotFound();
    }

    public function test_saving_brand_details_without_uploads_retains_both_asset_paths_and_bytes(): void
    {
        Storage::fake('local');
        [$barbershop, $owner] = $this->createBarbershopWithAssets();
        $logoPath = $barbershop->brand_logo_path;
        $coverPath = $barbershop->brand_cover_path;
        $logoBytes = Storage::disk('local')->get($logoPath);
        $coverBytes = Storage::disk('local')->get($coverPath);

        $this->actingAs($owner)
            ->put(route('tenant.branding.update', $barbershop, false), [
                'brand_primary_color' => '#123456',
                'brand_accent_color' => '#654321',
                'brand_description' => 'Se conservan los recursos',
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false));

        $barbershop->refresh();
        $this->assertSame($logoPath, $barbershop->brand_logo_path);
        $this->assertSame($coverPath, $barbershop->brand_cover_path);
        $this->assertSame($logoBytes, Storage::disk('local')->get($logoPath));
        $this->assertSame($coverBytes, Storage::disk('local')->get($coverPath));
    }

    public function test_replacing_logo_removes_only_the_old_logo_and_retains_cover_bytes(): void
    {
        Storage::fake('local');
        [$barbershop, $owner] = $this->createBarbershopWithAssets();
        $oldLogoPath = $barbershop->brand_logo_path;
        $coverPath = $barbershop->brand_cover_path;
        $coverBytes = Storage::disk('local')->get($coverPath);

        $this->actingAs($owner)
            ->post(route('tenant.branding.update', $barbershop, false), [
                '_method' => 'PUT',
                'brand_primary_color' => '#123456',
                'brand_accent_color' => '#654321',
                'logo' => $this->pngUpload('replacement-logo.png'),
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false));

        $barbershop->refresh();
        $this->assertNotSame($oldLogoPath, $barbershop->brand_logo_path);
        Storage::disk('local')->assertMissing($oldLogoPath);
        Storage::disk('local')->assertExists($barbershop->brand_logo_path);
        $this->assertSame($coverPath, $barbershop->brand_cover_path);
        $this->assertSame($coverBytes, Storage::disk('local')->get($coverPath));
    }

    public function test_replacing_cover_removes_only_the_old_cover_and_retains_logo_bytes(): void
    {
        Storage::fake('local');
        [$barbershop, $owner] = $this->createBarbershopWithAssets();
        $logoPath = $barbershop->brand_logo_path;
        $logoBytes = Storage::disk('local')->get($logoPath);
        $oldCoverPath = $barbershop->brand_cover_path;

        $this->actingAs($owner)
            ->post(route('tenant.branding.update', $barbershop, false), [
                '_method' => 'PUT',
                'brand_primary_color' => '#123456',
                'brand_accent_color' => '#654321',
                'cover' => $this->pngUpload('replacement-cover.png'),
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false));

        $barbershop->refresh();
        $this->assertNotSame($oldCoverPath, $barbershop->brand_cover_path);
        Storage::disk('local')->assertMissing($oldCoverPath);
        Storage::disk('local')->assertExists($barbershop->brand_cover_path);
        $this->assertSame($logoPath, $barbershop->brand_logo_path);
        $this->assertSame($logoBytes, Storage::disk('local')->get($logoPath));
    }

    public function test_invalid_uploads_leave_existing_branding_and_files_unchanged(): void
    {
        Storage::fake('local');
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);
        $this->actingAs($owner)->post(route('tenant.branding.update', $barbershop, false), [
            '_method' => 'PUT',
            'brand_primary_color' => '#123456',
            'brand_accent_color' => '#654321',
            'logo' => $this->pngUpload('original.png'),
        ]);
        $barbershop->refresh();
        $originalPath = $barbershop->brand_logo_path;
        $this->assertNotNull($originalPath);
        $storedFiles = Storage::disk('local')->allFiles();
        sort($storedFiles);

        foreach ([
            'svg' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'disguised-extension' => $this->pngUpload('disguised.txt'),
            'disguised-content' => UploadedFile::fake()->createWithContent('disguised.png', '<script>alert(1)</script>'),
            'oversize' => UploadedFile::fake()->createWithContent('large.png', $this->pngContent().str_repeat('x', 2 * 1024 * 1024 + 1)),
        ] as $file) {
            $this->actingAs($owner)
                ->from(route('tenant.branding.edit', $barbershop, false))
                ->post(route('tenant.branding.update', $barbershop, false), [
                    '_method' => 'PUT',
                    'brand_primary_color' => '#ABCDEF',
                    'brand_accent_color' => '#B8885A',
                    'logo' => $file,
                ])
                ->assertRedirect(route('tenant.branding.edit', $barbershop, false))
                ->assertSessionHasErrors(['logo' => 'El logo debe ser una imagen PNG, JPEG o WebP válida de máximo 2 MB y 4096 × 4096 px.']);

            $barbershop->refresh();
            $this->assertSame($originalPath, $barbershop->brand_logo_path);
            $this->assertSame('#123456', $barbershop->brand_primary_color);
            Storage::disk('local')->assertExists($originalPath);
            $currentFiles = Storage::disk('local')->allFiles();
            sort($currentFiles);
            $this->assertSame($storedFiles, $currentFiles);
        }
    }

    public function test_description_is_limited_to_one_thousand_characters(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);

        $this->actingAs($owner)
            ->from(route('tenant.branding.edit', $barbershop, false))
            ->put(route('tenant.branding.update', $barbershop, false), [
                'brand_primary_color' => '#496C35',
                'brand_accent_color' => '#B8885A',
                'brand_description' => str_repeat('x', 1001),
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false))
            ->assertSessionHasErrors(['brand_description' => 'La descripción no puede superar los 1000 caracteres.']);

        $this->assertDatabaseHas('barbershops', [
            'id' => $barbershop->id,
            'brand_description' => null,
        ]);
    }

    public function test_resolved_branding_uses_readable_foregrounds_for_light_and_dark_colors(): void
    {
        $defaults = TenantBranding::resolve(Barbershop::factory()->make());
        $light = TenantBranding::resolve(Barbershop::factory()->make([
            'brand_primary_color' => '#F5F5F5',
            'brand_accent_color' => '#FFFFFF',
        ]));
        $dark = TenantBranding::resolve(Barbershop::factory()->make([
            'brand_primary_color' => '#123456',
            'brand_accent_color' => '#000000',
        ]));

        $this->assertSame('#F8F7F4', $defaults['surface']);
        $this->assertSame('#496C35', $defaults['primary']);
        $this->assertSame('#B8885A', $defaults['accent']);
        $this->assertSame('#FFFFFF', $defaults['primary_foreground']);
        $this->assertSame('#000000', $defaults['accent_foreground']);
        $this->assertSame('#000000', $light['primary_foreground']);
        $this->assertSame('#000000', $light['accent_foreground']);
        $this->assertSame('#FFFFFF', $dark['primary_foreground']);
        $this->assertSame('#FFFFFF', $dark['accent_foreground']);
    }

    /**
     * @return array{Barbershop, User}
     */
    private function createBarbershopWithAssets(): array
    {
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);

        $this->actingAs($owner)
            ->post(route('tenant.branding.update', $barbershop, false), [
                '_method' => 'PUT',
                'brand_primary_color' => '#496C35',
                'brand_accent_color' => '#B8885A',
                'logo' => $this->pngUpload('initial-logo.png'),
                'cover' => $this->pngUpload('initial-cover.png'),
            ])
            ->assertRedirect(route('tenant.branding.edit', $barbershop, false));

        return [$barbershop->refresh(), $owner];
    }

    private function createMember(Barbershop $barbershop, string $role, string $status = Membership::STATUS_ACTIVE): User
    {
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create([
            'role' => $role,
            'status' => $status,
        ]);

        return $user;
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngContent());
    }

    private function pngContent(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true);
    }
}
