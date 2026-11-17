<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_landing_content_and_authentication_links(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Lleva tu barbería al')
            ->assertSee('siguiente nivel')
            ->assertSee('Agenda inteligente')
            ->assertSee('Cómo funciona')
            ->assertSee('data-landing-menu-auth', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false);
    }

    public function test_authenticated_visitors_keep_the_dashboard_action(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('Ir al panel')
            ->assertSee('href="'.url('/dashboard').'"', false)
            ->assertDontSee('href="'.route('login').'"', false);
    }
}
