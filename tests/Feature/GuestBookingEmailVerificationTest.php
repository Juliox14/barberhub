<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use App\Notifications\GuestBookingEmailCode;
use App\Support\ModuleContainment;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class GuestBookingEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_verify_an_email_code_without_creating_booking_or_customer_records(): void
    {
        Notification::fake();
        $barbershop = Barbershop::factory()->create();
        $email = 'Guest@Example.com';

        $issueResponse = $this->postJson('/barbershops/'.$barbershop->slug.'/booking/email-code', [
            'email' => $email,
        ]);

        $issueResponse->assertOk()->assertJsonStructure(['status']);
        $code = null;
        Notification::assertSentOnDemand(GuestBookingEmailCode::class, function (GuestBookingEmailCode $notification, array $channels, object $notifiable) use (&$code, $email): bool {
            $code = $notification->code;

            return $channels === ['mail']
                && $notifiable->routes['mail'] === strtolower($email);
        });
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $issueResponse->assertDontSee($code, false);
        $sessionCookie = $issueResponse->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $this->withCredentials()->withCookie($sessionCookie->getName(), $sessionCookie->getValue());

        $this->postJson('/barbershops/'.$barbershop->slug.'/booking/email-code/verify', [
            'email' => $email,
            'code' => $code,
        ])->assertExactJson(['verified' => true]);

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_wrong_and_expired_codes_never_verify_the_guest(): void
    {
        Notification::fake();
        $this->freezeTime();
        $barbershop = Barbershop::factory()->create();
        [$code, $sessionCookie] = $this->requestCode($barbershop, 'guest@example.com');
        $this->useSessionCookie($sessionCookie);
        $wrongCode = $code === '123456' ? '654321' : '123456';

        $this->verifyCode($barbershop, 'guest@example.com', $wrongCode)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);
        $this->travel(11)->minutes();
        $this->verifyCode($barbershop, 'guest@example.com', $code)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_fifth_wrong_code_exhausts_challenge_without_affecting_other_records(): void
    {
        Notification::fake();
        $barbershop = Barbershop::factory()->create();
        [$code, $sessionCookie] = $this->requestCode($barbershop, 'guest@example.com');
        $this->useSessionCookie($sessionCookie);
        $wrongCode = $code === '123456' ? '654321' : '123456';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->verifyCode($barbershop, 'guest@example.com', $wrongCode)
                ->assertUnprocessable()
                ->assertExactJson(['verified' => false]);
        }

        $this->verifyCode($barbershop, 'guest@example.com', $code)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_verification_is_bound_to_the_session_and_tenant(): void
    {
        Notification::fake();
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        [$code, $sessionCookie] = $this->requestCode($barbershop, 'guest@example.com');

        $this->withCredentials()->withCookie(config('session.cookie'), 'rotated-session-id-0123456789');
        $this->verifyCode($barbershop, 'guest@example.com', $code)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);
        $this->useSessionCookie($sessionCookie);
        $this->verifyCode($otherBarbershop, 'guest@example.com', $code)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);
        $this->verifyCode($barbershop, 'guest@example.com', $code)
            ->assertOk()
            ->assertExactJson(['verified' => true]);

        $this->verifyCode($barbershop, 'guest@example.com', $code)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_resend_after_cooldown_invalidates_the_previous_code_and_allows_only_the_new_code(): void
    {
        Notification::fake();
        $this->freezeTime();
        $barbershop = Barbershop::factory()->create();
        [$oldCode, $sessionCookie] = $this->requestCode($barbershop, 'guest@example.com');
        $this->useSessionCookie($sessionCookie);
        $this->withCredentials()->withCookie(config('session.cookie'), 'resend-new-session-0123456789');
        $this->postJson($this->issueUrl($barbershop), ['email' => 'guest@example.com'])
            ->assertOk()
            ->assertJsonStructure(['status']);
        Notification::assertSentOnDemandTimes(GuestBookingEmailCode::class, 1);
        $this->useSessionCookie($sessionCookie);
        $this->travel(60)->seconds();
        $this->postJson($this->issueUrl($barbershop), ['email' => 'guest@example.com'])
            ->assertOk()
            ->assertJsonStructure(['status']);

        $newCode = null;
        Notification::assertSentOnDemand(GuestBookingEmailCode::class, function (GuestBookingEmailCode $notification, array $channels, object $notifiable) use (&$newCode, $oldCode): bool {
            if ($notification->code === $oldCode) {
                return false;
            }

            $newCode = $notification->code;

            return $channels === ['mail'] && $notifiable->routes['mail'] === 'guest@example.com';
        });
        $this->assertIsString($newCode);
        $this->assertNotSame($oldCode, $newCode);

        $this->verifyCode($barbershop, 'guest@example.com', $oldCode)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);
        $this->verifyCode($barbershop, 'guest@example.com', $newCode)
            ->assertOk()
            ->assertExactJson(['verified' => true]);
    }

    public function test_mail_failure_does_not_leave_a_verifiable_challenge(): void
    {
        $barbershop = Barbershop::factory()->create();
        $email = 'delivery-failure@example.com';
        $code = null;
        $this->mock(Dispatcher::class)
            ->shouldReceive('send')
            ->once()
            ->withArgs(function (AnonymousNotifiable $notifiable, GuestBookingEmailCode $notification) use (&$code, $email): bool {
                $code = $notification->code;

                return $notifiable->routes['mail'] === $email;
            })
            ->andThrow(new RuntimeException('mail transport unavailable'));

        $issueResponse = $this->postJson($this->issueUrl($barbershop), ['email' => $email]);
        $issueResponse->assertOk()->assertExactJson(['status' => 'Si el correo es válido, recibirás un código.']);
        $this->assertIsString($code);
        $issueResponse->assertDontSee($code, false);
        $sessionCookie = $issueResponse->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie);
        $this->useSessionCookie($sessionCookie);

        $this->verifyCode($barbershop, $email, $code)
            ->assertUnprocessable()
            ->assertExactJson(['verified' => false]);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_email_abuse_limit_persists_across_session_changes_without_leaking_codes(): void
    {
        Notification::fake();
        $barbershops = Barbershop::factory()->count(11)->create();
        $email = 'limited@example.com';

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withCredentials()->withCookie(config('session.cookie'), 'rate-limit-session-'.$attempt);
            $this->postJson($this->issueUrl($barbershops[$attempt - 1]), ['email' => $email])
                ->assertOk()
                ->assertJson(['status' => 'Si el correo es válido, recibirás un código.']);
        }

        Notification::assertSentOnDemandTimes(GuestBookingEmailCode::class, 10);
        $this->withCredentials()->withCookie(config('session.cookie'), 'rate-limit-session-11');
        $this->postJson($this->issueUrl($barbershops[10]), ['email' => $email])
            ->assertOk()
            ->assertExactJson(['status' => 'Si el correo es válido, recibirás un código.']);
        Notification::assertSentOnDemandTimes(GuestBookingEmailCode::class, 10);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_invalid_inputs_inactive_tenants_and_suspended_appointments_are_rejected(): void
    {
        Notification::fake();
        $barbershop = Barbershop::factory()->create();
        $customer = Customer::factory()->for($barbershop)->create([
            'name' => 'Historial existente',
            'email' => 'guest@example.com',
            'user_id' => null,
        ]);
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        $appointment = Appointment::factory()->create([
            'barbershop_id' => $barbershop->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
        ]);
        $customerBefore = $customer->getAttributes();
        $appointmentBefore = $appointment->getAttributes();

        $this->postJson($this->issueUrl($barbershop), ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->postJson($this->issueUrl($barbershop), ['email' => str_repeat('a', 246).'@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->verifyCode($barbershop, 'guest@example.com', '12ab56')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $barbershop->update(['status' => Barbershop::STATUS_INACTIVE]);
        $this->postJson($this->issueUrl($barbershop), ['email' => 'guest@example.com'])->assertNotFound();
        $this->verifyCode($barbershop, 'guest@example.com', '123456')->assertNotFound();

        $barbershop->update(['status' => Barbershop::STATUS_ACTIVE]);
        app(ModuleContainment::class)->suspend($barbershop, 'appointments', 'Prueba de suspensión.');
        $this->postJson($this->issueUrl($barbershop), ['email' => 'guest@example.com'])->assertNotFound();
        $this->verifyCode($barbershop, 'guest@example.com', '123456')->assertNotFound();

        Notification::assertNothingSent();
        $customerAfter = $customer->fresh()->getAttributes();
        $appointmentAfter = $appointment->fresh()->getAttributes();
        ksort($customerBefore);
        ksort($customerAfter);
        ksort($appointmentBefore);
        ksort($appointmentAfter);
        $this->assertSame($customerBefore, $customerAfter);
        $this->assertSame($appointmentBefore, $appointmentAfter);
        $this->assertNull($customerAfter['user_id']);
    }

    /** @return array{string, Cookie} */
    private function requestCode(Barbershop $barbershop, string $email): array
    {
        $response = $this->postJson($this->issueUrl($barbershop), ['email' => $email]);
        $response->assertOk()->assertJsonStructure(['status']);
        $code = null;
        Notification::assertSentOnDemand(GuestBookingEmailCode::class, function (GuestBookingEmailCode $notification, array $channels, object $notifiable) use (&$code, $email): bool {
            $code = $notification->code;

            return $channels === ['mail'] && $notifiable->routes['mail'] === strtolower($email);
        });
        $this->assertIsString($code);
        $sessionCookie = $response->getCookie(config('session.cookie'));
        $this->assertNotNull($sessionCookie);

        return [$code, $sessionCookie];
    }

    private function useSessionCookie(Cookie $sessionCookie): void
    {
        $this->withCredentials()->withCookie($sessionCookie->getName(), $sessionCookie->getValue());
    }

    private function verifyCode(Barbershop $barbershop, string $email, string $code): TestResponse
    {
        return $this->postJson($this->verifyUrl($barbershop), [
            'email' => $email,
            'code' => $code,
        ]);
    }

    private function issueUrl(Barbershop $barbershop): string
    {
        return '/barbershops/'.$barbershop->slug.'/booking/email-code';
    }

    private function verifyUrl(Barbershop $barbershop): string
    {
        return $this->issueUrl($barbershop).'/verify';
    }
}
