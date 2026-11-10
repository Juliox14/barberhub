<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_dashboards_do_not_show_upcoming_modules(): void
    {
        foreach ([Membership::ROLE_OWNER, Membership::ROLE_ADMIN, Membership::ROLE_BARBER] as $role) {
            $barbershop = Barbershop::factory()->create();
            $user = User::factory()->create();
            Membership::factory()->for($user)->for($barbershop)->create(['role' => $role]);

            $this->actingAs($user)->get(route('tenant.dashboard', $barbershop, false))
                ->assertOk()
                ->assertDontSee('Próximamente')
                ->assertDontSee('Inventario')
                ->assertDontSee('bh-sidebar__upcoming', false)
                ->assertSee('Agenda');
        }
    }

    public function test_settings_menu_links_directly_to_the_existing_password_form_in_every_account_shell(): void
    {
        foreach ([Membership::ROLE_OWNER, Membership::ROLE_ADMIN, Membership::ROLE_BARBER, 'platform', 'fallback'] as $shell) {
            $user = User::factory()->create(['is_platform_admin' => $shell === 'platform']);
            if (in_array($shell, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN, Membership::ROLE_BARBER], true)) {
                $barbershop = Barbershop::factory()->create();
                Membership::factory()->for($user)->for($barbershop)->create(['role' => $shell]);
            }

            $response = $this->actingAs($user)->get(route('two-factor.settings'));
            $response->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML('<meta charset="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $links = $xpath->query('//details[@data-account-settings-menu]//a');
            $menuLinks = [];
            foreach ($links as $link) {
                $menuLinks[trim($link->textContent)] = $link->getAttribute('href');
            }

            $this->assertSame(route('profile.edit').'#change-password', $menuLinks['Cambiar contraseña'] ?? null);
            $this->assertSame(route('two-factor.settings'), $menuLinks['Seguridad de mi cuenta'] ?? null);

            $this->get(route('profile.edit'))
                ->assertOk()
                ->assertSee('id="change-password"', false)
                ->assertSee('action="'.route('password.update').'"', false)
                ->assertSee('name="current_password"', false)
                ->assertSee('name="password_confirmation"', false);
        }
    }
}
