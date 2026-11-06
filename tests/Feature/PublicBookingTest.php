<?php

namespace Tests\Feature;

use App\Jobs\SendAppointmentPush;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Notifications\GuestBookingEmailCode;
use App\Notifications\PublicBookingConfirmed;
use App\Support\ModuleContainment;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-10-05 08:00:00 UTC');
    }

    public function test_public_page_uses_shop_brand_and_lists_only_active_local_services(): void
    {
        [$shop, $service] = $this->context();
        $shop->update(['brand_primary_color' => '#225588', 'brand_description' => '<script>bad()</script>']);
        $hidden = Service::factory()->for($shop)->create(['active' => false]);
        $foreign = Service::factory()->create();
        $this->get(route('public.booking.create', $shop))->assertOk()
            ->assertSee($shop->name)->assertSee($service->name)->assertSee('#225588')
            ->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)
            ->assertDontSee('<script>bad()</script>', false)
            ->assertDontSee($hidden->name)->assertDontSee($foreign->name)
            ->assertSee('Sin preferencia')
            ->assertSee('data-availability-url="'.route('public.availability', $shop).'"', false)
            ->assertSee('data-slot-list', false)->assertSee('data-slot-status', false)
            ->assertSee('data-public-booking', false);
    }

    public function test_reference_layout_keeps_slots_associated_with_the_real_booking_form(): void
    {
        [$shop, $service] = $this->context();
        $response = $this->get(route('public.booking.create', [$shop, 'service_id' => $service->id, 'date' => '2026-10-05']))->assertOk();
        $html = new \DOMDocument;
        $html->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($html);
        $this->assertSame(1, $xpath->query('//form[@id="public-booking-form" and @method="POST"]')->length);
        $this->assertSame(0, $xpath->query('//form//form')->length);
        $this->assertSame(1, $xpath->query('//*[@data-booking-wizard]')->length);
        foreach ([1, 2, 3] as $step) {
            $this->assertSame(1, $xpath->query('//*[@data-step-panel="'.$step.'"]')->length);
        }
        $this->assertSame(4, $xpath->query('//*[@data-progress-step]')->length);
        $this->assertSame(2, $xpath->query('//button[@type="button" and (@data-wizard-back or @data-wizard-next)]')->length);
        $slots = $xpath->query('//input[@name="slot"]');
        $this->assertGreaterThan(0, $slots->length);
        foreach ($slots as $slot) {
            $this->assertSame('public-booking-form', $slot->getAttribute('form'));
            $this->assertTrue($slot->hasAttribute('required'));
        }
        $this->assertSame(1, $xpath->query('//form[@id="public-booking-form"]//input[@name="email" and @required]')->length);
        $this->assertSame(1, $xpath->query('//a[@href="#booking-details"]')->length);
        $this->assertSame('Nombre y apellido', $xpath->query('//input[@name="name"]')->item(0)->getAttribute('placeholder'));
        $this->assertSame('tu@correo.com', $xpath->query('//input[@name="email"]')->item(0)->getAttribute('placeholder'));
        $phone = $xpath->query('//input[@name="phone"]')->item(0);
        $this->assertSame('10 dígitos', $phone->getAttribute('placeholder'));
        $this->assertSame('40', $phone->getAttribute('maxlength'));
        $this->assertFalse($phone->hasAttribute('required'));
        $this->assertSame(1, $xpath->query('//div[@data-date-options and @hidden]')->length);
        $this->assertSame(1, $xpath->query('//details[@data-date-picker and @open]//input[@type="date" and @required]')->length);
        $this->assertSame(2, $xpath->query('//*[@data-verification-controls]//button')->length);
        $this->assertSame(1, $xpath->query('//*[@data-code-controls and @hidden]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-email-code and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-verify-code and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-resend-status]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-verification-controls]//*[@data-code-status]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="public-booking-form"]//*[@data-code-status]')->length);
        $submit = $xpath->query('//button[@data-slot-submit]')->item(0);
        $this->assertSame('Confirmar reserva', trim($submit->textContent));
        $this->assertTrue($submit->hasAttribute('disabled'));
        $this->assertSame(1, $xpath->query('//form[@data-email-verified="false" and @data-availability-ready="true"]')->length);
        $accountPage = $this->actingAs(User::factory()->create())->get(route('public.booking.create', [$shop, 'service_id' => $service->id, 'date' => '2026-10-05']))->assertOk();
        $html->loadHTML($accountPage->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $accountXpath = new \DOMXPath($html);
        $this->assertFalse($accountXpath->query('//button[@data-slot-submit]')->item(0)->hasAttribute('disabled'));
        $this->assertSame(1, $accountXpath->query('//form[@data-email-verified="true" and @data-availability-ready="true"]')->length);
        $response->assertSee('data-booking-price', false)->assertSee('data-booking-time', false)
            ->assertDontSee('Cancelación gratuita')->assertDontSee('Los Muchachos');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_verified_account_can_confirm_exact_summary_once_without_guest_code(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $user = User::factory()->create();
        $linked = Customer::factory()->for($shop)->for($user)->create();
        $this->actingAs($user);
        $summary = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber));
        $summary->assertOk()->assertJsonPath('summary.barber', $barber->display_name)
            ->assertJsonPath('summary.email', $user->email)->assertJsonPath('summary.starts_at', '2026-10-05T10:00');
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());
        $this->assertStringContainsString('no-store', (string) $summary->headers->get('Cache-Control'));
        $token = $summary->json('draft_id');
        $response = $this->postJson(route('public.booking.store', $shop), ['draft_id' => $token, 'barber_id' => 999]);
        $response->assertOk()->assertJsonPath('confirmed', true);
        $appointment = Appointment::query()->sole();
        $this->assertDatabaseHas('appointments', ['customer_id' => $linked->id, 'barber_id' => $barber->id, 'starts_at' => '2026-10-05 10:00:00']);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id, 'barbershop_id' => $shop->id, 'customer_id' => $linked->id,
            'actor_kind' => 'customer', 'actor_user_id' => $user->id, 'event_kind' => 'created',
            'after_status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $token])->assertExactJson($response->json());
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('customers', 1);
        Notification::assertSentOnDemandTimes(PublicBookingConfirmed::class, 1);
        Notification::assertSentOnDemand(PublicBookingConfirmed::class, fn ($notice, $channels, $notifiable): bool => $notifiable->routes['mail'] === $user->email
            && $notice->managementUrl !== null
            && URL::hasValidSignature(Request::create($notice->managementUrl))
            && str_contains($notice->managementUrl, '/appointments/1/manage'));
    }

    public function test_guest_booking_queues_one_assigned_push_for_the_barber_after_confirmation(): void
    {
        Notification::fake();
        Queue::fake([SendAppointmentPush::class]);
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);
        [$shop, $service, $barber] = $this->context();
        $barberUser = User::factory()->create();
        $barber->update(['user_id' => $barberUser->id]);
        $this->verifyGuest($shop);
        $draft = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertOk()->json('draft_id');
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk();
        $appointment = Appointment::query()->sole();
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk();

        Queue::assertPushedTimes(SendAppointmentPush::class, 1);
        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->appointmentId === $appointment->id
            && $job->recipientUserId === $barberUser->id
            && $job->actorUserId === null
            && $job->event === 'assigned');
    }

    public function test_signed_in_customer_booking_queues_one_assigned_push_for_the_barber(): void
    {
        Notification::fake();
        Queue::fake([SendAppointmentPush::class]);
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);
        [$shop, $service, $barber] = $this->context();
        $barberUser = User::factory()->create();
        $barber->update(['user_id' => $barberUser->id]);
        $customerUser = User::factory()->create();
        $this->actingAs($customerUser);
        $summary = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertOk();
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());
        $draft = $summary->json('draft_id');
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk();
        $appointment = Appointment::query()->sole();
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk();

        Queue::assertPushedTimes(SendAppointmentPush::class, 1);
        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->appointmentId === $appointment->id
            && $job->recipientUserId === $barberUser->id
            && $job->actorUserId === $customerUser->id
            && $job->event === 'assigned');
    }

    public function test_three_distinct_verified_guest_bookings_reuse_one_customer(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $shop->businessHours()->update(['closes_at' => '12:00']);
        $barber->availabilities()->update(['ends_at' => '12:00']);

        foreach (['10:00', '10:30', '11:00'] as $time) {
            $this->verifyGuest($shop);
            $payload = $this->payload($service, $barber);
            $payload['slot'] = $barber->id.'|2026-10-05T'.$time;
            $draft = $this->postJson(route('public.booking.summary', $shop), $payload)->assertOk()->json('draft_id');
            $receipt = $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])
                ->assertOk()->assertJsonPath('confirmed', true);
            $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertExactJson($receipt->json());
            $this->travel(5)->minutes();
        }

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('appointments', 3);
        $this->assertSame(1, Appointment::query()->distinct()->count('customer_id'));
        $this->assertDatabaseCount('appointment_events', 3);
        $this->assertDatabaseCount('users', 1);
        Notification::assertSentOnDemandTimes(PublicBookingConfirmed::class, 3);
        $this->postJson(route('public.booking.summary', $shop), $payload)->assertForbidden();
    }

    public function test_guest_proof_and_shared_customer_do_not_grant_access_to_another_appointment(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $customer = Customer::factory()->for($shop)->create(['name' => 'Invitado', 'email' => 'guest@example.com']);
        $first = Appointment::factory()->for($shop)->for($service)->for($barber)->for($customer)->create();
        $privateService = Service::factory()->for($shop)->create(['name' => 'Servicio privado de otra cita']);
        $second = Appointment::factory()->for($shop)->for($privateService, 'service')->for($barber)->for($customer)->create();
        $before = $second->fresh()->getAttributes();
        $this->verifyGuest($shop);
        $signedUrl = URL::signedRoute('public.booking.management', [$shop, $first]);
        $signature = '?'.parse_url($signedUrl, PHP_URL_QUERY);

        $this->get($signedUrl)->assertOk()->assertSee($service->name)->assertDontSee($privateService->name)
            ->assertViewHas('appointment', fn (Appointment $appointment): bool => $appointment->id === $first->id);
        $this->get(route('public.booking.management', [$shop, $second]))->assertForbidden();
        $this->get(route('public.booking.management', [$shop, $second]).$signature)->assertForbidden();
        $this->post(route('public.booking.management.cancel', [$shop, $second]).$signature)->assertForbidden();
        $this->post(route('public.booking.management.reschedule', [$shop, $second]).$signature, [
            'starts_at' => '2026-10-06T10:00',
        ])->assertForbidden();
        $this->get(route('tenant.appointments.index', $shop))->assertRedirect(route('login'));

        $this->assertSame($before, $second->fresh()->getAttributes());
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_guest_reuses_only_the_unique_local_unlinked_contact_without_changing_it(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $identity = ['name' => '  INVITADO   CENTRAL  ', 'email' => 'GUEST@EXAMPLE.COM'];
        $existing = Customer::factory()->for($shop)->create($identity + ['phone' => '5551111111']);
        $linked = Customer::factory()->for($shop)->for(User::factory())->create($identity);
        $foreign = Customer::factory()->create($identity);
        $accented = Customer::factory()->for($shop)->create(['name' => 'Invitádo Central', 'email' => 'guest@example.com']);
        $before = $existing->fresh()->getAttributes();
        $this->verifyGuest($shop);
        $payload = $this->payload($service, $barber);
        $payload['name'] = 'Invitado  Central';
        $payload['email'] = 'Guest@Example.com';
        $payload['phone'] = '5552222222';
        $draft = $this->postJson(route('public.booking.summary', $shop), $payload)->assertOk()->json('draft_id');
        $response = $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk();

        $this->assertDatabaseCount('customers', 4);
        $this->assertSame($existing->id, Appointment::query()->sole()->customer_id);
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertModelExists($linked);
        $this->assertModelExists($foreign);
        $this->assertModelExists($accented);
        $this->assertDatabaseHas('appointment_events', ['customer_id' => $existing->id, 'actor_kind' => 'guest', 'actor_user_id' => null]);
        $response->assertJsonMissingPath('customer_id')->assertJsonMissingPath('user_id');
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_guest_never_reuses_a_linked_or_foreign_contact(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $identity = ['name' => 'Invitado', 'email' => 'guest@example.com'];
        $linked = Customer::factory()->for($shop)->for(User::factory())->create($identity);
        $foreign = Customer::factory()->create($identity);
        $this->verifyGuest($shop);
        $draft = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertOk()->json('draft_id');
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk();

        $customer = Appointment::query()->sole()->customer;
        $this->assertNotContains($customer->id, [$linked->id, $foreign->id]);
        $this->assertNull($customer->user_id);
        $this->assertSame($shop->id, $customer->barbershop_id);
        $this->assertDatabaseCount('customers', 3);
    }

    public function test_ambiguous_guest_matches_are_not_merged_and_do_not_block_booking(): void
    {
        Notification::fake();
        Log::spy();
        [$shop, $service, $barber] = $this->context();
        $duplicates = Customer::factory()->count(2)->for($shop)->create(['name' => 'Invitado', 'email' => 'guest@example.com']);
        $before = $duplicates->map(fn (Customer $customer): array => $customer->fresh()->getAttributes())->all();
        $this->verifyGuest($shop);
        $draft = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertOk()->json('draft_id');
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk();

        $this->assertDatabaseCount('customers', 3);
        $this->assertDatabaseCount('appointments', 1);
        $this->assertNotContains(Appointment::query()->sole()->customer_id, $duplicates->modelKeys());
        $this->assertSame($before, $duplicates->map(fn (Customer $customer): array => $customer->fresh()->getAttributes())->all());
        Log::shouldHaveReceived('notice')->once()->with('guest_customer.ambiguous_match', [
            'barbershop_id' => $shop->id,
            'candidate_ids' => $duplicates->modelKeys(),
        ]);
    }

    public function test_guest_must_verify_and_gets_a_new_contact_record_without_email_linking(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $existing = Customer::factory()->for($shop)->create(['email' => 'guest@example.com', 'name' => 'Historia previa']);
        $before = $existing->getAttributes();
        $payload = $this->payload($service, $barber);
        $this->postJson(route('public.booking.summary', $shop), $payload)->assertForbidden();
        $this->assertDatabaseCount('appointments', 0);
        $this->verifyGuest($shop);
        $summary = $this->postJson(route('public.booking.summary', $shop), $payload)->assertOk();
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $summary->json('draft_id')])->assertOk();
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('appointments', 1);
        $appointment = Appointment::query()->sole();
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id, 'barbershop_id' => $shop->id, 'customer_id' => $appointment->customer_id,
            'actor_kind' => 'guest', 'actor_user_id' => null, 'event_kind' => 'created',
            'after_status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseHas('customers', ['barbershop_id' => $shop->id, 'email' => 'guest@example.com', 'name' => 'Invitado', 'user_id' => null]);
        $after = $existing->fresh()->getAttributes();
        ksort($before);
        ksort($after);
        $this->assertSame($before, $after);
        $this->assertDatabaseCount('users', 1);
        $this->postJson(route('public.booking.summary', $shop), $payload)->assertForbidden();
        Notification::assertSentOnDemandTimes(PublicBookingConfirmed::class, 1);
    }

    public function test_expired_proof_cannot_confirm_and_unverified_account_does_not_bypass_it(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $this->actingAs(User::factory()->unverified()->create());
        $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->verifyGuest($shop);
        $this->travel(9)->minutes();
        $draft = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertOk()->json('draft_id');
        $this->travel(2)->minutes();
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertForbidden();
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('customers', 0);
        Notification::assertSentOnDemandTimes(PublicBookingConfirmed::class, 0);
    }

    public function test_lost_slot_rolls_back_contact_and_preserves_proof_for_a_new_summary(): void
    {
        Notification::fake();
        Queue::fake([SendAppointmentPush::class]);
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);
        [$shop, $service, $barber] = $this->context();
        $barberUser = User::factory()->create();
        $barber->update(['user_id' => $barberUser->id]);
        $this->verifyGuest($shop);
        $payload = $this->payload($service, $barber);
        $draft = $this->postJson(route('public.booking.summary', $shop), $payload)->assertOk()->json('draft_id');
        Appointment::factory()->for($shop)->for($service)->for($barber)->create(['starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 10:30:00']);
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertUnprocessable();
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 1);
        Notification::assertSentOnDemandTimes(PublicBookingConfirmed::class, 0);
        Queue::assertNotPushed(SendAppointmentPush::class);
        $payload['slot'] = $barber->id.'|2026-10-05T10:30';
        $newDraft = $this->postJson(route('public.booking.summary', $shop), $payload)->assertOk()->json('draft_id');
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $newDraft])->assertOk();
        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_tenant_gates_and_tampered_or_expired_drafts_cannot_create_records(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $this->actingAs(User::factory()->create());
        $payload = $this->payload($service, $barber);
        $foreign = Service::factory()->create();
        $bad = $payload;
        $bad['service_id'] = $foreign->id;
        $this->postJson(route('public.booking.summary', $shop), $bad)->assertNotFound();
        $summary = $this->postJson(route('public.booking.summary', $shop), $payload)->assertOk();
        $draft = $summary->json('draft_id');
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), 'another-browser-session-0123456789');
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertNotFound();
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());
        $other = Barbershop::factory()->create();
        $this->postJson(route('public.booking.store', $other), ['draft_id' => $draft])->assertNotFound();
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => str_repeat('x', 64)])->assertNotFound();
        $this->travel(11)->minutes();
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertNotFound();
        app(ModuleContainment::class)->suspend($shop, 'appointments', 'Test');
        $this->get(route('public.booking.create', $shop))->assertNotFound();
        $this->postJson(route('public.booking.summary', $shop), $payload)->assertNotFound();
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $draft])->assertNotFound();
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('customers', 0);
        Notification::assertNothingSent();
    }

    public function test_html_summary_and_confirmation_render_the_same_professional_and_contact(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = $this->get(route('public.booking.create', ['barbershop' => $shop, 'service_id' => $service->id, 'date' => '2026-10-05']))
            ->assertOk()->assertSee('10:00')->assertSee($barber->display_name)->assertSee('Tus datos');
        $cookie = $page->getCookie(config('session.cookie'));
        $this->withCookie($cookie->getName(), $cookie->getValue());
        $summary = $this->post(route('public.booking.summary', $shop), $this->payload($service, $barber))
            ->assertOk()->assertSee('Revisa tu reserva')->assertSee($barber->display_name)->assertSee($user->email)
            ->assertSee('Paso 3 de 4 · Datos personales y confirmación');
        $draft = $summary->viewData('draft_id');
        $this->post(route('public.booking.store', $shop), ['draft_id' => $draft])->assertOk()
            ->assertSee('¡Tu cita está confirmada!')->assertSee($barber->display_name)->assertSee($user->email)
            ->assertSee('Paso 4 de 4 · Reserva confirmada');
        $this->assertDatabaseCount('appointments', 1);
        Notification::assertSentOnDemandTimes(PublicBookingConfirmed::class, 1);
    }

    public function test_service_changes_require_a_fresh_summary_without_creating_contact_or_sending_confirmation(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $this->actingAs(User::factory()->create());
        $summary = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertOk();
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());
        $service->update(['price' => '999.00']);
        $this->postJson(route('public.booking.store', $shop), ['draft_id' => $summary->json('draft_id')])
            ->assertUnprocessable()->assertJsonValidationErrors('slot');
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('customers', 0);
        Notification::assertNothingSent();
    }

    public function test_html_lost_slot_returns_to_booking_form_with_error_and_contact_preserved(): void
    {
        Notification::fake();
        [$shop, $service, $barber] = $this->context();
        $this->verifyGuest($shop);
        $payload = $this->payload($service, $barber);
        $summary = $this->post(route('public.booking.summary', $shop), $payload)->assertOk();
        Appointment::factory()->for($shop)->for($service)->for($barber)->create([
            'starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 10:30:00',
        ]);
        $response = $this->from(route('public.booking.summary', $shop))->post(route('public.booking.store', $shop), ['draft_id' => $summary->viewData('draft_id')]);
        $response->assertRedirect(route('public.booking.create', ['barbershop' => $shop, 'service_id' => $service->id, 'date' => '2026-10-05']))
            ->assertSessionHasErrors('slot')->assertSessionHasInput('email', 'guest@example.com');
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('guest@example.com');
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('appointments', 1);
        Notification::assertSentOnDemandTimes(PublicBookingConfirmed::class, 0);
    }

    public function test_confirmation_mail_failure_does_not_lose_or_duplicate_a_saved_booking(): void
    {
        [$shop, $service, $barber] = $this->context();
        $this->actingAs(User::factory()->create());
        $this->mock(Dispatcher::class)->shouldReceive('send')->once()
            ->andThrow(new \RuntimeException('mail unavailable'));
        $summary = $this->postJson(route('public.booking.summary', $shop), $this->payload($service, $barber))->assertOk();
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());
        $payload = ['draft_id' => $summary->json('draft_id')];
        $receipt = $this->postJson(route('public.booking.store', $shop), $payload)
            ->assertOk()->assertJsonPath('confirmed', true)->assertJsonPath('email_sent', false);
        $this->postJson(route('public.booking.store', $shop), $payload)->assertExactJson($receipt->json());
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('customers', 1);
    }

    /** @return array{Barbershop, Service, Barber} */
    private function context(): array
    {
        $shop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $service = Service::factory()->for($shop)->create(['duration_minutes' => 30]);
        $barber = Barber::factory()->for($shop)->create();
        BusinessHour::factory()->for($shop)->create(['weekday' => 1, 'opens_at' => '10:00', 'closes_at' => '11:00']);
        BarberAvailability::factory()->for($barber)->create(['weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00']);

        return [$shop, $service, $barber];
    }

    /** @return array{service_id: int, slot: string, name: string, email: string} */
    private function payload(Service $service, Barber $barber): array
    {
        return ['service_id' => $service->id, 'slot' => $barber->id.'|2026-10-05T10:00', 'name' => 'Invitado', 'email' => 'guest@example.com'];
    }

    private function verifyGuest(Barbershop $shop): void
    {
        $response = $this->postJson(route('public.booking.email-code.store', $shop), ['email' => 'guest@example.com'])->assertOk();
        $cookie = $response->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());
        $code = null;
        Notification::assertSentOnDemand(GuestBookingEmailCode::class, function ($notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });
        $this->postJson(route('public.booking.email-code.verify', $shop), ['email' => 'guest@example.com', 'code' => $code])->assertOk();
    }
}
