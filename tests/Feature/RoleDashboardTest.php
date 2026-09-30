<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Panel de administración')
            ->assertSee('Rol: Administrador');
    }

    public function test_barber_can_access_barber_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'barber']);

        $this->actingAs($user)
            ->get('/barber/dashboard')
            ->assertOk()
            ->assertSee('Panel de barbero')
            ->assertSee('Rol: Barbero');
    }

    public function test_client_can_access_client_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user)
            ->get('/client/dashboard')
            ->assertOk()
            ->assertSee('Panel de cliente')
            ->assertSee('Rol: Cliente');
    }

    public function test_user_cannot_access_dashboard_for_another_role(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_redirects_authenticated_users_to_their_role_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barber = User::factory()->create(['role' => 'barber']);
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard', absolute: false));

        $this->actingAs($barber)
            ->get('/dashboard')
            ->assertRedirect(route('barber.dashboard', absolute: false));

        $this->actingAs($client)
            ->get('/dashboard')
            ->assertRedirect(route('client.dashboard', absolute: false));
    }

    public function test_login_redirects_to_intended_role_dashboard_when_present(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get('/admin/dashboard')
            ->assertRedirect(route('login', absolute: false));

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_login_redirects_users_to_their_role_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $barber = User::factory()->create(['role' => 'barber']);
        $client = User::factory()->create(['role' => 'client']);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard', absolute: false));

        $this->post('/logout');

        $this->post('/login', [
            'email' => $barber->email,
            'password' => 'password',
        ])->assertRedirect(route('barber.dashboard', absolute: false));

        $this->post('/logout');

        $this->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route('client.dashboard', absolute: false));
    }

    public function test_registration_redirects_new_clients_to_the_client_dashboard(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('client.dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame('client', auth()->user()->role);
    }
}
