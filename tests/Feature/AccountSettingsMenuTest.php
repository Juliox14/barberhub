<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountSettingsMenuTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function accountShells(): array
    {
        return [
            'owner' => [Membership::ROLE_OWNER],
            'barber' => [Membership::ROLE_BARBER],
            'platform administrator' => ['platform'],
            'account without a tenant shell' => ['fallback'],
        ];
    }

    #[DataProvider('accountShells')]
    public function test_security_settings_are_in_the_header_menu_next_to_logout(string $shell): void
    {
        $user = User::factory()->create(['is_platform_admin' => $shell === 'platform']);
        if (in_array($shell, [Membership::ROLE_OWNER, Membership::ROLE_BARBER], true)) {
            $barbershop = Barbershop::factory()->create();
            Membership::factory()->for($user)->for($barbershop)->create([
                'role' => $shell,
                'status' => Membership::STATUS_ACTIVE,
            ]);
        }

        $response = $this->actingAs($user)->get(route('two-factor.settings'));
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('data-account-settings-menu', false)
            ->assertSee('aria-label="Abrir menú de configuración de cuenta"', false)
            ->assertSee('images/icons/account-settings.png', false)
            ->assertSee('Seguridad de mi cuenta')
            ->assertSee('Iconos de Icons8')
            ->assertSee('https://icons8.com/icon/364/settings', false)
            ->assertDontSee('Icono:');
        $this->assertSame(1, substr_count($html, 'Seguridad de mi cuenta'));
        $menuStart = strpos($html, 'data-account-settings-menu');
        $menuEnd = strpos($html, '</details>', $menuStart);
        $this->assertStringNotContainsString('Icons8', substr($html, $menuStart, $menuEnd - $menuStart));
        $this->assertSame(1, substr_count($html, 'Iconos de Icons8'));
        $this->assertLessThan(strpos($html, 'action="http://localhost/logout"'), $menuStart);
    }
}
