<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FormAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_forms_show_validation_errors_only_in_the_alert(): void
    {
        $this->from('/login')->post('/login', [])->assertSessionHasErrors(['email', 'password']);

        $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/login');

        $response->assertSee('Revisá los datos del formulario')
            ->assertSee('role="alert"', false)
            ->assertSee('El campo correo electrónico es obligatorio.')
            ->assertSee('El campo contraseña es obligatorio.')
            ->assertDontSee('validation.')
            ->assertSee('name="email"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'El campo correo electrónico es obligatorio.'));
        $this->assertSame(1, substr_count($response->getContent(), 'El campo contraseña es obligatorio.'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_profile_forms_include_named_error_bags_in_the_summary(): void
    {
        $user = User::factory()->create();

        $originalPassword = $user->password;

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/profile');

        $response->assertSee('Revisá los datos del formulario')
            ->assertSee('La contraseña actual es incorrecta.')
            ->assertDontSee('validation.');

        $this->assertSame(1, substr_count($response->getContent(), 'La contraseña actual es incorrecta.'));
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_success_status_is_translated_and_displayed_only_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withSession([
            'status' => 'profile-updated',
        ])->get('/profile');

        $response->assertSee('role="status"', false)
            ->assertSee('Tu perfil se actualizó correctamente.')
            ->assertDontSee('profile-updated');

        $this->assertSame(1, substr_count($response->getContent(), 'Tu perfil se actualizó correctamente.'));
    }

    public function test_guest_success_messages_are_escaped_and_displayed_once(): void
    {
        $message = '<script>alert("test")</script>';

        $response = $this->withSession(['status' => $message])->get('/forgot-password');

        $response->assertSee('role="status"', false)
            ->assertSee($message)
            ->assertDontSee($message, false);

        $this->assertSame(1, substr_count($response->getContent(), e($message)));
    }

    public function test_tenant_forms_display_success_messages_only_once(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $message = 'Servicio creado correctamente.';

        $response = $this->actingAs($owner)->withSession(['status' => $message])
            ->get(route('tenant.services.index', $barbershop, false));

        $response->assertSee('role="status"', false)->assertSee($message);

        $this->assertSame(1, substr_count($response->getContent(), $message));
    }

    public function test_platform_forms_display_validation_errors(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), [])
            ->assertSessionHasErrors(['name']);

        $response = $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('platform.barbershops.create', absolute: false));

        $response->assertSee('Revisá los datos del formulario')->assertSee('role="alert"', false)
            ->assertSee('El campo correo de la persona propietaria es obligatorio.')
            ->assertDontSee('validation.');

        $this->assertSame(1, substr_count($response->getContent(), 'El campo correo de la persona propietaria es obligatorio.'));
        $this->assertDatabaseCount('barbershops', 0);
    }

    public function test_invalid_login_credentials_show_a_clear_message_only_in_the_alert(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $message = 'El correo electrónico o la contraseña son incorrectos.';

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors(['email' => $message]);

        $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/login');

        $response->assertSee('role="alert"', false)->assertSee($message)->assertDontSee('auth.failed');
        $this->assertSame(1, substr_count($response->getContent(), $message));
        $this->assertGuest();
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_login_rate_limit_shows_a_translated_wait_time(): void
    {
        $this->freezeTime();
        $email = 'rate-limited@example.com';
        $key = $email.'|127.0.0.1';
        RateLimiter::clear($key);

        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->post('/login', ['email' => $email, 'password' => 'wrong-password']);
            }

            $this->from('/login')->post('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertRedirect('/login')->assertSessionHasErrors([
                    'email' => 'Demasiados intentos de inicio de sesión. Volvé a intentarlo en 60 segundos.',
                ]);

            $response = $this->withCookie(config('session.cookie'), session()->getId())->get('/login');

            $response->assertSee('Demasiados intentos de inicio de sesión. Volvé a intentarlo en 60 segundos.')
                ->assertDontSee('auth.throttle');
            $this->assertSame(5, RateLimiter::attempts($key));
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
        } finally {
            RateLimiter::clear($key);
        }
    }

    public function test_tenant_validation_errors_are_displayed_only_in_the_alert(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $url = route('tenant.services.index', $barbershop, false);
        $message = 'El campo nombre es obligatorio.';

        $this->actingAs($owner)->from($url)
            ->post(route('tenant.services.store', $barbershop, false), [])
            ->assertRedirect($url)->assertSessionHasErrors(['name' => $message]);

        $response = $this->withCookie(config('session.cookie'), session()->getId())->get($url);

        $response->assertSee('role="alert"', false)->assertSee($message);
        $this->assertSame(1, substr_count($response->getContent(), $message));
        $this->assertDatabaseCount('services', 0);
    }

    public function test_forms_do_not_show_alerts_without_feedback(): void
    {
        $response = $this->get('/login');

        $response->assertDontSee('role="alert"', false)
            ->assertDontSee('role="status"', false)
            ->assertSee('name="email"', false);
    }
}
