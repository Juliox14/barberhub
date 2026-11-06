<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\ModuleContainment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBusinessReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_report_ranks_completed_appointments_and_shows_filtered_share(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $firstService = Service::factory()->for($barbershop)->create(['name' => 'Corte Uno']);
        $secondService = Service::factory()->for($barbershop)->create(['name' => 'Barba Dos']);
        $otherShop = Barbershop::factory()->create();
        $foreignService = Service::factory()->for($otherShop)->create(['name' => 'Servicio ajeno secreto']);

        foreach ([[$firstService, Appointment::STATUS_COMPLETED], [$firstService, Appointment::STATUS_COMPLETED], [$firstService, Appointment::STATUS_CANCELLED], [$secondService, Appointment::STATUS_COMPLETED], [$foreignService, Appointment::STATUS_COMPLETED]] as [$service, $status]) {
            Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
                'status' => $status,
                'starts_at' => '2026-10-10 10:00:00',
                'ends_at' => '2026-10-10 10:30:00',
            ]);
        }

        $this->actingAs($owner)
            ->get(route('tenant.reports.services', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ], false))
            ->assertOk()
            ->assertViewHas('serviceRows', fn (array $services): bool => count($services) === 2
                && $services[0]['name'] === 'Corte Uno'
                && $services[0]['completed'] === 2
                && $services[0]['share'] === 66.7
                && $services[1]['name'] === 'Barba Dos'
                && $services[1]['completed'] === 1)
            ->assertViewHas('completedTotal', 3)
            ->assertSee('Servicios más realizados')
            ->assertSee('Corte Uno')
            ->assertSee('66.7%')
            ->assertDontSee('Servicio ajeno secreto')
            ->assertDontSee('Ingresos');

        $this->actingAs($owner)
            ->get(route('tenant.reports.services', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
                'service_id' => $firstService->id,
            ], false))
            ->assertOk()
            ->assertViewHas('serviceRows', fn (array $services): bool => count($services) === 1
                && $services[0]['completed'] === 2
                && $services[0]['share'] === 100.0);
    }

    public function test_barber_report_counts_statuses_and_uses_scheduled_completed_duration(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        $barber = Barber::factory()->for($barbershop)->create(['display_name' => 'Barbero Uno', 'active' => false]);
        $otherBarber = Barber::factory()->for($barbershop)->create(['display_name' => 'Barbero Dos']);
        $service = Service::factory()->for($barbershop)->create();

        foreach ([
            [Appointment::STATUS_COMPLETED, $barber, '10:00:30', '10:45:00'],
            [Appointment::STATUS_COMPLETED, $barber, '11:00:00', '11:30:00'],
            [Appointment::STATUS_CANCELLED, $barber, '12:00:00', '12:30:00'],
            [Appointment::STATUS_NO_SHOW, $barber, '13:00:00', '13:30:00'],
            [Appointment::STATUS_SCHEDULED, $barber, '14:00:00', '14:30:00'],
            [Appointment::STATUS_COMPLETED, $otherBarber, '15:00:00', '15:30:00'],
        ] as [$status, $assignedBarber, $start, $end]) {
            Appointment::factory()->for($barbershop)->for($assignedBarber)->for($service)->create([
                'status' => $status,
                'starts_at' => '2026-10-10 '.$start,
                'ends_at' => '2026-10-10 '.$end,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('tenant.reports.barbers', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
                'barber_id' => $barber->id,
            ], false))
            ->assertOk()
            ->assertViewHas('barberRows', fn (array $barbers): bool => count($barbers) === 1
                && $barbers[0]['name'] === 'Barbero Uno'
                && $barbers[0]['completed'] === 2
                && $barbers[0]['cancelled'] === 1
                && $barbers[0]['noShow'] === 1
                && $barbers[0]['scheduled'] === 1
                && $barbers[0]['bookedMinutes'] === 74.5)
            ->assertViewHas('totals', [
                'completed' => 2,
                'cancelled' => 1,
                'noShow' => 1,
                'scheduled' => 1,
                'bookedMinutes' => 74.5,
            ])
            ->assertSee('Actividad por barbero')
            ->assertSee('74.5 min')
            ->assertSee('no es una clasificación de productividad');
    }

    public function test_barber_report_totals_minutes_across_multiple_query_batches(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        Appointment::factory()->count(501)->for($barbershop)->for($barber)->for($service)->completed()->create([
            'starts_at' => '2026-10-10 10:00:00',
            'ends_at' => '2026-10-10 10:30:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.barbers', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ], false))
            ->assertOk()
            ->assertViewHas('totals', fn (array $totals): bool => $totals['completed'] === 501
                && $totals['bookedMinutes'] === 15030.0)
            ->assertSee('15,030.0 min');
    }

    public function test_customer_report_deduplicates_attendees_and_classifies_first_visit_across_filters(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $otherBarber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        $otherService = Service::factory()->for($barbershop)->create();
        $returningCustomer = Customer::factory()->for($barbershop)->create(['name' => 'Cliente Recurrente', 'user_id' => null]);
        $newCustomer = Customer::factory()->for($barbershop)->create(['name' => 'Cliente Nuevo', 'user_id' => null]);
        $cancelledCustomer = Customer::factory()->for($barbershop)->create(['name' => 'Solo Cancelado']);
        $otherShop = Barbershop::factory()->create();
        $foreignCustomer = Customer::factory()->for($otherShop)->create(['name' => 'Cliente ajeno secreto']);
        Appointment::factory()->for($otherShop)->for($barber)->for($service)->create([
            'customer_id' => $newCustomer->id,
            'status' => Appointment::STATUS_COMPLETED,
            'starts_at' => '2026-09-01 09:00:00',
            'ends_at' => '2026-09-01 09:30:00',
        ]);

        $appointments = [
            [$returningCustomer, $otherBarber, $otherService, Appointment::STATUS_COMPLETED, '2026-09-20 09:00:00'],
            [$returningCustomer, $barber, $service, Appointment::STATUS_COMPLETED, '2026-10-03 09:00:00'],
            [$returningCustomer, $barber, $service, Appointment::STATUS_COMPLETED, '2026-10-04 09:00:00'],
            [$newCustomer, $otherBarber, $otherService, Appointment::STATUS_COMPLETED, '2026-10-01 00:00:00'],
            [$newCustomer, $barber, $service, Appointment::STATUS_COMPLETED, '2026-10-06 09:00:00'],
            [$cancelledCustomer, $barber, $service, Appointment::STATUS_CANCELLED, '2026-10-07 09:00:00'],
            [$foreignCustomer, $barber, $service, Appointment::STATUS_COMPLETED, '2026-10-08 09:00:00'],
            [null, $barber, $service, Appointment::STATUS_COMPLETED, '2026-10-09 09:00:00'],
        ];

        foreach ($appointments as [$customer, $assignedBarber, $assignedService, $status, $startsAt]) {
            Appointment::factory()->for($barbershop)->for($assignedBarber)->for($assignedService)->create([
                'customer_id' => $customer?->id,
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => date('Y-m-d H:i:s', strtotime($startsAt.' +30 minutes')),
            ]);
        }

        $this->actingAs($owner)
            ->get(route('tenant.reports.customers', [
                'barbershop' => $barbershop,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
                'barber_id' => $barber->id,
                'service_id' => $service->id,
            ], false))
            ->assertOk()
            ->assertViewHas('customerSummary', [
                'unique' => 2,
                'new' => 1,
                'returning' => 1,
                'unidentifiedAppointments' => 1,
            ])
            ->assertSee('Clientes nuevos y recurrentes')
            ->assertSee('Clientes atendidos')
            ->assertSee('Nuevos')
            ->assertSee('Recurrentes')
            ->assertSee('Citas completadas sin cliente identificado (sin customer_id):')
            ->assertSee('Un cliente sin cuenta de usuario sí se considera identificado')
            ->assertDontSee('Solo Cancelado')
            ->assertDontSee('Cliente ajeno secreto');
    }

    public function test_each_business_report_renders_an_empty_state_without_matching_data(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('tenant.reports.services', $barbershop, false))
            ->assertOk()
            ->assertSee('No hay servicios completados en este periodo');
        $this->get(route('tenant.reports.barbers', $barbershop, false))
            ->assertOk()
            ->assertSee('No hay actividad de barberos en este periodo');
        $this->get(route('tenant.reports.customers', $barbershop, false))
            ->assertOk()
            ->assertSee('No hay clientes identificados atendidos en este periodo');
    }

    public function test_reports_forbid_barbers_and_strangers_and_require_the_appointments_module(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $stranger = User::factory()->create();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->get(route('tenant.reports.services', $barbershop, false))
            ->assertRedirect(route('login', absolute: false));
        $this->actingAs($barber)->get(route('tenant.reports.barbers', $barbershop, false))->assertForbidden();
        $this->actingAs($stranger)->get(route('tenant.reports.customers', $barbershop, false))->assertForbidden();
        $this->actingAs($platformAdmin)->get(route('tenant.reports.services', $barbershop, false))->assertOk();

        ModuleContainment::query()->create([
            'barbershop_id' => $barbershop->id,
            'module' => 'appointments',
            'reason' => 'Test containment',
            'expires_at' => now()->addMinutes(5),
        ]);

        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $this->actingAs($owner)->get(route('tenant.reports.customers', $barbershop, false))->assertForbidden();
    }
}
