<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThreatEradicationInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_ordering_payloads_fall_back_to_allowlisted_sort_and_direction(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->insertAuditLog('older.event', '2025-01-01 00:00:00');
        $this->insertAuditLog('newer.event', '2025-01-02 00:00:00');
        $this->actingAs($admin);

        $this->get(route('platform.security-audit-logs.index', [
            'sort' => 'event desc; DROP TABLE security_audit_logs --',
            'direction' => 'asc',
        ], false))
            ->assertOk()
            ->assertViewHas('sortState', fn (array $state): bool => $state['sort'] === 'created_at' && $state['direction'] === 'desc')
            ->assertViewHas('auditLogs', fn ($logs): bool => $logs->getCollection()->pluck('event')->all() === ['newer.event', 'older.event']);

        $this->get(route('platform.security-audit-logs.index', [
            'sort' => 'event',
            'direction' => 'desc; DROP TABLE security_audit_logs --',
        ], false))
            ->assertOk()
            ->assertViewHas('sortState', fn (array $state): bool => $state['sort'] === 'event' && $state['direction'] === 'desc')
            ->assertViewHas('auditLogs', fn ($logs): bool => $logs->getCollection()->pluck('event')->sort()->values()->all() === ['newer.event', 'older.event']);

        $this->assertDatabaseCount('security_audit_logs', 2);
    }

    public function test_csrf_rejects_missing_and_invalid_tokens_without_mutating_records(): void
    {
        [$barbershop, $owner, $appointment] = $this->appointmentContext();
        $this->actingAs($owner)->withMiddleware();
        $before = $appointment->fresh()->getAttributes();
        $eventCount = AppointmentEvent::query()->count();
        $auditCount = DB::table('security_audit_logs')->count();
        $environment = app()->environment();
        app()->instance('env', 'production');

        try {
            $url = route('tenant.appointments.cancel', [$barbershop, $appointment], false);
            $this->post($url)->assertStatus(419);

            $token = str_repeat('c', 40);
            $this->withSession(['_token' => $token])->withHeader('X-CSRF-TOKEN', str_repeat('d', 40))
                ->post($url)
                ->assertStatus(419);

            $this->assertSame($before, $appointment->fresh()->getAttributes());
            $this->assertSame($eventCount, AppointmentEvent::query()->count());
            $this->assertSame($auditCount, DB::table('security_audit_logs')->count());

            $this->withHeader('X-CSRF-TOKEN', $token)
                ->post($url)
                ->assertStatus(302);

            $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
            $this->assertSame($eventCount + 1, AppointmentEvent::query()->count());
        } finally {
            app()->instance('env', $environment);
        }
    }

    public function test_array_current_password_is_rejected_without_changing_credentials_session_or_audit(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true]);
        $loginResponse = $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $sessionId = $loginResponse->getCookie(config('session.cookie'))->getValue();
        $user->refresh();
        $passwordHash = $user->password;
        $rememberToken = $user->getRememberToken();
        $sessionVersion = $user->security_session_version;
        $auditCount = DB::table('security_audit_logs')->count();

        $response = $this->withCookie(config('session.cookie'), $sessionId)
            ->from('/profile')
            ->put('/password', [
                'current_password' => ['bad'],
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertStatus(302)
            ->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertSame($passwordHash, $user->fresh()->password);
        $this->assertSame($rememberToken, $user->fresh()->getRememberToken());
        $this->assertSame($sessionVersion, $user->fresh()->security_session_version);
        $this->assertSame($sessionId, $response->getSession()->getId());
        $this->assertSame($auditCount, DB::table('security_audit_logs')->count());
    }

    public function test_malformed_appointment_input_is_rejected_without_changing_records(): void
    {
        [$barbershop, $owner, $appointment] = $this->appointmentContext();
        $before = $appointment->fresh()->getAttributes();
        $eventCount = AppointmentEvent::query()->count();
        $auditCount = DB::table('security_audit_logs')->count();

        $this->actingAs($owner)
            ->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), [
                'starts_at' => "2026-10-05T11:00' OR 1=1 --",
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('starts_at');

        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertSame($eventCount, AppointmentEvent::query()->count());
        $this->assertSame($auditCount, DB::table('security_audit_logs')->count());
    }

    /** @return array{0: Barbershop, 1: User, 2: Appointment} */
    private function appointmentContext(): array
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->for($service)->create();

        return [$barbershop, $owner, $appointment];
    }

    private function insertAuditLog(string $event, string $createdAt): void
    {
        DB::table('security_audit_logs')->insert([
            'event' => $event,
            'metadata' => '[]',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
