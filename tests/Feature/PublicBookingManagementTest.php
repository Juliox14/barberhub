<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PublicBookingConfirmed;
use App\Support\ModuleContainment;
use App\Support\PublicBookingManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
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

    /** @return array{Barbershop, Appointment} */
    private function appointmentContext(): array
    {
        $shop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $service = Service::factory()->for($shop)->create(['name' => 'Corte clásico']);
        $barber = Barber::factory()->for($shop)->create(['display_name' => 'Profesional Uno']);
        $customer = Customer::factory()->for($shop)->create([
            'name' => 'Cliente de prueba', 'email' => 'customer@example.test', 'phone' => '5551234567',
        ]);
        $appointment = Appointment::factory()->for($shop)->for($service)->for($barber)->for($customer)->create([
            'starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 10:30:00',
        ]);

        return [$shop, $appointment];
    }
}
