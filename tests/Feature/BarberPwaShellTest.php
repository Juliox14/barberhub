<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarberPwaShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_tenant_pages_render_install_controls_and_offline_status(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $response = $this->actingAs($barber)->get(route('tenant.dashboard', $barbershop, false));

        $response->assertOk()
            ->assertSee('name="theme-color"', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('Agregar BarberHub a la pantalla de inicio')
            ->assertSee('aria-live="polite"', false);

        $this->assertMatchesRegularExpression(
            '~(?:/resources/js/pwa\.js|/build/assets/pwa-[^"\s]+\.js)~',
            $response->getContent(),
        );
    }

    public function test_public_marketing_page_does_not_render_tenant_pwa_registration(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertDontSee('service-worker.js', false);

        $this->assertDoesNotMatchRegularExpression(
            '~(?:/resources/js/pwa\.js|/build/assets/pwa-[^"\s]+\.js)~',
            $response->getContent(),
        );
    }
}
