<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\SecurityIncidentAlert;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SecurityContainmentAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_lock_creates_one_persistent_alert_audit_and_configured_email_without_secrets(): void
    {
        Notification::fake();
        config(['security.alert_recipient' => 'security@example.test']);
        $this->freezeTime();
        $account = User::factory()->create();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')->post('/login', ['email' => $account->email, 'password' => 'never-store-this-secret'])
                ->assertRedirect('/login')->assertSessionHasErrors('email');
        }

        $this->assertDatabaseHas('security_audit_logs', ['event' => 'security.account_locked', 'subject_id' => $account->id]);
        $this->assertDatabaseCount('security_alerts', 1);
        $this->assertDatabaseHas('security_alerts', ['event' => 'security.account_locked', 'subject_id' => $account->id, 'occurrences' => 1]);
        Notification::assertSentOnDemandTimes(SecurityIncidentAlert::class, 1);
        Notification::assertSentOnDemand(SecurityIncidentAlert::class, fn (SecurityIncidentAlert $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'security@example.test'
            && $notification->event === 'security.account_locked'
            && $channels === ['mail']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.100'])->post('/login', ['email' => $account->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        Notification::assertSentOnDemandTimes(SecurityIncidentAlert::class, 1);
        $this->assertDatabaseHas('security_alerts', ['occurrences' => 1]);
        $this->assertStringNotContainsString('never-store-this-secret', json_encode(DB::table('security_alerts')->get(), JSON_THROW_ON_ERROR));
    }

    public function test_admin_actions_are_audited_grouped_and_mailed_again_only_after_fifteen_minutes(): void
    {
        Notification::fake();
        config(['security.alert_recipient' => 'security@example.test']);
        $this->freezeTime();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $account = User::factory()->create();
        $shop = Barbershop::factory()->create();

        $this->actingAs($admin);
        foreach (range(1, 2) as $attempt) {
            $this->post(route('platform.security.sessions.revoke', $account, false), ['reason' => 'Sesión no reconocida.'])->assertRedirect();
        }

        $this->assertDatabaseCount('security_alerts', 1);
        $this->assertDatabaseHas('security_alerts', ['event' => 'security.session_revoked', 'occurrences' => 2]);
        $this->assertSame(2, DB::table('security_audit_logs')->where('event', 'security.session_revoked')->count());
        Notification::assertSentOnDemandTimes(SecurityIncidentAlert::class, 1);

        $this->travel(15)->minutes();
        $this->post(route('platform.security.sessions.revoke', $account, false), ['reason' => 'Comprobación posterior.'])->assertRedirect();
        Notification::assertSentOnDemandTimes(SecurityIncidentAlert::class, 2);
        $this->assertDatabaseHas('security_alerts', ['occurrences' => 3]);

        $payload = ['module' => 'appointments', 'reason' => 'Investigación preventiva.'];
        $this->post(route('platform.security.modules.suspend', $shop, false), $payload)->assertRedirect();
        $this->post(route('platform.security.modules.suspend', $shop, false), $payload)->assertRedirect();
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'platform.barbershop.module_suspended', 'subject_id' => $shop->id, 'barbershop_id' => $shop->id, 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('security_alerts', ['event' => 'platform.barbershop.module_suspended', 'occurrences' => 1]);
        $this->post(route('platform.security.modules.release', $shop, false), ['module' => 'appointments', 'reason' => 'Incidente resuelto.'])->assertRedirect();
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'platform.barbershop.module_released', 'barbershop_id' => $shop->id]);
        Notification::assertSentOnDemandTimes(SecurityIncidentAlert::class, 4);
    }

    public function test_alert_history_is_restricted_escaped_and_discoverable_from_real_platform_sidebar(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $account = User::factory()->create(['name' => '<script>account()</script>']);
        $reason = '<script>reason()</script>';
        config(['security.alert_recipient' => 'security@example.test']);
        $this->actingAs($admin)->post(route('platform.security.sessions.revoke', $account, false), ['reason' => $reason])->assertRedirect();

        $this->get(route('platform.security-audit-logs.index', absolute: false))
            ->assertOk()->assertSee('Alertas de seguridad')->assertSee($account->name)->assertSee($reason)
            ->assertDontSee($reason, false)->assertSee('1 ocurrencia')
            ->assertSee(route('platform.security.index', absolute: false), false);
        $this->getJson(route('platform.security-audit-logs.index', absolute: false))
            ->assertOk()->assertSee('Alertas de seguridad')->assertHeader('Cache-Control', 'no-store, private');

        Notification::assertSentOnDemand(SecurityIncidentAlert::class, function (SecurityIncidentAlert $notification): bool {
            $mail = (string) $notification->toMail(new AnonymousNotifiable)->render();
            $this->assertStringNotContainsString('<script>reason()</script>', $mail);
            $this->assertStringContainsString('&lt;script&gt;', $mail);

            return true;
        });

        foreach (['owner', 'admin', 'barber'] as $role) {
            $member = User::factory()->create();
            $shop = Barbershop::factory()->create();
            Membership::factory()->for($member)->for($shop)->create(['role' => $role]);
            $this->actingAs($member)->get(route('platform.security-audit-logs.index', absolute: false))->assertForbidden()->assertDontSee($reason);
        }
    }

    public function test_missing_recipient_skips_email_and_delivery_failure_does_not_undo_containment(): void
    {
        Notification::fake();
        config(['security.alert_recipient' => null]);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $account = User::factory()->create();
        $this->actingAs($admin)->post(route('platform.security.sessions.revoke', $account, false), ['reason' => 'Alerta interna.'])->assertRedirect();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('security_alerts', 1);

        config(['security.alert_recipient' => 'security@example.test']);
        $shop = Barbershop::factory()->create();
        $this->mock(Dispatcher::class)
            ->shouldReceive('send')->once()->andThrow(new RuntimeException('secret-transport-password'));
        Log::shouldReceive('warning')->once()->with('Security alert email dispatch failed.', ['event' => 'platform.barbershop.module_suspended']);
        $this->post(route('platform.security.modules.suspend', $shop, false), ['module' => 'appointments', 'reason' => 'Entrega temporalmente no disponible.'])->assertRedirect();
        $this->assertDatabaseHas('module_containments', ['barbershop_id' => $shop->id, 'module' => 'appointments']);
        $this->assertDatabaseHas('security_alerts', ['event' => 'platform.barbershop.module_suspended']);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'platform.barbershop.module_suspended']);
    }

    public function test_rejected_admin_request_changes_no_alerts_audit_or_delivery(): void
    {
        Notification::fake();
        $member = User::factory()->create();
        $account = User::factory()->create();
        $this->actingAs($member)->post(route('platform.security.sessions.revoke', $account, false), ['reason' => 'No autorizado'])->assertForbidden();

        $this->assertDatabaseCount('security_alerts', 0);
        $this->assertDatabaseCount('security_audit_logs', 0);
        Notification::assertNothingSent();
    }
}
