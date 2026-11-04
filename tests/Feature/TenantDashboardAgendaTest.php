<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantDashboardAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_todays_scheduled_appointments_for_their_barbershop_in_time_order(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 01:00:00', 'UTC'));
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create(['display_name' => 'Barbero de hoy']);
        $service = Service::factory()->for($barbershop)->create(['name' => 'Corte real']);
        $customer = Customer::factory()->for($barbershop)->create(['name' => 'Cliente de hoy']);
        $factory = Appointment::factory()->for($barbershop)->for($barber)->for($service);
        $factory->for($customer)->create(['starts_at' => '2026-10-05 23:00:00', 'ends_at' => '2026-10-05 23:30:00']);
        $factory->create(['starts_at' => '2026-10-05 09:00:00', 'ends_at' => '2026-10-05 09:30:00']);
        $excludedService = Service::factory()->for($barbershop)->create(['name' => 'Servicio excluido']);
        foreach ([
            ['2026-10-04 23:59:00', Appointment::STATUS_SCHEDULED],
            ['2026-10-06 00:00:00', Appointment::STATUS_SCHEDULED],
            ['2026-10-05 12:00:00', Appointment::STATUS_CANCELLED],
            ['2026-10-05 13:00:00', Appointment::STATUS_COMPLETED],
            ['2026-10-05 14:00:00', Appointment::STATUS_NO_SHOW],
        ] as [$startsAt, $status]) {
            $factory->create([
                'starts_at' => $startsAt,
                'ends_at' => Carbon::parse($startsAt)->addMinutes(30),
                'status' => $status,
                'service_id' => $excludedService->id,
            ]);
        }
        Appointment::factory()->create([
            'service_id' => Service::factory()->create(['name' => 'Servicio ajeno'])->id,
            'starts_at' => '2026-10-05 10:00:00',
        ]);

        $this->actingAs($owner)->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSeeInOrder(['09:00', '23:00'])
            ->assertSee('Corte real')
            ->assertSee('Cliente de hoy')
            ->assertSee('Cliente sin registrar')
            ->assertSee('Barbero de hoy')
            ->assertDontSee('Servicio excluido')
            ->assertDontSee('Servicio ajeno')
            ->assertDontSee('Ejemplo')
            ->assertDontSee('La agenda se conectará')
            ->assertSee('Miembros activos')
            ->assertSee('Equipo de barbería');
    }

    public function test_dashboard_shows_an_empty_agenda_instead_of_examples_when_there_are_no_appointments(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee('No hay citas programadas para hoy')
            ->assertDontSee('Primer servicio')
            ->assertDontSee('Servicio programado')
            ->assertDontSee('Ejemplo');
    }

    public function test_dashboard_new_appointment_link_opens_the_existing_creation_form(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $createUrl = route('tenant.appointments.index', ['barbershop' => $barbershop, 'create' => 'appointment']);

        $this->actingAs($owner)->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee('href="'.$createUrl.'"', false)
            ->assertSee('Nueva cita')
            ->assertDontSee('Nueva cita · Próximamente');

        $this->get($createUrl)
            ->assertOk()
            ->assertSee('isOpen: true', false)
            ->assertSee('id="create-appointment-title"', false)
            ->assertSee('action="'.route('tenant.appointments.store', $barbershop).'"', false)
            ->assertSee('form="appointment-create"', false);
    }

    public function test_regular_agenda_visit_keeps_the_creation_form_closed(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)->get(route('tenant.appointments.index', $barbershop, false))
            ->assertOk()
            ->assertSee('id="create-appointment-title"', false)
            ->assertSee('isOpen: false', false)
            ->assertDontSee('isOpen: true', false);
    }

    public function test_barber_dashboard_only_shows_their_own_appointments(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));
        $barbershop = Barbershop::factory()->create(['timezone' => 'UTC']);
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $barber = Barber::factory()->for($barbershop)->for($user)->create();
        Appointment::factory()->for($barbershop)->for($barber)->create([
            'service_id' => Service::factory()->for($barbershop)->create(['name' => 'Mi servicio'])->id,
        ]);
        Appointment::factory()->for($barbershop)->create([
            'service_id' => Service::factory()->for($barbershop)->create(['name' => 'Servicio de otro barbero'])->id,
        ]);

        $this->actingAs($user)->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee('Mi servicio')
            ->assertDontSee('Servicio de otro barbero');
    }
}
