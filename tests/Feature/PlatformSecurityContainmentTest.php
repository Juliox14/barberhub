<?php

namespace Tests\Feature;

use App\Events\PlatformSecurityAction;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\SingleDeviceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSecurityContainmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_revokes_owned_sessions_push_subscriptions_and_remember_cookie_with_a_reason(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $account = User::factory()->create([
            'active_session_id' => Str::random(64),
            'active_session_at' => now(),
        ]);
        $oldRememberToken = $account->getRememberToken();
        $rememberedUser = $account->fresh();
        $sessionId = Str::random(40);
        $this->app['db']->table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $account->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $subscription = PushSubscription::factory()->for($account)->create();
        Event::fake([PlatformSecurityAction::class]);

        $response = $this->actingAs($admin)->post(route('platform.security.sessions.revoke', $account, false), [
            'reason' => 'Se detectó una sesión no reconocida.',
        ]);

        $response->assertRedirect(route('platform.security.index', absolute: false));
        $account->refresh();
        $this->assertNull($account->active_session_id);
        $this->assertNull($account->active_session_at);
        $this->assertNotSame($oldRememberToken, $account->getRememberToken());
        $this->assertNull(app(SingleDeviceSession::class)->restoreRememberedSession($rememberedUser));
        $this->assertSame(1, $account->security_session_version);
        $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
        $this->assertModelMissing($subscription);
        Event::assertDispatched(PlatformSecurityAction::class, fn (PlatformSecurityAction $event): bool => $event->action === 'session_revoked'
            && $event->userId === $admin->id
            && $event->subjectUserId === $account->id
            && $event->reason === 'Se detectó una sesión no reconocida.'
            && $event->barbershopId === null
            && $event->module === null
            && $event->expiresAt === null
        );
    }

    public function test_non_platform_admin_cannot_revoke_sessions_and_missing_reason_is_rejected(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $user = User::factory()->create();
        $account = User::factory()->create(['active_session_id' => Str::random(64)]);

        $this->actingAs($user)
            ->post(route('platform.security.sessions.revoke', $account, false), ['reason' => 'Motivo válido'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('platform.security.index', absolute: false))
            ->post(route('platform.security.sessions.revoke', $account, false), ['reason' => ' '])
            ->assertSessionHasErrors('reason');

        $this->assertNotNull($account->fresh()->active_session_id);
        $this->assertSame(0, $account->fresh()->security_session_version);
    }

    public function test_suspension_is_shop_and_module_scoped_fixed_at_fifteen_minutes_and_early_liftable(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create();
        $otherShop = Barbershop::factory()->create();
        $member = User::factory()->create();
        $otherShopMember = User::factory()->create();
        Membership::factory()->for($member)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($otherShopMember)->for($otherShop)->create(['role' => Membership::ROLE_OWNER]);
        $this->travelTo('2026-01-01 12:00:00');
        Event::fake([PlatformSecurityAction::class]);

        $payload = ['module' => 'appointments', 'reason' => 'Revisión preventiva.'];
        $this->actingAs($admin)
            ->post(route('platform.security.modules.suspend', $barbershop, false), $payload)
            ->assertRedirect(route('platform.security.index', absolute: false));
        $this->assertDatabaseHas('module_containments', [
            'barbershop_id' => $barbershop->id,
            'module' => 'appointments',
            'reason' => 'Revisión preventiva.',
            'expires_at' => '2026-01-01 12:15:00',
        ]);

        $this->actingAs($member)->get(route('tenant.appointments.index', $barbershop, false))->assertForbidden();
        $this->actingAs($admin)->get(route('tenant.appointments.index', $barbershop, false))->assertForbidden();
        $this->actingAs($member)->post(route('tenant.appointments.store', $barbershop, false), [])->assertForbidden();
        $this->actingAs($admin)->post(route('tenant.appointments.store', $barbershop, false), [])->assertForbidden();
        $this->actingAs($member)->get(route('tenant.services.index', $barbershop, false))->assertOk();
        $this->actingAs($member)->get(route('tenant.dashboard', $barbershop, false))->assertOk();
        $this->actingAs($otherShopMember)->get(route('tenant.appointments.index', $otherShop, false))->assertOk();
        $this->actingAs($admin)->get(route('tenant.appointments.index', $otherShop, false))->assertOk();

        $this->travel(5)->minutes();
        $this->actingAs($admin)
            ->post(route('platform.security.modules.suspend', $barbershop, false), $payload)
            ->assertRedirect(route('platform.security.index', absolute: false));
        $this->assertDatabaseHas('module_containments', [
            'barbershop_id' => $barbershop->id,
            'module' => 'appointments',
            'reason' => 'Revisión preventiva.',
            'expires_at' => '2026-01-01 12:15:00',
        ]);
        $this->travel(9)->minutes();
        $this->actingAs($member)->get(route('tenant.appointments.index', $barbershop, false))->assertForbidden();
        $this->travel(1)->minutes();
        $this->actingAs($member)->get(route('tenant.appointments.index', $barbershop, false))->assertOk();
        $this->actingAs($admin)
            ->post(route('platform.security.modules.release', $barbershop, false), [
                'module' => 'appointments',
                'reason' => 'La vigencia terminó.',
            ])
            ->assertRedirect(route('platform.security.index', absolute: false));
        $this->assertDatabaseHas('module_containments', [
            'barbershop_id' => $barbershop->id,
            'module' => 'appointments',
            'released_at' => null,
            'release_reason' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('platform.security.modules.suspend', $barbershop, false), $payload)
            ->assertRedirect(route('platform.security.index', absolute: false));
        $this->actingAs($member)->get(route('tenant.appointments.index', $barbershop, false))->assertForbidden();
        $this->actingAs($admin)
            ->post(route('platform.security.modules.release', $barbershop, false), [
                'module' => 'appointments',
                'reason' => 'La revisión terminó.',
            ])
            ->assertRedirect(route('platform.security.index', absolute: false));
        $this->actingAs($member)->get(route('tenant.appointments.index', $barbershop, false))->assertOk();
        $this->assertDatabaseHas('module_containments', [
            'barbershop_id' => $barbershop->id,
            'module' => 'appointments',
            'released_at' => now()->toDateTimeString(),
            'release_reason' => 'La revisión terminó.',
        ]);

        Event::assertDispatchedTimes(PlatformSecurityAction::class, 3);
    }

    public function test_platform_admin_can_view_security_console_and_non_admin_cannot(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $user = User::factory()->create();
        Barbershop::factory()->create();

        $this->actingAs($admin)
            ->get(route('platform.security.index', absolute: false))
            ->assertOk()
            ->assertSee('Contención de seguridad')
            ->assertSee('Revocar sesiones de una cuenta')
            ->assertSee('Suspender 15 min');

        $this->get(route('profile.edit', absolute: false))
            ->assertOk()
            ->assertSee(route('platform.security.index', absolute: false), false);

        $this->actingAs($user)
            ->get(route('platform.security.index', absolute: false))
            ->assertForbidden();
    }

    public function test_every_allowed_module_blocks_its_routes_including_invitation_mutations(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create();
        $customer = Customer::factory()->for($barbershop)->create();
        $moduleRoutes = [
            'members' => 'tenant.members.index',
            'barbers' => 'tenant.barbers.index',
            'services' => 'tenant.services.index',
            'appointments' => 'tenant.appointments.index',
            'business-hours' => 'tenant.business-hours.index',
            'barber-availability' => 'tenant.barber-availability.index',
        ];

        foreach ($moduleRoutes as $module => $routeName) {
            $this->actingAs($admin)
                ->post(route('platform.security.modules.suspend', $barbershop, false), [
                    'module' => $module,
                    'reason' => 'Validación de ruta.',
                ])->assertRedirect();

            $this->actingAs($admin)->get(route($routeName, $barbershop, false))->assertForbidden();
        }

        $this->actingAs($admin)
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('tenant.customer-invitations.store', [$barbershop, $customer], false), [])
            ->assertForbidden();
    }

    public function test_unknown_module_and_empty_reason_cannot_create_a_containment(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $barbershop = Barbershop::factory()->create();

        $this->actingAs($admin)
            ->from(route('platform.security.index', absolute: false))
            ->post(route('platform.security.modules.suspend', $barbershop, false), [
                'module' => 'profile',
                'reason' => '',
            ])
            ->assertSessionHasErrors(['module', 'reason']);

        $this->assertDatabaseCount('module_containments', 0);
    }

    public function test_platform_admin_security_revocation_invalidates_pending_transfer_version_without_affecting_other_account(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $account = User::factory()->create(['active_session_id' => Str::random(64)]);
        $otherAccount = User::factory()->create(['active_session_id' => Str::random(64)]);
        $staleVersion = (int) User::query()->whereKey($account->getKey())->value('security_session_version');
        $sessions = app(SingleDeviceSession::class);

        $this->actingAs($admin)->post(route('platform.security.sessions.revoke', $account, false), [
            'reason' => 'Solicitud verificada.',
        ])->assertRedirect();

        $this->assertSame(1, $account->fresh()->security_session_version);
        $this->assertFalse($sessions->claimAfterCredentials($account->fresh(), Str::random(64), $staleVersion));
        $this->assertFalse($sessions->confirmTransfer($account->fresh(), Str::random(64), $staleVersion));
        $this->assertNotNull($otherAccount->fresh()->active_session_id);
        $this->assertSame(0, $otherAccount->fresh()->security_session_version);
    }
}
