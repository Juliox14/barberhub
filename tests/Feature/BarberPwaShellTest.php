<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\PushSubscription;
use App\Models\User;
use App\View\Components\PwaControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
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

    public function test_rendered_pwa_controls_include_independent_mobile_alerts(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $response = $this->actingAs($barber)->get(route('tenant.dashboard', $barbershop, false));

        $response->assertOk()
            ->assertSee('data-push-subscribed="false"', false)
            ->assertSee('data-pwa-install-alert', false)
            ->assertSee('data-push-alert', false)
            ->assertSee('aria-label="Cerrar aviso de instalación"', false)
            ->assertSee('aria-label="Cerrar aviso de notificaciones"', false);
    }

    public function test_pwa_component_confirms_subscription_for_its_current_account_and_session(): void
    {
        $barber = User::factory()->create();
        $session = new Store('barberhub', new ArraySessionHandler(120), 'current-browser');
        $session->start();
        $request = Request::create('/dashboard');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn (): User => $barber);
        PushSubscription::factory()->for($barber)->create([
            'session_hash' => hash('sha256', $session->getId()),
        ]);

        $component = new PwaControls($request);

        $this->assertTrue($component->pushSubscribed);
        $this->assertSame((string) $barber->getKey(), $component->accountId);
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
