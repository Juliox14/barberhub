<?php

namespace Tests\Feature;

use App\Jobs\SendAppointmentPush;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PublicBookingConfirmed;
use App\Support\AppointmentHistory;
use App\Support\ModuleContainment;
use App\Support\PublicBookingManagement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-10-05 08:00:00 UTC');
    }

    public function test_signed_reservation_link_shows_only_its_appointment_without_login_or_mutation(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $otherAppointment = Appointment::factory()->for($shop)->for($appointment->service)->for($appointment->barber)->create([
            'customer_id' => Customer::factory()->for($shop)->create(['name' => 'Otra persona', 'email' => 'other@example.test'])->id,
            'starts_at' => '2026-10-05 10:30:00',
            'ends_at' => '2026-10-05 11:00:00',
        ]);
        $signature = URL::temporarySignedRoute('public.booking.management', now()->addDay(), [
            'barbershop' => $shop,
            'appointment' => $appointment,
        ]);
        $before = $appointment->fresh()->getAttributes();

        $response = $this->get($signature);

        $response->assertOk()->assertSee('05/10/2026 10:00')->assertSee('Cliente de prueba')->assertSee($appointment->service->name)
            ->assertSee($appointment->barber->display_name)->assertDontSee('Otra persona')->assertDontSee('other@example.test')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertModelExists($otherAppointment);
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_unsigned_tampered_and_cross_appointment_links_are_rejected_even_when_authenticated(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $other = Appointment::factory()->for($shop)->for($appointment->service)->for($appointment->barber)->create([
            'starts_at' => '2026-10-05 10:30:00', 'ends_at' => '2026-10-05 11:00:00',
        ]);
        $shop->update(['slug' => 'shop-'.$appointment->id]);
        $signature = URL::temporarySignedRoute('public.booking.management', now()->addDay(), [
            'barbershop' => $shop, 'appointment' => $appointment,
        ]);
        $tampered = route('public.booking.management', [$shop, $other]).'?'.parse_url($signature, PHP_URL_QUERY);
        $user = User::factory()->create();

        $this->get(route('public.booking.management', [$shop, $appointment]))->assertForbidden();
        $this->get($tampered)->assertForbidden();
        $this->actingAs($user)->get(URL::temporarySignedRoute('public.booking.management', now()->addDay(), [
            'barbershop' => $shop, 'appointment' => $other,
        ]))->assertOk();
        $this->actingAs($user)->get($tampered)->assertForbidden();
        $this->assertDatabaseCount('appointments', 2);
    }

    public function test_signed_link_rejects_other_tenant_inactive_shop_and_suspended_appointments_module(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $otherShop = Barbershop::factory()->create();
        $foreignUrl = URL::temporarySignedRoute('public.booking.management', now()->addDay(), [
            'barbershop' => $otherShop, 'appointment' => $appointment,
        ]);
        $signedUrl = URL::temporarySignedRoute('public.booking.management', now()->addDay(), [
            'barbershop' => $shop, 'appointment' => $appointment,
        ]);

        $this->get($foreignUrl)->assertNotFound();
        $shop->update(['status' => Barbershop::STATUS_INACTIVE]);
        $this->get($signedUrl)->assertNotFound();
        $shop->update(['status' => Barbershop::STATUS_ACTIVE]);
        app(ModuleContainment::class)->suspend($shop, 'appointments', 'Test');
        $this->get($signedUrl)->assertNotFound();
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_confirmation_notification_contains_a_real_per_reservation_management_link(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $summary = [
            'name' => 'Cliente de prueba', 'service' => $appointment->service->name,
            'barber' => $appointment->barber->display_name, 'starts_at' => '2026-10-05T10:00',
            'timezone' => $shop->timezone, 'duration' => 30, 'price' => '25.00',
        ];
        $managementUrl = PublicBookingManagement::url($shop, $appointment);
        $notificationWithUrl = new PublicBookingConfirmed($shop->name, $summary, $managementUrl);
        $mailWithUrl = $notificationWithUrl->toMail(new AnonymousNotifiable)->render();

        $this->assertStringContainsString($managementUrl, $mailWithUrl);
        $this->assertTrue(URL::hasValidSignature(Request::create($managementUrl)));
    }

    public function test_valid_signed_customer_reschedule_keeps_original_resources_and_records_history(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $url = URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]);

        $this->post($url, ['starts_at' => '2026-10-05T11:00', 'service_id' => 999, 'barber_id' => 999, 'customer_id' => 999])
            ->assertRedirect(PublicBookingManagement::url($shop, $appointment))
            ->assertSessionHas('status', 'Tu cita fue reprogramada.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id, 'barbershop_id' => $shop->id,
            'barber_id' => $appointment->barber_id, 'service_id' => $appointment->service_id,
            'customer_id' => $appointment->customer_id, 'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00', 'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id, 'actor_kind' => 'guest', 'actor_user_id' => null,
            'event_kind' => 'rescheduled', 'before_starts_at' => '2026-10-05 10:00:00',
            'after_starts_at' => '2026-10-05 11:00:00',
        ]);
    }

    public function test_exact_twelve_hour_cutoff_is_allowed_and_one_second_below_is_rejected_atomically(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $action = URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]);
        $this->travelTo('2026-10-05 04:00:00 UTC');

        $this->post($action)->assertRedirect();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_CANCELLED]);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'event_kind' => Appointment::STATUS_CANCELLED]);

        [$shop, $appointment] = $this->appointmentContext();
        $action = URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]);
        $this->travelTo('2026-10-05 04:00:01 UTC');
        $this->post($action)->assertNotFound();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_SCHEDULED]);
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id]);
    }

    public function test_past_and_occupied_targets_leave_appointment_and_history_unchanged(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-05 04:00:00 UTC');
        $action = URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]);
        $this->postJson($action, ['starts_at' => 'not-a-date'])->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $this->postJson($action, ['starts_at' => '2026-10-04T10:00'])->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $blocking = Appointment::factory()->for($shop)->for($appointment->service)->for($appointment->barber)->create([
            'starts_at' => '2026-10-05 11:00:00', 'ends_at' => '2026-10-05 11:30:00',
        ]);
        $this->postJson($action, ['starts_at' => '2026-10-05T11:00'])->assertUnprocessable()->assertJsonValidationErrors('starts_at');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'starts_at' => '2026-10-05 10:00:00']);
        $this->assertDatabaseHas('appointments', ['id' => $blocking->id, 'status' => Appointment::STATUS_SCHEDULED]);
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id]);
    }

    public function test_both_customer_mutations_reject_missing_wrong_appointment_and_wrong_action_signatures_without_effects(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $other = Appointment::factory()->for($shop)->for($appointment->service)->for($appointment->barber)->create([
            'starts_at' => '2026-10-05 12:00:00', 'ends_at' => '2026-10-05 12:30:00',
        ]);
        $this->enableAppointmentPush();
        Queue::fake([SendAppointmentPush::class]);
        $before = $appointment->fresh()->getAttributes();
        $eventCount = AppointmentEvent::query()->count();
        $cancelUrl = URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]);
        $rescheduleUrl = URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]);
        $query = parse_url($cancelUrl, PHP_URL_QUERY);
        $wrongAction = route('public.booking.management.reschedule', [$shop, $appointment]).'?'.$query;
        $rescheduleQuery = parse_url($rescheduleUrl, PHP_URL_QUERY);
        $wrongReverseAction = route('public.booking.management.cancel', [$shop, $appointment]).'?'.$rescheduleQuery;
        $wrongAppointment = route('public.booking.management.cancel', [$shop, $other]).'?'.$query;
        $wrongRescheduleAppointment = route('public.booking.management.reschedule', [$shop, $other]).'?'.$rescheduleQuery;
        $otherShop = Barbershop::factory()->create();
        $wrongTenant = route('public.booking.management.cancel', [$otherShop, $appointment]).'?'.$query;
        $wrongRescheduleTenant = route('public.booking.management.reschedule', [$otherShop, $appointment]).'?'.$rescheduleQuery;

        $this->post(route('public.booking.management.cancel', [$shop, $appointment]))->assertForbidden();
        $this->post($wrongAction, ['starts_at' => '2026-10-05T11:00'])->assertForbidden();
        $this->post($wrongReverseAction)->assertForbidden();
        $this->post($wrongAppointment)->assertForbidden();
        $this->post($wrongRescheduleAppointment, ['starts_at' => '2026-10-05T11:00'])->assertForbidden();
        $this->post($wrongTenant)->assertForbidden();
        $this->post($wrongRescheduleTenant, ['starts_at' => '2026-10-05T11:00'])->assertForbidden();
        $this->post(route('public.booking.management.reschedule', [$shop, $appointment]), ['starts_at' => '2026-10-05T11:00'])->assertForbidden();
        $this->post($cancelUrl.'x')->assertForbidden();

        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertSame($eventCount, AppointmentEvent::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_customer_mutations_require_csrf_when_web_middleware_is_enabled(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $this->withMiddleware();
        $url = URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]);
        $before = $appointment->fresh()->getAttributes();
        $environment = app()->environment();
        app()->instance('env', 'production');

        try {
            $this->post($url)->assertStatus(419);
            $this->assertSame($before, $appointment->fresh()->getAttributes());
            $this->assertDatabaseCount('appointment_events', 0);

            $token = str_repeat('c', 40);
            $this->withSession(['_token' => $token])->withHeader('X-CSRF-TOKEN', $token)->post($url)->assertRedirect();
            $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_CANCELLED]);
        } finally {
            app()->instance('env', $environment);
        }
    }

    public function test_terminal_status_repeat_cancel_and_previously_signed_link_after_staff_cancel_are_read_only(): void
    {
        foreach ([Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW] as $status) {
            [$shop, $appointment] = $this->appointmentContext();
            $appointment->update(['status' => $status]);
            $this->assertMutationActionsRejectWithoutEffects($shop, $appointment);
        }

        [$shop, $appointment] = $this->appointmentContext();
        $cancelUrl = URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]);
        $rescheduleUrl = URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => Membership::ROLE_OWNER]);
        $this->enableAppointmentPush();
        Queue::fake([SendAppointmentPush::class]);
        $this->actingAs($owner)->post(route('tenant.appointments.cancel', [$shop, $appointment], false))->assertRedirect();
        Queue::assertPushedTimes(SendAppointmentPush::class, 1);
        $before = $appointment->fresh()->getAttributes();
        $eventCount = AppointmentEvent::query()->where('appointment_id', $appointment->id)->count();
        $this->post($cancelUrl)->assertNotFound();
        $this->post($rescheduleUrl, ['starts_at' => '2026-10-05T11:00'])->assertNotFound();
        $this->post($cancelUrl)->assertNotFound();
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertSame($eventCount, AppointmentEvent::query()->where('appointment_id', $appointment->id)->count());
        Queue::assertPushedTimes(SendAppointmentPush::class, 1);
    }

    public function test_inactive_shop_and_suspended_module_block_both_signed_mutations(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $cancelUrl = URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]);
        $rescheduleUrl = URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]);
        $this->enableAppointmentPush();
        Queue::fake([SendAppointmentPush::class]);
        $before = $appointment->fresh()->getAttributes();
        $shop->update(['status' => Barbershop::STATUS_INACTIVE]);
        $this->post($cancelUrl)->assertNotFound();
        $this->post($rescheduleUrl, ['starts_at' => '2026-10-05T11:00'])->assertNotFound();
        $shop->update(['status' => Barbershop::STATUS_ACTIVE]);
        app(ModuleContainment::class)->suspend($shop, 'appointments', 'test');
        $this->post($cancelUrl)->assertNotFound();
        $this->post($rescheduleUrl, ['starts_at' => '2026-10-05T11:00'])->assertNotFound();
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertDatabaseCount('appointment_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_cutoff_allows_reschedule_at_twelve_hours_and_rejects_one_second_below(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $url = URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]);
        $this->travelTo('2026-10-05 04:00:00 UTC');
        $this->post($url, ['starts_at' => '2026-10-05T11:00'])->assertRedirect();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'starts_at' => '2026-10-05 11:00:00']);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id, 'before_starts_at' => '2026-10-05 10:00:00', 'after_starts_at' => '2026-10-05 11:00:00',
        ]);

        [$shop, $appointment] = $this->appointmentContext();
        $url = URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]);
        $this->travelTo('2026-10-05 04:00:01 UTC');
        $before = $appointment->fresh()->getAttributes();
        $this->post($url, ['starts_at' => '2026-10-05T11:00'])->assertNotFound();
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id]);
    }

    public function test_guest_linked_customer_and_unrelated_logged_user_are_distinguished_in_history_and_push(): void
    {
        [$shop, $guestAppointment] = $this->appointmentContext();
        [$linkedShop, $linkedAppointment] = $this->appointmentContext();
        [$otherShop, $otherAppointment] = $this->appointmentContext();
        $linkedUser = User::factory()->create();
        $linkedAppointment->customer->update(['user_id' => $linkedUser->id]);
        $unrelated = User::factory()->create();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $this->enableAppointmentPush();
        Queue::fake([SendAppointmentPush::class]);

        $this->post(URL::signedRoute('public.booking.management.cancel', [$shop, $guestAppointment]))->assertRedirect();
        $this->actingAs($linkedUser)->post(URL::signedRoute('public.booking.management.reschedule', [$linkedShop, $linkedAppointment]), [
            'starts_at' => '2026-10-05T11:00',
        ])->assertRedirect();
        $this->actingAs($unrelated)->post(URL::signedRoute('public.booking.management.cancel', [$otherShop, $otherAppointment]))->assertRedirect();

        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $guestAppointment->id, 'actor_kind' => 'guest', 'actor_user_id' => null,
        ]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $linkedAppointment->id, 'actor_kind' => 'customer', 'actor_user_id' => $linkedUser->id,
        ]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $otherAppointment->id, 'actor_kind' => 'guest', 'actor_user_id' => null,
        ]);
        Queue::assertPushedTimes(SendAppointmentPush::class, 3);
        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->appointmentId === $guestAppointment->id && $job->actorUserId === null && $job->event === 'cancelled');
        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->appointmentId === $linkedAppointment->id && $job->actorUserId === $linkedUser->id && $job->event === 'rescheduled');
        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->appointmentId === $otherAppointment->id && $job->actorUserId === null && $job->event === 'cancelled');
    }

    public function test_history_insert_failure_rolls_back_public_appointment_change_and_prevents_push(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $this->enableAppointmentPush();
        Queue::fake([SendAppointmentPush::class]);
        $before = $appointment->fresh()->getAttributes();
        app(AppointmentHistory::class)->record(
            $appointment,
            Appointment::STATUS_CANCELLED,
            'guest',
            null,
            $appointment->getRawOriginal('starts_at'),
            $appointment->getRawOriginal('starts_at'),
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CANCELLED,
        );
        Schema::table('appointment_events', fn (Blueprint $table) => $table->unique(['appointment_id', 'event_kind']));

        $this->postJson(URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]))->assertServerError();

        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertDatabaseCount('appointment_events', 1);
        Queue::assertNothingPushed();
    }

    public function test_same_time_customer_reschedule_does_not_create_event_or_push(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $this->enableAppointmentPush();
        Queue::fake([SendAppointmentPush::class]);
        $before = $appointment->fresh()->getAttributes();

        $this->post(URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]), [
            'starts_at' => '2026-10-05T10:00',
        ])->assertRedirect()->assertSessionHas('status', 'Tu cita fue reprogramada.');

        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id]);
        Queue::assertNothingPushed();
    }

    public function test_unavailable_resources_or_out_of_hours_reschedule_rolls_back_without_event_or_push(): void
    {
        foreach (['inactive barber', 'inactive service', 'outside hours'] as $case) {
            [$shop, $appointment] = $this->appointmentContext();
            if ($case === 'inactive barber') {
                $appointment->barber->update(['active' => false]);
                $target = '2026-10-05T11:00';
            } elseif ($case === 'inactive service') {
                $appointment->service->update(['active' => false]);
                $target = '2026-10-05T11:00';
            } else {
                $target = '2026-10-05T18:00';
            }
            $this->travelTo('2026-10-04 08:00:00 UTC');
            $this->enableAppointmentPush();
            Queue::fake([SendAppointmentPush::class]);
            $before = $appointment->fresh()->getAttributes();

            $this->postJson(URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]), ['starts_at' => $target])
                ->assertUnprocessable();
            $this->assertSame($before, $appointment->fresh()->getAttributes());
            $this->assertDatabaseMissing('appointment_events', ['appointment_id' => $appointment->id]);
            Queue::assertNothingPushed();
        }
    }

    public function test_date_choice_redirects_to_signed_reservation_url_and_renders_selected_dates_slots(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $response = $this->post(URL::signedRoute('public.booking.management.date', [$shop, $appointment]), ['date' => '2026-10-12']);
        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertTrue(URL::hasValidSignature(Request::create($target)));
        $this->get($target)->assertOk()->assertSee('value="2026-10-12"', false)->assertSee('2026-10-12T09:00');

        $empty = $this->post(URL::signedRoute('public.booking.management.date', [$shop, $appointment]), ['date' => '2026-10-06']);
        $empty->assertRedirect();
        $emptyTarget = $empty->headers->get('Location');
        $this->get($emptyTarget)->assertOk()->assertSee('name="starts_at"', false)->assertSee('disabled', false);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'starts_at' => '2026-10-05 10:00:00']);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_cancellation_form_is_only_submitted_from_a_closed_confirmation_dialog(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $before = $appointment->fresh()->getAttributes();

        $response = $this->get(PublicBookingManagement::url($shop, $appointment));

        $response->assertSee('¿Cancelar tu cita?')->assertSee('Conservar cita')->assertSee('Sí, cancelar cita');
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//button[@type="button" and normalize-space(.)="Cancelar cita"]')->length);
        $this->assertSame(1, $xpath->query('//dialog[not(@open) and @aria-labelledby="cancel-heading"]')->length);
        $this->assertSame(1, $xpath->query('//dialog//button[@type="submit" and normalize-space(.)="Sí, cancelar cita"]')->length);
        $this->assertSame(1, $xpath->query('//dialog//button[@type="button" and @autofocus and normalize-space(.)="Conservar cita"]')->length);
        $form = $xpath->query('//dialog//form[@method="POST"]')->item(0);
        $this->assertSame(URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]), $form?->getAttribute('action'));
        $this->assertSame(1, $xpath->query('//dialog//form//input[@name="_token"]')->length);
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_customer_cancel_requires_signed_action_and_records_history(): void
    {
        [$shop, $appointment] = $this->appointmentContext();
        $this->travelTo('2026-10-04 08:00:00 UTC');
        $action = URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]);

        $this->post($action)
            ->assertRedirect(PublicBookingManagement::url($shop, $appointment))
            ->assertSessionHas('status', 'Tu cita fue cancelada.');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_CANCELLED]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id, 'actor_kind' => 'guest', 'event_kind' => Appointment::STATUS_CANCELLED,
            'before_status' => Appointment::STATUS_SCHEDULED, 'after_status' => Appointment::STATUS_CANCELLED,
        ]);
    }

    private function assertMutationActionsRejectWithoutEffects(Barbershop $shop, Appointment $appointment): void
    {
        $this->enableAppointmentPush();
        Queue::fake([SendAppointmentPush::class]);
        $before = $appointment->fresh()->getAttributes();
        $eventCount = AppointmentEvent::query()->where('appointment_id', $appointment->id)->count();
        $this->post(URL::signedRoute('public.booking.management.cancel', [$shop, $appointment]))->assertNotFound();
        $this->post(URL::signedRoute('public.booking.management.reschedule', [$shop, $appointment]), [
            'starts_at' => '2026-10-05T11:00',
        ])->assertNotFound();
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertSame($eventCount, AppointmentEvent::query()->where('appointment_id', $appointment->id)->count());
        Queue::assertNothingPushed();
    }

    private function enableAppointmentPush(): void
    {
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);
    }

    /** @return array{Barbershop, Appointment} */
    private function appointmentContext(): array
    {
        $shop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $service = Service::factory()->for($shop)->create(['name' => 'Corte clásico', 'duration_minutes' => 30]);
        $barber = Barber::factory()->for($shop)->create(['display_name' => 'Profesional Uno']);
        BusinessHour::factory()->for($shop)->create(['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
        BarberAvailability::factory()->for($barber)->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        $customer = Customer::factory()->for($shop)->create([
            'name' => 'Cliente de prueba', 'email' => 'customer@example.test', 'phone' => '5551234567',
        ]);
        $appointment = Appointment::factory()->for($shop)->for($service)->for($barber)->for($customer)->create([
            'starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 10:30:00',
        ]);

        return [$shop, $appointment];
    }
}
