<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Support\AppointmentScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedules_internal_appointment_with_service_derived_duration(): void
    {
        [$barbershop, $barber, $service, $customer] = $this->scheduleableContext(durationMinutes: 45);

        $appointment = app(AppointmentScheduler::class)->schedule(
            barbershop: $barbershop,
            barber: $barber,
            service: $service,
            customer: $customer,
            startsAt: Carbon::parse('2026-10-05 10:15:00'),
        );

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertSame('2026-10-05 10:15:00', $appointment->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 11:00:00', $appointment->ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('appointments', [
            'barbershop_id' => $barbershop->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_rejects_cross_tenant_barber_service_and_customer(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $barber = Barber::factory()->for($otherBarbershop)->create(['active' => true]);
        $service = Service::factory()->for($otherBarbershop)->create(['active' => true]);
        $customer = Customer::factory()->for($otherBarbershop)->create();

        $this->expectValidationErrors(function () use ($barbershop, $barber, $service, $customer): void {
            app(AppointmentScheduler::class)->schedule(
                barbershop: $barbershop,
                barber: $barber,
                service: $service,
                customer: $customer,
                startsAt: Carbon::parse('2026-10-05 10:00:00'),
            );
        }, [
            'barber' => ['Selecciona un barbero activo de esta barbería.'],
            'service' => ['Selecciona un servicio activo de esta barbería.'],
            'customer' => ['Selecciona un cliente de esta barbería.'],
        ]);
    }

    public function test_rejects_start_time_outside_fifteen_minute_slot_grid(): void
    {
        [$barbershop, $barber, $service] = $this->scheduleableContext();

        $this->expectScheduleValidationError(
            barbershop: $barbershop,
            barber: $barber,
            service: $service,
            startsAt: '2026-10-05 10:07:00',
            expectedErrors: ['starts_at' => ['La cita debe comenzar en un bloque de 15 minutos.']],
        );
    }

    public function test_rejects_appointment_outside_business_hours(): void
    {
        [$barbershop, $barber, $service] = $this->scheduleableContext(durationMinutes: 60);

        $this->expectScheduleValidationError(
            barbershop: $barbershop,
            barber: $barber,
            service: $service,
            startsAt: '2026-10-05 17:30:00',
            expectedErrors: ['starts_at' => ['La cita debe estar dentro del horario de negocio del día seleccionado.']],
        );
    }

    public function test_rejects_appointment_outside_barber_availability(): void
    {
        [$barbershop, $barber, $service] = $this->scheduleableContext(availabilityStart: '10:00', availabilityEnd: '16:00');

        $this->expectScheduleValidationError(
            barbershop: $barbershop,
            barber: $barber,
            service: $service,
            startsAt: '2026-10-05 09:30:00',
            expectedErrors: ['starts_at' => ['La cita debe estar dentro de la disponibilidad activa del barbero.']],
        );
    }

    public function test_rejects_overlapping_scheduled_appointment_for_same_barber(): void
    {
        [$barbershop, $barber, $service] = $this->scheduleableContext();
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => Carbon::parse('2026-10-05 10:00:00'),
            'ends_at' => Carbon::parse('2026-10-05 10:45:00'),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        $this->expectScheduleValidationError(
            barbershop: $barbershop,
            barber: $barber,
            service: $service,
            startsAt: '2026-10-05 10:30:00',
            expectedErrors: ['starts_at' => ['El barbero ya tiene una cita activa en ese horario.']],
            expectedAppointmentCount: 1,
        );
    }

    public function test_cancelled_appointment_does_not_block_same_barber_slot(): void
    {
        [$barbershop, $barber, $service] = $this->scheduleableContext();
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->cancelled()->create([
            'starts_at' => Carbon::parse('2026-10-05 10:00:00'),
            'ends_at' => Carbon::parse('2026-10-05 10:30:00'),
        ]);

        $appointment = app(AppointmentScheduler::class)->schedule(
            barbershop: $barbershop,
            barber: $barber,
            service: $service,
            customer: null,
            startsAt: Carbon::parse('2026-10-05 10:00:00'),
        );

        $this->assertSame(Appointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertDatabaseCount('appointments', 2);
    }

    /**
     * @return array{0: Barbershop, 1: Barber, 2: Service, 3: Customer}
     */
    private function scheduleableContext(
        int $durationMinutes = 30,
        string $businessOpen = '09:00',
        string $businessClose = '18:00',
        string $availabilityStart = '09:00',
        string $availabilityEnd = '18:00',
    ): array {
        $barbershop = Barbershop::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create(['active' => true]);
        $service = Service::factory()->for($barbershop)->create(['duration_minutes' => $durationMinutes, 'active' => true]);
        $customer = Customer::factory()->for($barbershop)->create();
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'opens_at' => $businessOpen,
            'closes_at' => $businessClose,
            'closed' => false,
        ]);
        BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'starts_at' => $availabilityStart,
            'ends_at' => $availabilityEnd,
            'active' => true,
        ]);

        return [$barbershop, $barber, $service, $customer];
    }

    /**
     * @param  array<string, array<int, string>>  $expectedErrors
     */
    private function expectScheduleValidationError(
        Barbershop $barbershop,
        Barber $barber,
        Service $service,
        string $startsAt,
        array $expectedErrors,
        int $expectedAppointmentCount = 0,
    ): void {
        $this->expectValidationErrors(function () use ($barbershop, $barber, $service, $startsAt): void {
            app(AppointmentScheduler::class)->schedule(
                barbershop: $barbershop,
                barber: $barber,
                service: $service,
                customer: null,
                startsAt: Carbon::parse($startsAt),
            );
        }, $expectedErrors, $expectedAppointmentCount);
    }

    /**
     * @param  array<string, array<int, string>>  $expectedErrors
     */
    private function expectValidationErrors(callable $callback, array $expectedErrors, int $expectedAppointmentCount = 0): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            $this->assertSame($expectedErrors, $exception->errors());
            $this->assertDatabaseCount('appointments', $expectedAppointmentCount);

            return;
        }

        $this->fail('Expected appointment validation to fail.');
    }
}
